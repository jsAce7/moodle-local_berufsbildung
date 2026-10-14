<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Hook-Callbacks.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung;

use core\hook\navigation\primary_extend;
use core_user\hook\extend_user_menu;
use local_berufsbildung\hilfe\sammlung;
use local_berufsbildung\navigation\menuepunkt;
use moodle_url;
use navigation_node;

/**
 * Haengt die Einstiege direkt in die primaere Navigationsleiste
 * (Dashboard / Meine Kurse / ...) statt in den einklapp- oder ausblendbaren
 * Seiten-Drawer - der ist je nach Theme nicht zuverlaessig erreichbar, die
 * primaere Leiste ist es immer.
 *
 * Drei Eintraege, je nach Rolle: "Meine Lehre" fuer Lernende, "Meine
 * Lernenden" fuer ausbildende Personen, "Berufsbildung" fuer die Leitung.
 * Aufsetzende Plugins melden Unterpunkte ueber den Callback
 * <plugin>_berufsbildung_navigation() (navigation\menuepunkt). Unter
 * "Meine Lehre" und "Meine Lernenden" erscheinen sie als Reiter auf der
 * Seite (output\seitennavigation), die Eintraege hier bleiben direkte
 * Links. "Berufsbildung" hat keine eigene Seite, besteht nur aus
 * Unterpunkten und ist deshalb aufklappbar; es erscheint nur, wenn
 * mindestens einer geliefert wird.
 *
 * Die Blockverwaltung ist Pflege von Stammdaten und haengt deshalb als
 * Reiter an "Meine Lernenden".
 *
 * Die Hilfeseite steht nicht in der primaeren Leiste, sondern im
 * Benutzermenue: sie ist Nachschlagen, kein Arbeitsort.
 */
class hook_callbacks {
    /** Praefix der Knoten-Schluessel; dahinter steht das Menue (menuepunkt::MEINE_LEHRE usw.). */
    public const KNOTEN_PRAEFIX = 'local_berufsbildung_';

    /** Key des Core-Knotens "Website-Administration" in der primaeren Navigation. */
    private const SITEADMIN_KEY = 'siteadminnode';

    /** Callback in der lib.php der aufsetzenden Plugins. */
    private const CALLBACK = 'berufsbildung_navigation';

    /**
     * Haengt die Einstiege in die primaere Navigation.
     *
     * @param primary_extend $hook
     */
    public static function primary_extend(primary_extend $hook): void {
        global $USER;

        if (!isloggedin() || isguestuser()) {
            return;
        }

        $primaryview = $hook->get_primaryview();
        $userid = (int) $USER->id;
        $unterpunkte = self::gemeldete_menuepunkte();

        // Key => [Sprachstring, Seite oder null, Unterpunkte]. Die Reihenfolge
        // hier ist die Reihenfolge in der Leiste. Die Unterpunkte von "Meine
        // Lehre" und "Meine Lernenden" stehen als Reiter auf deren Seite.
        $eintraege = [];

        if (self::sieht_meine_lehre($userid)) {
            $eintraege[self::KNOTEN_PRAEFIX . menuepunkt::MEINE_LEHRE] = ['nav:meine_lehre', 'meine_lehre.php', []];
        }

        if (self::sieht_meine_lernenden($userid)) {
            $eintraege[self::KNOTEN_PRAEFIX . menuepunkt::MEINE_LERNENDEN] = [
                'nav:meine_lernenden', 'meine_lernenden.php', [],
            ];
        }

        if (!empty($unterpunkte[menuepunkt::BERUFSBILDUNG])) {
            $eintraege[self::KNOTEN_PRAEFIX . menuepunkt::BERUFSBILDUNG] = [
                'nav:berufsbildung', null, $unterpunkte[menuepunkt::BERUFSBILDUNG],
            ];
        }

        // Vor die Website-Administration einsortieren: die fachlichen
        // Einstiege sind Alltag, der Admin-Knoten die Ausnahme. add() haengt
        // immer hinten an, eine Position kennt nur add_node(). Fehlt der
        // Admin-Knoten (Nutzer ohne Admin-Zugang) oder heisst er in einer
        // kuenftigen Version anders, bleibt $vor null und die Eintraege
        // stehen wie bisher am Ende - das vermeidet zugleich die
        // debugging()-Meldung, die ein unbekannter $beforekey ausloest.
        $vor = $primaryview->find(self::SITEADMIN_KEY, null) ? self::SITEADMIN_KEY : null;

        foreach ($eintraege as $schluessel => [$stringkey, $seite, $punkte]) {
            $text = get_string($stringkey, 'local_berufsbildung');
            $url = $seite !== null ? new moodle_url('/local/berufsbildung/' . $seite) : null;

            if (empty($punkte)) {
                $primaryview->add_node(
                    navigation_node::create($text, $url, navigation_node::TYPE_CUSTOM, null, $schluessel),
                    $vor
                );
                continue;
            }

            // Aufklappbar: Moodle zeigt einen Knoten mit Kindern als Menue,
            // seine eigene URL waere dann nicht anklickbar. Deshalb nur fuer
            // "Berufsbildung", das keine eigene Seite hat.
            $knoten = $primaryview->add_node(
                navigation_node::create($text, null, navigation_node::TYPE_CUSTOM, null, $schluessel),
                $vor
            );
            foreach ($punkte as $punkt) {
                $knoten->add($punkt->text, $punkt->url, navigation_node::TYPE_CUSTOM, null, $punkt->schluessel);
            }
        }
    }

    /**
     * Haengt "Hilfe zur Berufsbildung" ins Benutzermenue, damit die
     * Hilfeseite von jeder Seite aus erreichbar ist - nur fuer Personen,
     * fuer die sie etwas enthaelt.
     *
     * @param extend_user_menu $hook
     */
    public static function extend_user_menu(extend_user_menu $hook): void {
        if (!isloggedin() || isguestuser() || !sammlung::hat_abschnitte()) {
            return;
        }

        $hook->add_navitem((object) [
            'itemtype' => 'link',
            'url' => new moodle_url('/local/berufsbildung/hilfe.php'),
            'title' => get_string('hilfe:titel', 'local_berufsbildung'),
            'titleidentifier' => 'hilfe:titel,local_berufsbildung',
        ]);
    }

    /**
     * Ob "Meine Lehre" erscheint: fuer alle mit Beruf und Jahrgang, auch vor
     * Lehrbeginn und nach dem Abschluss - die Seite zeigt dann "Lehre
     * beginnt erst" bzw. den Rueckblick. Ohne Beruf und Jahrgang
     * (PHASE_UNBEKANNT, etwa bei Berufsbildner/innen) gibt es nichts zu
     * zeigen, externen üK-Teilnehmenden ebenso wenig.
     *
     * @param int $userid
     * @return bool
     */
    public static function sieht_meine_lehre(int $userid): bool {
        return !api::ist_uek_extern($userid) && api::get_ausbildungsphase($userid) !== api::PHASE_UNBEKANNT;
    }

    /**
     * Ob "Meine Lernenden" erscheint: fuer alle, die heute mindestens eine
     * laufende Zuordnung haben.
     *
     * @param int $userid
     * @return bool
     */
    public static function sieht_meine_lernenden(int $userid): bool {
        return !empty(api::get_lernende_for($userid));
    }

    /**
     * Die Menuepunkte der aufsetzenden Plugins fuer die angemeldete Person,
     * je Menue. Ein Plugin, dessen Callback scheitert, faellt weg, statt die
     * ganze Navigation zu brechen.
     *
     * @return array<string, menuepunkt[]>
     */
    public static function gemeldete_menuepunkte(): array {
        $je = [];
        foreach (get_plugins_with_function(self::CALLBACK) as $funktionenjetyp) {
            foreach ($funktionenjetyp as $plugin => $funktion) {
                try {
                    $punkte = $funktion();
                } catch (\Throwable $e) {
                    debugging("{$plugin}_" . self::CALLBACK . '(): ' . $e->getMessage(), DEBUG_DEVELOPER);
                    continue;
                }
                foreach ($punkte as $punkt) {
                    if ($punkt instanceof menuepunkt) {
                        $je[$punkt->menue][] = $punkt;
                    }
                }
            }
        }

        return $je;
    }
}

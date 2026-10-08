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
 * Aufsetzende Plugins haengen Unterpunkte an ueber den Callback
 * <plugin>_berufsbildung_navigation() (navigation\menuepunkt). Hat ein
 * Eintrag Unterpunkte, wird er aufklappbar und sein erster Punkt fuehrt
 * auf die bisherige Seite. "Berufsbildung" besteht nur aus Unterpunkten
 * und erscheint nur, wenn mindestens einer geliefert wird.
 *
 * Die Blockverwaltung ist Pflege von Stammdaten und haengt deshalb als
 * Nebeneingang an "Meine Lernenden" (siehe meine_lernenden.php).
 */
class hook_callbacks {
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
        // hier ist die Reihenfolge in der Leiste.
        $eintraege = [];

        // Vor Lehrbeginn und nach dem Abschluss ebenfalls: die Seite zeigt dann
        // "Lehre beginnt erst" bzw. den Rueckblick. Ohne Beruf und Jahrgang
        // (PHASE_UNBEKANNT, etwa bei Berufsbildner/innen) gibt es nichts zu zeigen.
        if (!api::ist_uek_extern($userid) && api::get_ausbildungsphase($userid) !== api::PHASE_UNBEKANNT) {
            $eintraege['local_berufsbildung_meine_lehre'] = [
                'nav:meine_lehre', 'meine_lehre.php', $unterpunkte[menuepunkt::MEINE_LEHRE] ?? [],
            ];
        }

        if (!empty(api::get_lernende_for($userid))) {
            $eintraege['local_berufsbildung_meine_lernenden'] = [
                'nav:meine_lernenden', 'meine_lernenden.php', $unterpunkte[menuepunkt::MEINE_LERNENDEN] ?? [],
            ];
        }

        if (!empty($unterpunkte[menuepunkt::BERUFSBILDUNG])) {
            $eintraege['local_berufsbildung_berufsbildung'] = [
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
            // seine eigene URL ist dann nicht mehr anklickbar. Die bisherige
            // Seite steht deshalb als erster Punkt im Menue.
            $knoten = $primaryview->add_node(
                navigation_node::create($text, null, navigation_node::TYPE_CUSTOM, null, $schluessel),
                $vor
            );
            if ($url !== null) {
                $knoten->add($text, $url, navigation_node::TYPE_CUSTOM, null, $schluessel . '_seite');
            }
            foreach ($punkte as $punkt) {
                $knoten->add($punkt->text, $punkt->url, navigation_node::TYPE_CUSTOM, null, $punkt->schluessel);
            }
        }
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

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
 * Reiterleiste unter "Meine Lehre" und "Meine Lernenden".
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\output;

use context_system;
use local_berufsbildung\hook_callbacks;
use local_berufsbildung\navigation\menuepunkt;
use moodle_url;
use tabobject;

/**
 * Die Unterpunkte von "Meine Lehre" und "Meine Lernenden" stehen als Reiter
 * auf der Seite, nicht als Aufklappmenue in der primaeren Navigation: dort
 * war der Eintrag selbst nicht mehr anklickbar und die eigene Seite stand
 * doppelt im Menue.
 *
 * Erster Reiter ist die Uebersicht (meine_lehre.php bzw. meine_lernenden.php),
 * danach folgen die Punkte, die aufsetzende Plugins ueber
 * <plugin>_berufsbildung_navigation() melden. Unter "Meine Lernenden" steht
 * zuletzt die Blockverwaltung - Stammdatenpflege, die im selben Arbeitsgang
 * wie die Betreuung anfaellt, aber eine Nebenaktion ist.
 *
 * Aufsetzende Plugins zeigen dieselbe Leiste auf ihren Zielseiten, damit der
 * Weg zurueck zur Uebersicht auf der Seite bleibt:
 *
 *     seitennavigation::markiere_hauptleiste(menuepunkt::MEINE_LEHRE);
 *     echo $OUTPUT->header();
 *     echo seitennavigation::render(menuepunkt::MEINE_LEHRE, 'local_bildungsbericht_verlauf');
 */
final class seitennavigation {
    /** Schluessel des Reiters zur Blockverwaltung. */
    public const BLOECKE = 'local_berufsbildung_bloecke';

    /** Je Menue mit eigener Seite: die Seite der Uebersicht. "Berufsbildung" hat keine. */
    private const SEITEN = [
        menuepunkt::MEINE_LEHRE => 'meine_lehre.php',
        menuepunkt::MEINE_LERNENDEN => 'meine_lernenden.php',
    ];

    /**
     * Die Reiterleiste eines Menues. Leer, wenn es neben der Uebersicht
     * keinen Reiter gibt - eine Leiste mit einem einzigen Reiter fuehrt
     * nirgendwohin.
     *
     * @param string $menue menuepunkt::MEINE_LEHRE oder menuepunkt::MEINE_LERNENDEN
     * @param string|null $aktiv Schluessel des aktiven Reiters, null fuer die Uebersicht
     * @return string HTML
     */
    public static function render(string $menue, ?string $aktiv = null): string {
        global $OUTPUT;

        $reiter = self::reiter($menue);
        if (count($reiter) < 2) {
            return '';
        }

        return $OUTPUT->tabtree($reiter, $aktiv ?? self::uebersicht_schluessel($menue));
    }

    /**
     * Haelt den Eintrag des Menues in der primaeren Navigation aktiv, auch
     * auf Seiten, deren URL dort nicht vorkommt. Vor $OUTPUT->header()
     * aufzurufen.
     *
     * @param string $menue menuepunkt::MEINE_LEHRE oder menuepunkt::MEINE_LERNENDEN
     */
    public static function markiere_hauptleiste(string $menue): void {
        global $PAGE;

        self::pruefe_menue($menue);
        $PAGE->set_primary_active_tab(hook_callbacks::KNOTEN_PRAEFIX . $menue);
    }

    /**
     * Die Reiter eines Menues, fuer die angemeldete Person.
     *
     * @param string $menue
     * @return tabobject[]
     */
    private static function reiter(string $menue): array {
        self::pruefe_menue($menue);

        $reiter = [new tabobject(
            self::uebersicht_schluessel($menue),
            new moodle_url('/local/berufsbildung/' . self::SEITEN[$menue]),
            get_string('seitennavigation:uebersicht', 'local_berufsbildung')
        )];

        foreach (hook_callbacks::gemeldete_menuepunkte()[$menue] ?? [] as $punkt) {
            $reiter[] = new tabobject($punkt->schluessel, $punkt->url, $punkt->text);
        }

        if (
            $menue === menuepunkt::MEINE_LERNENDEN
            && has_capability('local/berufsbildung:manageblocks', context_system::instance())
        ) {
            $reiter[] = new tabobject(
                self::BLOECKE,
                new moodle_url('/local/berufsbildung/bloecke.php'),
                get_string('bloecke:uebersicht', 'local_berufsbildung')
            );
        }

        return $reiter;
    }

    /**
     * Schluessel des Uebersichts-Reiters.
     *
     * @param string $menue
     * @return string
     */
    private static function uebersicht_schluessel(string $menue): string {
        return hook_callbacks::KNOTEN_PRAEFIX . $menue . '_seite';
    }

    /**
     * Nur Menues mit eigener Seite haben Reiter.
     *
     * @param string $menue
     */
    private static function pruefe_menue(string $menue): void {
        if (!array_key_exists($menue, self::SEITEN)) {
            throw new \coding_exception('Menue ohne eigene Seite: ' . $menue);
        }
    }
}

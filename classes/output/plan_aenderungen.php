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
 * Aufbereitung der Einsatzaenderungen eines Versetzungsplan-Imports.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\output;

use core_collator;
use core_user;
use local_berufsbildung\persistent\einsatz_aenderung;

/**
 * Je Person die Einsaetze, die wegfallen oder neu dazukommen - fuer das
 * Template plan_aenderungen, das die Freigabe (plan_freigabe), der Upload
 * (import_plan.php) und der Verlauf (plan_verlauf.php) gemeinsam nutzen.
 */
class plan_aenderungen {
    /**
     * Rendert die Aenderungen, nach Person gruppiert.
     *
     * @param array[] $aenderungen siehe personen()
     * @return string
     */
    public static function render(array $aenderungen): string {
        global $OUTPUT;

        return $OUTPUT->render_from_template(
            'local_berufsbildung/plan_aenderungen',
            ['personen' => self::personen($aenderungen)]
        );
    }

    /**
     * Gruppiert eine flache Aenderungsliste nach Person.
     *
     * @param array[] $aenderungen je Einsatz ['userid' => int, 'art' => 'entfaellt'|'neu',
     *  'block' => string, 'kw_von' => string, 'kw_bis' => string], je Person in der
     *  Reihenfolge, in der sie erscheinen sollen
     * @return array[] je Person ['name' => string, 'einsaetze' => [['entfaellt' => bool,
     *  'block' => string, 'zeitraum' => string]]], alphabetisch
     */
    public static function personen(array $aenderungen): array {
        $jeperson = [];
        foreach ($aenderungen as $aenderung) {
            $jeperson[(int) $aenderung['userid']][] = [
                'entfaellt' => $aenderung['art'] === einsatz_aenderung::ENTFAELLT,
                'block' => (string) $aenderung['block'],
                'zeitraum' => $aenderung['kw_von'] === $aenderung['kw_bis']
                    ? (string) $aenderung['kw_von']
                    : $aenderung['kw_von'] . ' – ' . $aenderung['kw_bis'],
            ];
        }

        $personen = [];
        foreach (self::namen(array_keys($jeperson)) as $userid => $name) {
            $personen[] = ['name' => $name, 'einsaetze' => $jeperson[$userid]];
        }

        return $personen;
    }

    /**
     * Anzeigenamen, alphabetisch. Eine inzwischen geloeschte Person
     * erscheint mit ihrer userid.
     *
     * @param int[] $userids
     * @return array<int, string> userid => Name
     */
    public static function namen(array $userids): array {
        $namen = [];
        foreach ($userids as $userid) {
            $person = core_user::get_user((int) $userid);
            $namen[(int) $userid] = $person ? fullname($person) : (string) $userid;
        }
        core_collator::asort($namen);

        return $namen;
    }
}

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
 * Liest Kohorten-Mitgliedschaften - der einzige Ort in diesem Plugin, der
 * die core-Tabelle cohort_members anfasst (Architekturregel 8: kein $DB
 * ausserhalb von Persistent- und Service-Klassen).
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\service;

class kohorten_resolver {

    /**
     * Eindeutige Liste der Mitglieder-userids ueber alle uebergebenen
     * Kohorten hinweg.
     *
     * @param int[] $cohortids
     * @return int[]
     */
    public function mitglieder(array $cohortids): array {
        global $DB;

        if (empty($cohortids)) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal($cohortids, SQL_PARAMS_NAMED);
        $userids = $DB->get_fieldset_select('cohort_members', 'DISTINCT userid', "cohortid {$insql}", $params);

        return array_map('intval', $userids);
    }

    /**
     * Anzeigenamen der Kohorten - fuer die Verwaltungsseite, wo nur die
     * gespeicherte cohortid vorliegt.
     *
     * @param int[] $cohortids
     * @return array<int, string> cohortid => Name
     */
    public function namen(array $cohortids): array {
        global $DB;

        if (empty($cohortids)) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal($cohortids, SQL_PARAMS_NAMED);

        return $DB->get_records_select_menu('cohort', "id {$insql}", $params, '', 'id, name');
    }
}

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
 * Aufloesung der optionalen Block-Kurs-Verknuepfung.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\service;

use local_berufsbildung\persistent\block;

/**
 * Kapselt den Zugriff auf Moodle-Kurse fuer die reine Anzeigeverknuepfung
 * eines Ausbildungsblocks. Kurszugriff und Einschreibungen bleiben beim
 * Moodle-Core und werden hier bewusst nicht veraendert.
 */
class block_kurs_service {
    /**
     * Liefert alle Nicht-Startseitenkurse als Auswahl fuer die Blockverwaltung.
     *
     * @return array<int, string> courseid => lesbare Kursbezeichnung
     */
    public function get_kursauswahl(): array {
        global $DB;

        $kurse = $DB->get_records_select(
            'course',
            'id <> :siteid',
            ['siteid' => SITEID],
            'fullname ASC',
            'id, fullname, shortname'
        );
        $auswahl = [];
        foreach ($kurse as $kurs) {
            $auswahl[(int) $kurs->id] = format_string($kurs->fullname)
                . ' (' . format_string($kurs->shortname) . ')';
        }

        return $auswahl;
    }

    /**
     * Liefert den einem Block zugeordneten Kurs, sofern er noch existiert.
     *
     * @param int $blockid
     * @return \stdClass|null Kurs mit id, fullname und shortname oder null
     */
    public function get_kurs(int $blockid): ?\stdClass {
        global $DB;

        $block = block::get_record(['id' => $blockid]);
        if ($block === false || $block->get('courseid') === null) {
            return null;
        }

        $courseid = (int) $block->get('courseid');
        if ($courseid <= 0) {
            return null;
        }

        $kurs = $DB->get_record('course', ['id' => $courseid], 'id, fullname, shortname', IGNORE_MISSING);

        return $kurs === false ? null : $kurs;
    }

    /**
     * Die Kurse mehrerer Bloecke in einer Abfrage - fuer die Blockliste,
     * die sonst je Block zwei Abfragen braucht (get_kurs()).
     *
     * @param \local_berufsbildung\persistent\block[] $bloecke
     * @return array<int, \stdClass> Block-ID => Kurs mit id, fullname und shortname;
     *         Bloecke ohne (noch existierenden) Kurs fehlen
     */
    public function get_kurse(array $bloecke): array {
        global $DB;

        $kursids = [];
        foreach ($bloecke as $block) {
            $courseid = (int) $block->get('courseid');
            if ($courseid > 0) {
                $kursids[(int) $block->get('id')] = $courseid;
            }
        }
        if (empty($kursids)) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal(array_unique($kursids), SQL_PARAMS_NAMED);
        $kurse = $DB->get_records_select('course', "id {$insql}", $params, '', 'id, fullname, shortname');

        $ergebnis = [];
        foreach ($kursids as $blockid => $courseid) {
            if (isset($kurse[$courseid])) {
                $ergebnis[$blockid] = $kurse[$courseid];
            }
        }

        return $ergebnis;
    }
}

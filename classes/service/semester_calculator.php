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
 * Semesterberechnung, getrennt von der Profilfeld-Auflösung.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\service;

/**
 * Berechnet Semester und Semestergrenzen aus Jahrgang und Stichtag.
 *
 * Kennt weder Moodle-Nutzer noch Profilfelder - nimmt nur Jahrgang und
 * Stichtag entgegen, damit sie ohne Moodle-Nutzer testbar ist.
 */
class semester_calculator {

    /**
     * @param int $startmonat Monat des Lehrbeginns, 1..12 (Standard 8 = August)
     * @param int $lehrdauer_semester Lehrdauer in Semestern (Standard 8)
     */
    public function __construct(
        private readonly int $startmonat = 8,
        private readonly int $lehrdauer_semester = 8,
    ) {
    }

    /**
     * Semester zum Stichtag, oder null wenn die Lehre noch nicht begonnen
     * hat oder bereits beendet ist.
     *
     * @param int $jahrgang Jahr des Lehrbeginns
     * @param int $stichtag Timestamp
     * @return int|null 1..lehrdauer_semester, oder null
     */
    public function berechne_semester(int $jahrgang, int $stichtag): ?int {
        $jahr_stichtag = (int) date('Y', $stichtag);
        $monat_stichtag = (int) date('n', $stichtag);

        $monate = ($jahr_stichtag - $jahrgang) * 12 + ($monat_stichtag - $this->startmonat);
        $semester = (int) floor($monate / 6) + 1;

        if ($semester < 1 || $semester > $this->lehrdauer_semester) {
            return null;
        }

        return $semester;
    }

    /**
     * Anfang und Ende eines Semesters als Timestamps.
     *
     * @param int $jahrgang Jahr des Lehrbeginns
     * @param int $semester 1..lehrdauer_semester
     * @return array{0: int, 1: int} [von, bis]
     */
    public function semester_grenzen(int $jahrgang, int $semester): array {
        $monate_von = ($this->startmonat - 1) + ($semester - 1) * 6;
        $monate_bis = $monate_von + 6;

        $von = mktime(0, 0, 0, ($monate_von % 12) + 1, 1, $jahrgang + intdiv($monate_von, 12));
        $bis = mktime(0, 0, 0, ($monate_bis % 12) + 1, 1, $jahrgang + intdiv($monate_bis, 12)) - 1;

        return [$von, $bis];
    }
}

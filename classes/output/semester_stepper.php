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
 * Rendert den Fortschritt einer lernenden Person durch ihre Lehre als
 * Semester-Punkte, gruppiert nach Lehrjahr. Rein visuell - dieselbe
 * Information steht bereits als Text daneben (form:ausbildungsstand),
 * deshalb bleibt der Stepper dekorativ (aria-hidden).
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\output;

class semester_stepper {

    /**
     * @param int $semester Aktuelles Semester (1..$gesamtsemester)
     * @param int $gesamtsemester Lehrdauer in Semestern (siehe ausbildungsstand::$gesamtsemester)
     * @param bool $kompakt Kleine Variante fuer die Roster-Karten in meine_lernenden.php
     */
    public static function render(int $semester, int $gesamtsemester, bool $kompakt = false): string {
        global $OUTPUT;

        $lehrjahre = [];
        for ($einzelsemester = 1; $einzelsemester <= $gesamtsemester; $einzelsemester++) {
            $lehrjahr = (int) ceil($einzelsemester / 2);
            $lehrjahre[$lehrjahr][] = [
                'istvergangen' => $einzelsemester < $semester,
                'istaktuell' => $einzelsemester === $semester,
            ];
        }

        $gruppen = [];
        foreach ($lehrjahre as $punkte) {
            $gruppen[] = ['punkte' => $punkte];
        }

        return $OUTPUT->render_from_template('local_berufsbildung/semester_stepper', [
            'gruppen' => $gruppen,
            'kompakt' => $kompakt,
        ]);
    }
}

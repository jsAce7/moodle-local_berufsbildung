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
 * Scheduled Task: einen veralteten Versetzungsplan den Site-Admins melden.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\task;

use core\task\scheduled_task;
use local_berufsbildung\versetzungsplan\alterung_service;

/**
 * Geplante Aufgabe: veralteten Versetzungsplan melden.
 */
class pruefe_planalterung extends scheduled_task {
    /**
     * Anzeigename der geplanten Aufgabe.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task:pruefe_planalterung', 'local_berufsbildung');
    }

    /**
     * Fuehrt die geplante Aufgabe aus.
     *
     */
    public function execute(): void {
        $anzahl = (new alterung_service())->melde_falls_veraltet();

        mtrace(sprintf('local_berufsbildung: veralteter Versetzungsplan an %d Person(en) gemeldet.', $anzahl));
    }
}

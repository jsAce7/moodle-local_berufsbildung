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
 * Scheduled Task: Kohorten-Links mit der aktuellen Mitgliedschaft abgleichen.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\task;

use core\task\scheduled_task;
use local_berufsbildung\service\kohorten_sync_service;

/**
 * Geplante Aufgabe: Kohorten-Verknuepfungen abgleichen.
 */
class sync_kohorten extends scheduled_task {
    /**
     * Anzeigename der geplanten Aufgabe.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task:sync_kohorten', 'local_berufsbildung');
    }

    /**
     * Fuehrt die geplante Aufgabe aus.
     *
     */
    public function execute(): void {
        $ergebnis = (new kohorten_sync_service())->synchronisiere_alle();

        mtrace(sprintf(
            'local_berufsbildung: %d Zuordnung(en) erzeugt, %d beendet.',
            $ergebnis['erzeugt'],
            $ergebnis['beendet']
        ));
    }
}

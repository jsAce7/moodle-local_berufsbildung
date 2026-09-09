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
 * Scheduled Task: Rolle 'berufsbildner' mit der Zuordnungstabelle abgleichen.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\task;

use core\task\scheduled_task;
use local_berufsbildung\service\role_sync_service;

class sync_role_assignments extends scheduled_task {

    public function get_name(): string {
        return get_string('task:sync_role_assignments', 'local_berufsbildung');
    }

    public function execute(): void {
        $ergebnis = (new role_sync_service())->synchronisiere();

        mtrace(sprintf(
            'local_berufsbildung: Rolle bei %d Zuordnung(en) zugewiesen, bei %d entzogen. '
                . 'Planungsrolle bei %d Person(en) zugewiesen, bei %d entzogen.',
            $ergebnis['zugewiesen'],
            $ergebnis['entzogen'],
            $ergebnis['planung_zugewiesen'],
            $ergebnis['planung_entzogen']
        ));
    }
}

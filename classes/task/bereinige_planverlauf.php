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
 * Scheduled Task: den Versetzungsplan-Verlauf auf ein Jahr kuerzen.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\task;

use core\task\scheduled_task;
use local_berufsbildung\versetzungsplan\import_service;

/**
 * Geplante Aufgabe: im Versetzungsplan-Verlauf geaenderte Einsaetze und
 * Protokolltext aelter als ein Jahr entfernen
 * (import_service::bereinige_verlauf()).
 */
class bereinige_planverlauf extends scheduled_task {
    /**
     * Anzeigename der geplanten Aufgabe.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task:bereinige_planverlauf', 'local_berufsbildung');
    }

    /**
     * Fuehrt die geplante Aufgabe aus.
     *
     */
    public function execute(): void {
        $anzahl = (new import_service())->bereinige_verlauf();

        mtrace(sprintf(
            'local_berufsbildung: Versetzungsplan-Verlauf bereinigt, %d geaenderte Einsaetze geloescht, %d Protokolle geleert.',
            $anzahl['aenderungen'],
            $anzahl['protokolle']
        ));
    }
}

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
 * Event-Observer.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung;

use core\event\capability_unassigned;
use local_berufsbildung\service\leitungsrolle_service;

/**
 * Reagiert auf Aenderungen ausserhalb des Plugins, die seine Rollen betreffen.
 */
class observer {
    /**
     * Stellt die Rechte der Leitungsrolle wieder her, sobald ihr eine
     * Capability entzogen wurde - siehe leitungsrolle_service.
     *
     * @param capability_unassigned $event
     */
    public static function capability_unassigned(capability_unassigned $event): void {
        $service = new leitungsrolle_service();
        if ((int) $event->objectid !== $service->rolle_id()) {
            return;
        }

        $service->stelle_sicher();
    }
}

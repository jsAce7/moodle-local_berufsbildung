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
 * Callbacks fuer den Moodle-Kalender.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_berufsbildung\service\termin_service;

/**
 * Die Schaltflaeche eines Faelligkeitstermins in Kalender und Zeitleiste.
 * Sie fuehrt ueber termin.php, das die Adresse erst beim Klick aufloest
 * (termin_service::ziel_fuer()).
 *
 * @param calendar_event $event
 * @param \core_calendar\action_factory $factory
 * @param int $userid Wer den Termin ansieht, 0 fuer die angemeldete Person
 * @return \core_calendar\local\event\entities\action_interface|null
 */
function local_berufsbildung_core_calendar_provide_event_action(
    calendar_event $event,
    \core_calendar\action_factory $factory,
    $userid = 0
) {
    global $USER;

    if ((int) $event->userid !== ((int) $userid ?: (int) $USER->id)) {
        return null;
    }

    return $factory->create_instance(
        get_string('termin:oeffnen', 'local_berufsbildung'),
        new moodle_url('/local/berufsbildung/termin.php', ['id' => $event->id]),
        1,
        true
    );
}

/**
 * Ein Faelligkeitstermin erscheint nur, solange die Anbindung
 * eingeschaltet ist und seine Besitzerin oder sein Besitzer heute
 * zustaendig ist (termin_service::ist_termin_sichtbar()). Ueber die id, nicht
 * $event->instance: das setzt der Kalender nur bei Aktivitaeten.
 *
 * @param calendar_event $event
 * @param int $userid Wer den Termin ansieht, 0 fuer die angemeldete Person
 * @return bool
 */
function local_berufsbildung_core_calendar_is_event_visible(calendar_event $event, $userid = 0): bool {
    return termin_service::ist_termin_sichtbar((int) $event->id);
}

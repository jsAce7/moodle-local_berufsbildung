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
 * Oeffnet die Aufgabe hinter einem Faelligkeitstermin im Kalender.
 *
 * Keine eigene Seite, nur eine Weiterleitung: Die Adresse der Aufgabe
 * steht nicht im Termin, sie wird hier ueber die Quelle aufgeloest
 * (termin_service::ziel_fuer()). Ist die Faelligkeit inzwischen erledigt,
 * fuehrt der Termin auf die Uebersicht.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);

require_login(null, false);
$PAGE->set_url(new moodle_url('/local/berufsbildung/termin.php', ['id' => $id]));
$PAGE->set_context(context_user::instance((int) $USER->id));

$service = new \local_berufsbildung\service\termin_service();
$ziel = $service->ziel_fuer($id, (int) $USER->id);
if ($ziel !== null) {
    redirect(new moodle_url($ziel));
}

redirect(
    $service->veralteten_termin_bereinigen($id, (int) $USER->id),
    get_string('termin:nicht_mehr_aktuell', 'local_berufsbildung'),
    null,
    \core\output\notification::NOTIFY_INFO
);

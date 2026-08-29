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
 * Installationsschritte.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Legt die Rolle 'berufsbildner' im User-Kontext an, sofern sie nicht
 * existiert. Sie traegt keine eigenen Capabilities - die vergeben die
 * aufsetzenden Plugins.
 */
function xmldb_local_berufsbildung_install() {
    global $DB;

    if (!$DB->record_exists('role', ['shortname' => 'berufsbildner'])) {
        $roleid = create_role(
            get_string('role:berufsbildner', 'local_berufsbildung'),
            'berufsbildner',
            get_string('role:berufsbildner_desc', 'local_berufsbildung')
        );
        set_role_contextlevels($roleid, [CONTEXT_USER]);
    }
}

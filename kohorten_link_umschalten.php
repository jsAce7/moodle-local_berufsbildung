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
 * Kohorten-Verknuepfung aktivieren/deaktivieren. Aendert nie bestehende
 * Zuordnungen - stoppt nur den weiteren Abgleich.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_berufsbildung\persistent\kohorten_link;

require_login();
require_capability('local/berufsbildung:managezuordnung', context_system::instance());
require_sesskey();

$id = required_param('id', PARAM_INT);
$link = new kohorten_link($id);

$link->set('aktiv', !$link->get('aktiv'));
$link->update();

$meldung = $link->get('aktiv')
    ? get_string('kohortenlink:aktivieren', 'local_berufsbildung')
    : get_string('kohortenlink:deaktiviert_hinweis', 'local_berufsbildung');

redirect(
    new moodle_url('/local/berufsbildung/kohorten_links.php'),
    $meldung,
    null,
    \core\output\notification::NOTIFY_SUCCESS
);

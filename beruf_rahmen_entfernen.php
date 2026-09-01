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
 * Entfernt die Kompetenzrahmen-Zuordnung eines Berufs.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_berufsbildung\service\rahmen_resolver;

require_login();
require_capability('local/berufsbildung:manageblocks', context_system::instance());
require_sesskey();

$beruf = required_param('beruf', PARAM_TEXT);

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/berufsbildung/beruf_rahmen_entfernen.php', ['beruf' => $beruf]));

$resolver = new rahmen_resolver();
$konfiguration = get_config('local_berufsbildung', 'beruf_rahmen_mapping');
$paare = $resolver->alle_paare($konfiguration !== false ? (string) $konfiguration : '');

unset($paare[$beruf]);
set_config('beruf_rahmen_mapping', $resolver->serialisiere($paare), 'local_berufsbildung');

redirect(
    new moodle_url('/local/berufsbildung/beruf_rahmen.php'),
    get_string('berufrahmen:entfernt', 'local_berufsbildung'),
    null,
    \core\output\notification::NOTIFY_SUCCESS
);

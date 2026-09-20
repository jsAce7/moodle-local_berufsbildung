<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Ändert die Teilnahmeart einer lernenden Person.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_berufsbildung\api;

require_login();
require_sesskey();
require_capability('local/berufsbildung:managezuordnung', context_system::instance());

$userid = required_param('userid', PARAM_INT);
$art = required_param('art', PARAM_ALPHANUMEXT);
api::set_teilnahmeart($userid, $art);

redirect(
    new moodle_url('/local/berufsbildung/meine_lernenden.php'),
    get_string('teilnahmeart:gespeichert', 'local_berufsbildung'),
    null,
    \core\output\notification::NOTIFY_SUCCESS
);

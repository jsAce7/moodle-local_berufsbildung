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

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        // Auch "Rolle zuruecksetzen" entzieht jede Capability einzeln.
        'eventname' => '\core\event\capability_unassigned',
        'callback' => '\local_berufsbildung\observer::capability_unassigned',
    ],
    [
        // Architekturregel 2: bei einer Account-Loeschung sofort bereinigen,
        // nicht erst ueber einen Loeschantrag in tool_dataprivacy.
        'eventname' => '\core\event\user_deleted',
        'callback' => '\local_berufsbildung\observer::user_deleted',
    ],
    [
        // Beruf, Jahrgang und Lehrbeginn stehen im Profil (api::profil()).
        'eventname' => '\core\event\user_updated',
        'callback' => '\local_berufsbildung\observer::user_updated',
    ],
];

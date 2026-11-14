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
 * Cache-Definitionen.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$definitions = [
    // Ob "Meine Lehre" und "Meine Lernenden" in der Navigation erscheinen,
    // je Person. Die Hooks laufen auf jeder Seite. Zuordnungen und
    // Teilnahmeart leeren den Cache beim Schreiben; was sich ohne
    // Schreibvorgang aendert (Lehrbeginn, Zuordnung beginnt, Profilfeld),
    // holt die Lebensdauer nach - siehe navigation\sichtbarkeit.
    'navigation' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => true,
        'staticacceleration' => true,
        'staticaccelerationsize' => 30,
        'ttl' => 300,
    ],
    // Profilfelder (Beruf, Jahrgang, Lehrbeginn) je Person, nur fuer die
    // Dauer einer Anfrage und nur auf Seiten, die ihn einschalten - siehe
    // api::profile_vorhalten().
    'profil' => [
        'mode' => cache_store::MODE_REQUEST,
        'simplekeys' => true,
    ],
    // Bezeichnungen der Kompetenzen im Raster je Anfrage, siehe
    // output\kompetenzraster::lade_kompetenzen().
    'kompetenzen' => [
        'mode' => cache_store::MODE_REQUEST,
        'simplekeys' => true,
        'simpledata' => true,
    ],
];

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
 * Webservice-Funktionen und Dienst-Definition.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_berufsbildung_import_versetzungsplan' => [
        'classname' => 'local_berufsbildung\external\import_versetzungsplan',
        'methodname' => 'execute',
        'description' => 'Importiert eine Versetzungsplan-Lieferung. Schnittstelle: '
            . 'https://github.com/jsAce7/moodle-local_berufsbildung/blob/main/docs/schnittstelle_versetzungsplan.md',
        'type' => 'write',
        'capabilities' => 'local/berufsbildung:importplan',
        'ajax' => false,
    ],
    // Fuer das Feld "Fachvorgesetzte" im Blockformular (amd/src/fachvorgesetzte_auswahl.js).
    'local_berufsbildung_fachvorgesetzte_suchen' => [
        'classname' => 'local_berufsbildung\external\fachvorgesetzte_suchen',
        'methodname' => 'execute',
        'description' => 'Sucht Personen fuer die Fachvorgesetzten eines Ausbildungsblocks.',
        'type' => 'read',
        'capabilities' => 'local/berufsbildung:manageblocks',
        'ajax' => true,
    ],
];

// Eigener Dienst statt Anhaengen an einen bestehenden - nur das
// Dienstkonto des Versetzungsplan-Skripts bekommt hierfuer ein Token,
// nicht jeder mit einem allgemeinen Webservice-Zugang.
$services = [
    'Berufsbildung: Versetzungsplan-Import' => [
        'functions' => ['local_berufsbildung_import_versetzungsplan'],
        'restrictedusers' => 1,
        'enabled' => 0,
        'shortname' => 'local_berufsbildung_versetzungsplan',
    ],
];

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
 * Formular zum Hochladen der Zuordnungs-CSV.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');
require_once($CFG->libdir . '/csvlib.class.php');

use core_text;
use csv_import_reader;

/**
 * Formular fuer den CSV-Import von Zuordnungen.
 */
class zuordnung_import_form extends \moodleform {
    /**
     * Baut das Formular auf.
     *
     */
    protected function definition(): void {
        $mform = $this->_form;

        $mform->addElement('static', 'beschreibung', '', get_string('import:beschreibung', 'local_berufsbildung'));

        $mform->addElement(
            'filepicker',
            'csvfile',
            get_string('import:datei', 'local_berufsbildung'),
            null,
            ['accepted_types' => ['.csv']]
        );
        $mform->addRule('csvfile', null, 'required');

        $delimiters = csv_import_reader::get_delimiter_list();
        $mform->addElement('select', 'delimiter', get_string('import:trennzeichen', 'local_berufsbildung'), $delimiters);
        $mform->setDefault('delimiter', get_string('listsep', 'langconfig') === ';' ? 'semicolon' : 'comma');

        $encodings = core_text::get_encodings();
        $mform->addElement('select', 'encoding', get_string('import:kodierung', 'local_berufsbildung'), $encodings);
        $mform->setDefault('encoding', 'UTF-8');

        $this->add_action_buttons(true, get_string('import:vorschau_anzeigen', 'local_berufsbildung'));
    }
}

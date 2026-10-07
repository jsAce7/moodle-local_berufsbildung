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
 * Formular fuer den manuellen Versetzungsplan-Upload (Rueckfallweg zum
 * Webservice).
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Formular fuer den Versetzungsplan-Import.
 */
class plan_import_form extends \moodleform {
    /**
     * Baut das Formular auf.
     *
     */
    protected function definition(): void {
        $mform = $this->_form;

        $mform->addElement('static', 'beschreibung', '', get_string('planimport:beschreibung', 'local_berufsbildung'));

        $mform->addElement('filepicker', 'csvdatei', get_string('planimport:datei', 'local_berufsbildung'), null, [
            'accepted_types' => ['.csv'],
        ]);
        $mform->addRule('csvdatei', null, 'required');

        $mform->addElement('advcheckbox', 'testlauf', get_string('planimport:testlauf', 'local_berufsbildung'));
        $mform->setDefault('testlauf', 1);

        $mform->addElement(
            'advcheckbox',
            'rueckgang_bestaetigt',
            get_string('planimport:rueckgang_bestaetigt', 'local_berufsbildung')
        );
        $mform->setDefault('rueckgang_bestaetigt', 0);

        $this->add_action_buttons(false, get_string('planimport:hochladen', 'local_berufsbildung'));
    }
}

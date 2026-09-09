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
 * Formular zum Zuordnen eines Kompetenzrahmens zu einem Beruf.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class beruf_rahmen_form extends \moodleform {

    protected function definition(): void {
        $mform = $this->_form;

        $rahmenoptionen = $this->_customdata['rahmenoptionen'] ?? [];

        // Auswahlliste statt Freitext: der Code muss zeichengenau dem
        // Profilwert entsprechen, sonst greift die Zuordnung nie.
        $berufe = $this->_customdata['berufe'] ?? [];
        $berufauswahl = ['' => get_string('choosedots')] + array_combine($berufe, $berufe);

        $mform->addElement('select', 'beruf', get_string('berufrahmen:beruf', 'local_berufsbildung'), $berufauswahl);
        $mform->setType('beruf', PARAM_TEXT);
        $mform->addRule('beruf', null, 'required');
        $mform->addHelpButton('beruf', 'berufrahmen_beruf', 'local_berufsbildung');

        $mform->addElement('select', 'rahmenidnumber', get_string('berufrahmen:rahmen', 'local_berufsbildung'), $rahmenoptionen);
        $mform->setType('rahmenidnumber', PARAM_TEXT);
        $mform->addRule('rahmenidnumber', null, 'required');

        $this->add_action_buttons(false, get_string('berufrahmen:hinzufuegen', 'local_berufsbildung'));
    }

    /**
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        $bestehende = $this->_customdata['bestehende_berufe'] ?? [];
        if (!empty($data['beruf']) && in_array($data['beruf'], $bestehende, true)) {
            $errors['beruf'] = get_string('berufrahmen:fehler_existiert', 'local_berufsbildung');
        }

        return $errors;
    }
}

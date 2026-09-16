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
 * Formular zum Anlegen/Bearbeiten einer Aufbewahrungspflicht.
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
 * Formular fuer eine dokumentierte Aufbewahrungspflicht.
 */
class aufbewahrung_form extends \moodleform {
    /**
     * Baut das Formular auf.
     *
     */
    protected function definition(): void {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);

        $mform->addElement(
            'autocomplete',
            'lernendeid',
            get_string('aufbewahrung:lernende', 'local_berufsbildung'),
            [],
            ['ajax' => 'core_user/form_user_selector', 'multiple' => false]
        );
        $mform->addRule('lernendeid', null, 'required');
        $mform->setType('lernendeid', PARAM_INT);

        $mform->addElement('textarea', 'grund', get_string('aufbewahrung:grund', 'local_berufsbildung'));
        $mform->setType('grund', PARAM_RAW);
        $mform->addRule('grund', null, 'required');

        $mform->addElement('date_selector', 'gueltig_von', get_string('aufbewahrung:gueltig_von', 'local_berufsbildung'));
        $mform->setDefault('gueltig_von', time());

        // Optional: unbefristet, bis auf Widerruf, wenn leer gelassen.
        $mform->addElement(
            'date_selector',
            'gueltig_bis',
            get_string('aufbewahrung:gueltig_bis', 'local_berufsbildung'),
            ['optional' => true]
        );

        $this->add_action_buttons(true, get_string('aufbewahrung:speichern', 'local_berufsbildung'));
    }

    /**
     * Prueft die Eingaben und meldet Fehler je Feld.
     *
     * @param mixed $data
     * @param mixed $files
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if (!empty($data['gueltig_bis']) && (int) $data['gueltig_bis'] < (int) $data['gueltig_von']) {
            $errors['gueltig_bis'] = get_string('zuordnung:fehler_enddatum', 'local_berufsbildung');
        }
        return $errors;
    }
}

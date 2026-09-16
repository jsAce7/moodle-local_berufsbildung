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
 * Formular zum Anlegen/Bearbeiten eines Ausbildungsblocks.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

use local_berufsbildung\persistent\block;

/**
 * Formular fuer einen Ausbildungsblock.
 */
class block_form extends \moodleform {
    /**
     * Baut das Formular auf.
     *
     */
    protected function definition(): void {
        $mform = $this->_form;

        $mform->addElement('text', 'nummer', get_string('block:nummer', 'local_berufsbildung'));
        $mform->setType('nummer', PARAM_TEXT);
        $mform->addRule('nummer', null, 'required');

        $mform->addElement('text', 'name', get_string('block:name', 'local_berufsbildung'));
        $mform->setType('name', PARAM_TEXT);

        $berufe = $this->_customdata['berufe'] ?? [];
        $berufauswahl = ['' => get_string('block:beruf_leer', 'local_berufsbildung')] + array_combine($berufe, $berufe);
        $mform->addElement('select', 'beruf', get_string('block:beruf', 'local_berufsbildung'), $berufauswahl);
        $mform->setType('beruf', PARAM_TEXT);
        $mform->addHelpButton('beruf', 'block_beruf', 'local_berufsbildung');

        $mform->addElement('advcheckbox', 'ist_betrieb', get_string('block:ist_betrieb', 'local_berufsbildung'));
        $mform->addHelpButton('ist_betrieb', 'block_ist_betrieb', 'local_berufsbildung');
        $mform->setDefault('ist_betrieb', 1);

        $mform->addElement('advcheckbox', 'aktiv', get_string('block:aktiv', 'local_berufsbildung'));
        $mform->setDefault('aktiv', 1);

        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);

        $this->add_action_buttons(true, get_string('block:speichern', 'local_berufsbildung'));
    }

    /**
     * Prueft die Eingaben und meldet Fehler je Feld.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        if (!empty($data['nummer'])) {
            $bestehender = block::get_record(['nummer' => $data['nummer']]);
            if ($bestehender && (int) $bestehender->get('id') !== (int) $data['id']) {
                $errors['nummer'] = get_string('block:fehler_nummer_existiert', 'local_berufsbildung');
            }
        }

        return $errors;
    }
}

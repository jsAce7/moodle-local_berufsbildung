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
 * Formular zum Anlegen einer Kohorten-Verknuepfung.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

use local_berufsbildung\persistent\kohorten_link;

class kohorten_link_form extends \moodleform {

    protected function definition(): void {
        $mform = $this->_form;

        $mform->addElement('static', 'beschreibung', '', get_string('kohortenlink:beschreibung', 'local_berufsbildung'));

        $cohorts = $this->_customdata['cohorts'] ?? [];
        $mform->addElement('select', 'cohortid', get_string('kohortenlink:kohorte', 'local_berufsbildung'), $cohorts);
        $mform->setType('cohortid', PARAM_INT);

        $mform->addElement(
            'autocomplete',
            'berufsbildnerid',
            get_string('zuordnung:berufsbildner', 'local_berufsbildung'),
            [],
            ['ajax' => 'core_user/form_user_selector', 'multiple' => false]
        );
        $mform->addRule('berufsbildnerid', null, 'required');
        $mform->setType('berufsbildnerid', PARAM_INT);

        $mform->addElement('text', 'rolle', get_string('zuordnung:rolle', 'local_berufsbildung'));
        $mform->setType('rolle', PARAM_ALPHA);
        $mform->setDefault('rolle', 'hauptverantwortlich');
        $mform->addRule('rolle', null, 'required');
        $mform->addHelpButton('rolle', 'zuordnung_rolle', 'local_berufsbildung');

        $mform->addElement('text', 'beruf', get_string('zuordnung:beruf', 'local_berufsbildung'));
        $mform->setType('beruf', PARAM_ALPHANUMEXT);
        $mform->addHelpButton('beruf', 'zuordnung_beruf', 'local_berufsbildung');

        $this->add_action_buttons(true, get_string('kohortenlink:anlegen', 'local_berufsbildung'));
    }

    /**
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        if (!empty($data['cohortid']) && !empty($data['berufsbildnerid']) && !empty($data['rolle'])) {
            $existiert = kohorten_link::record_exists_select(
                'cohortid = :cohortid AND berufsbildnerid = :berufsbildnerid AND rolle = :rolle',
                [
                    'cohortid' => (int) $data['cohortid'],
                    'berufsbildnerid' => (int) $data['berufsbildnerid'],
                    'rolle' => $data['rolle'],
                ]
            );

            if ($existiert) {
                $errors['berufsbildnerid'] = get_string('kohortenlink:fehler_existiert', 'local_berufsbildung');
            }
        }

        return $errors;
    }
}

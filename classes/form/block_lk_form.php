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
 * Formular zum Zuordnen einer Lern- oder Handlungskompetenz (LK) zu einem
 * Ausbildungsblock.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

use local_berufsbildung\persistent\block_lk;

class block_lk_form extends \moodleform {

    protected function definition(): void {
        $mform = $this->_form;

        $blockid = (int) $this->_customdata['blockid'];
        $kompetenzen = $this->_customdata['kompetenzen'] ?? [];

        $mform->addElement('select', 'competencyid', get_string('blocklk:kompetenz', 'local_berufsbildung'), $kompetenzen);
        $mform->setType('competencyid', PARAM_INT);
        $mform->addRule('competencyid', null, 'required');

        $mform->addElement('select', 'intensitaet', get_string('blocklk:intensitaet', 'local_berufsbildung'), [
            'schwerpunkt' => get_string('blocklk:intensitaet_schwerpunkt', 'local_berufsbildung'),
            'teilweise' => get_string('blocklk:intensitaet_teilweise', 'local_berufsbildung'),
        ]);
        $mform->setType('intensitaet', PARAM_ALPHA);

        // Der Feldname 'id' (nicht 'blockid') ist Absicht: die aufrufende
        // Seite liest required_param('id', ...), das bei einem POST ohne
        // Querystring in der Formular-Action nur ueber ein POST-Feld
        // dieses Namens ankommt - siehe zuordnung_beenden.php fuer das
        // gleiche Muster.
        $mform->addElement('hidden', 'id', $blockid);
        $mform->setType('id', PARAM_INT);

        $this->add_action_buttons(false, get_string('blocklk:hinzufuegen', 'local_berufsbildung'));
    }

    /**
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        if (!empty($data['competencyid'])) {
            $existiert = block_lk::record_exists_select(
                'blockid = :blockid AND competencyid = :competencyid',
                ['blockid' => (int) $data['id'], 'competencyid' => (int) $data['competencyid']]
            );

            if ($existiert) {
                $errors['competencyid'] = get_string('blocklk:fehler_existiert', 'local_berufsbildung');
            }
        }

        return $errors;
    }
}

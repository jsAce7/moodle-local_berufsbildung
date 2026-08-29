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
 * Formular zum Beenden einer bestehenden Zuordnung.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class zuordnung_beenden_form extends \moodleform {

    protected function definition(): void {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        // Optional: leer gelassen (Haekchen aus) macht eine beendete
        // Zuordnung wieder laufend, statt sie zwingend zu beenden.
        $mform->addElement(
            'date_selector',
            'gueltig_bis',
            get_string('zuordnung:gueltig_bis', 'local_berufsbildung'),
            ['optional' => true]
        );
        $mform->addHelpButton('gueltig_bis', 'zuordnung_gueltig_bis', 'local_berufsbildung');

        $this->add_action_buttons(true, get_string('zuordnung:speichern', 'local_berufsbildung'));
    }
}

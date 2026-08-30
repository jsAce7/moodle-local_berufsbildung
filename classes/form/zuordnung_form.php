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
 * Formular zum Anlegen einer oder mehrerer Zuordnungen.
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
 * Legt bewusst kein core\form\persistent an: das Speichern laeuft ueber
 * api::set_zuordnung() je Lernende/n (eine Zuordnung pro Person, nie eine
 * Sammelzuordnung), das eine bestehende laufende Zuordnung automatisch
 * beendet - diese Logik gehoert in die API, nicht ins Formular.
 *
 * Die Kohorten-Optionen werden nicht hier, sondern in der aufrufenden Seite
 * geladen und als customdata['cohorts'] hereingereicht - das Formular
 * fasst dafuer weder core-Kohorten-Funktionen noch $DB an.
 */
class zuordnung_form extends \moodleform {

    protected function definition(): void {
        $mform = $this->_form;

        $mform->addElement(
            'autocomplete',
            'berufsbildnerid',
            get_string('zuordnung:berufsbildner', 'local_berufsbildung'),
            [],
            ['ajax' => 'core_user/form_user_selector', 'multiple' => false]
        );
        $mform->addRule('berufsbildnerid', null, 'required');
        $mform->setType('berufsbildnerid', PARAM_INT);

        // Mehrfachauswahl: kein setType() hier - PARAM_*-Typen sind fuer
        // Skalare gedacht, moodleform behandelt Array-Felder eines
        // 'multiple'-Autocomplete bereits selbst (siehe
        // admin/tool/cohortroles/classes/form/assign_role_cohort.php).
        $mform->addElement(
            'autocomplete',
            'lernendeids',
            get_string('zuordnung:lernende', 'local_berufsbildung'),
            [],
            ['ajax' => 'core_user/form_user_selector', 'multiple' => true]
        );
        $mform->addHelpButton('lernendeids', 'zuordnung_lernende', 'local_berufsbildung');

        $cohorts = $this->_customdata['cohorts'] ?? [];
        if (!empty($cohorts)) {
            $mform->addElement(
                'autocomplete',
                'cohortids',
                get_string('zuordnung:kohorten', 'local_berufsbildung'),
                $cohorts,
                ['multiple' => true]
            );
            $mform->addHelpButton('cohortids', 'zuordnung_kohorten', 'local_berufsbildung');
        }

        $mform->addElement('text', 'beruf', get_string('zuordnung:beruf', 'local_berufsbildung'));
        $mform->setType('beruf', PARAM_TEXT);
        $mform->addHelpButton('beruf', 'zuordnung_beruf', 'local_berufsbildung');

        // Feste Auswahl der heute tatsaechlich genutzten Rollen - das Feld
        // selbst bleibt bewusst Freitext ohne 'choices'-Einschraenkung
        // (siehe CLAUDE.md Architekturregel 4: "wird abgefragt, nicht
        // angenommen"), nur die Formularoberflaeche schraenkt auf die
        // aktuell bekannten Werte ein.
        $mform->addElement('select', 'rolle', get_string('zuordnung:rolle', 'local_berufsbildung'), [
            'hauptverantwortlich' => get_string('zuordnung:rolle_hauptverantwortlich', 'local_berufsbildung'),
            'stellvertretung' => get_string('zuordnung:rolle_stellvertretung', 'local_berufsbildung'),
        ]);
        $mform->setType('rolle', PARAM_ALPHA);
        $mform->setDefault('rolle', 'hauptverantwortlich');
        $mform->addHelpButton('rolle', 'zuordnung_rolle', 'local_berufsbildung');

        $mform->addElement('date_selector', 'gueltig_von', get_string('zuordnung:gueltig_von', 'local_berufsbildung'));
        $mform->setDefault('gueltig_von', time());

        $this->add_action_buttons(true, get_string('zuordnung:anlegen', 'local_berufsbildung'));
    }

    /**
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        $lernendeids = !empty($data['lernendeids']) ? array_map('intval', $data['lernendeids']) : [];
        $cohortids = !empty($data['cohortids']) ? array_map('intval', $data['cohortids']) : [];

        if (empty($lernendeids) && empty($cohortids)) {
            $errors['lernendeids'] = get_string('zuordnung:fehler_keine_lernenden', 'local_berufsbildung');
        }

        if (!empty($data['berufsbildnerid']) && in_array((int) $data['berufsbildnerid'], $lernendeids, true)) {
            $errors['lernendeids'] = get_string('zuordnung:fehler_gleiche_person', 'local_berufsbildung');
        }

        return $errors;
    }
}

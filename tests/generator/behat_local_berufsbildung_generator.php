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
 * Behat-Generator fuer local_berufsbildung.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

/**
 * Testdaten fuer Behat: Ausbildungsblöcke, deren Kompetenzzuordnung, ein
 * gegliederter Kompetenzrahmen sowie Lernende mit ihren Einsaetzen.
 *
 * Aufruf z.B. mit 'the following "local_berufsbildung > blocks" exist:'.
 */
class behat_local_berufsbildung_generator extends behat_generator_base {
    /**
     * Die Entitaeten, die dieser Generator anlegen kann.
     *
     * @return array
     */
    protected function get_creatable_entities(): array {
        return [
            // Der Generator von core_competency kennt keine Elternkompetenz.
            // Die Kompetenzauswahl braucht aber die Gliederung Bereich -
            // Handlungskompetenz - Leistungskriterium.
            'competencies' => [
                'singular' => 'competency',
                'datagenerator' => 'competency',
                'required' => ['shortname', 'framework'],
                'switchids' => ['framework' => 'competencyframeworkid', 'parent' => 'parentid'],
            ],
            'blocks' => [
                'singular' => 'block',
                'datagenerator' => 'block',
                'required' => ['nummer'],
            ],
            'block competencies' => [
                'singular' => 'block_competency',
                'datagenerator' => 'block_competency',
                'required' => ['block', 'competency'],
                'switchids' => ['block' => 'blockid', 'competency' => 'competencyid'],
            ],
            // Mit Beruf und Jahrgang im Profil, damit sich ihr
            // Ausbildungsstand aufloesen laesst.
            'learners' => [
                'singular' => 'learner',
                'datagenerator' => 'lernende',
                'required' => ['username'],
            ],
            'placements' => [
                'singular' => 'placement',
                'datagenerator' => 'einsatz',
                'required' => ['user', 'block', 'von', 'bis'],
                'switchids' => ['user' => 'userid', 'block' => 'blockid'],
            ],
            // Zuordnung Berufsbildner/in - Lernende/r, laufend ab einem Jahr
            // zurueck, sofern nichts anderes angegeben ist.
            'assignments' => [
                'singular' => 'assignment',
                'datagenerator' => 'zuordnung',
                'required' => ['trainer', 'learner'],
                'switchids' => ['trainer' => 'berufsbildnerid', 'learner' => 'lernendeid'],
            ],
        ];
    }

    /**
     * ID eines Kompetenzrahmens aus seiner ID-Nummer.
     *
     * @param string $idnumber
     * @return int
     */
    protected function get_framework_id(string $idnumber): int {
        global $DB;

        return (int) $DB->get_field('competency_framework', 'id', ['idnumber' => $idnumber], MUST_EXIST);
    }

    /**
     * ID einer Kompetenz aus ihrer ID-Nummer.
     *
     * @param string $idnumber
     * @return int
     */
    protected function get_competency_id(string $idnumber): int {
        global $DB;

        return (int) $DB->get_field('competency', 'id', ['idnumber' => $idnumber], MUST_EXIST);
    }

    /**
     * ID der Elternkompetenz aus ihrer ID-Nummer; leer fuer einen
     * Handlungskompetenzbereich zuoberst im Rahmen.
     *
     * @param string $idnumber
     * @return int
     */
    protected function get_parent_id(string $idnumber): int {
        return $idnumber === '' ? 0 : $this->get_competency_id($idnumber);
    }

    /**
     * ID der Berufsbildner/in aus dem Benutzernamen.
     *
     * @param string $username
     * @return int
     */
    protected function get_trainer_id(string $username): int {
        return (int) $this->get_user_id($username);
    }

    /**
     * ID der lernenden Person aus dem Benutzernamen.
     *
     * @param string $username
     * @return int
     */
    protected function get_learner_id(string $username): int {
        return (int) $this->get_user_id($username);
    }

    /**
     * ID eines Ausbildungsblocks aus seiner Nummer.
     *
     * @param string $nummer
     * @return int
     */
    protected function get_block_id(string $nummer): int {
        $block = \local_berufsbildung\persistent\block::get_record(['nummer' => $nummer]);
        if (!$block) {
            throw new Exception('Der Ausbildungsblock "' . $nummer . '" existiert nicht.');
        }

        return (int) $block->get('id');
    }

    /**
     * Passwort wie beim Behat-Generator fuer "users": gleich dem
     * Benutzernamen, damit "I log in as" funktioniert.
     *
     * @param array $data
     * @return array
     */
    protected function preprocess_lernende(array $data): array {
        $data += ['password' => $data['username']];

        return $data;
    }

    /**
     * Legt eine Kompetenz ueber den Generator von core_competency an.
     *
     * @param array $data
     */
    protected function process_competency(array $data): void {
        $data += ['descriptionformat' => FORMAT_HTML];
        testing_util::get_data_generator()->get_plugin_generator('core_competency')->create_competency($data);
    }
}

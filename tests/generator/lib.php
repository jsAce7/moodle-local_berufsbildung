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
 * PHPUnit-Testdatengenerator.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Erzeugt Testdaten fuer local_berufsbildung, aufrufbar ueber
 * $this->getDataGenerator()->get_plugin_generator('local_berufsbildung').
 */
class local_berufsbildung_generator extends component_generator_base {
    /**
     * Legt eine Zuordnung an.
     *
     * @param array $record Muss berufsbildnerid und lernendeid enthalten.
     *                       gueltig_von, beruf und rolle haben Defaults.
     * @return \local_berufsbildung\persistent\zuordnung
     */
    public function create_zuordnung(array $record = []): \local_berufsbildung\persistent\zuordnung {
        if (empty($record['berufsbildnerid'])) {
            throw new coding_exception('create_zuordnung() benoetigt berufsbildnerid');
        }
        if (empty($record['lernendeid'])) {
            throw new coding_exception('create_zuordnung() benoetigt lernendeid');
        }

        $record += [
            'beruf' => 'AU_EFZ',
            'rolle' => 'hauptverantwortlich',
            'gueltig_von' => time() - YEARSECS,
            'gueltig_bis' => null,
        ];

        $zuordnung = new \local_berufsbildung\persistent\zuordnung(0, (object) $record);
        $zuordnung->create();

        return $zuordnung;
    }

    /**
     * Legt einen Testuser mit auflösbarem Ausbildungsstand an - d.h. mit
     * den Profilfeldern beruf/jahrgang befuellt, den get_ausbildungsstand()/
     * get_ausbildungsende() auch tatsaechlich etwas zurueckgeben. Legt die
     * Profilfelder selbst an, falls sie in diesem Testlauf noch nicht
     * existieren.
     *
     * @param array $record 'beruf' und 'jahrgang' ueberschreibbar, alle
     *                       weiteren Schluessel gehen unveraendert an
     *                       create_user() durch.
     * @return stdClass
     */
    public function create_lernende(array $record = []): stdClass {
        global $DB;

        if (!$DB->record_exists('user_info_field', ['shortname' => 'beruf'])) {
            $this->datagenerator->create_custom_profile_field([
                'datatype' => 'text',
                'shortname' => 'beruf',
                'name' => 'Beruf',
            ]);
        }
        if (!$DB->record_exists('user_info_field', ['shortname' => 'jahrgang'])) {
            $this->datagenerator->create_custom_profile_field([
                'datatype' => 'text',
                'shortname' => 'jahrgang',
                'name' => 'Jahrgang',
            ]);
        }

        $beruf = $record['beruf'] ?? 'AU_EFZ';
        $jahrgang = $record['jahrgang'] ?? (string) date('Y');
        unset($record['beruf'], $record['jahrgang']);

        $record += [
            'profile_field_beruf' => $beruf,
            'profile_field_jahrgang' => (string) $jahrgang,
        ];

        return $this->datagenerator->create_user($record);
    }

    /**
     * Legt einen Ausbildungsblock an.
     *
     * @param array $record Muss nummer enthalten; die uebrigen Felder haben
     *                       die Defaults des Persistent.
     * @return \local_berufsbildung\persistent\block
     */
    public function create_block(array $record): \local_berufsbildung\persistent\block {
        if (empty($record['nummer'])) {
            throw new coding_exception('create_block() benoetigt nummer');
        }

        $block = new \local_berufsbildung\persistent\block(0, (object) $record);
        $block->create();

        return $block;
    }

    /**
     * Ordnet einem Ausbildungsblock eine Kompetenz zu.
     *
     * @param array $record Muss blockid und competencyid enthalten.
     * @return \local_berufsbildung\persistent\block_lk
     */
    public function create_block_competency(array $record): \local_berufsbildung\persistent\block_lk {
        if (empty($record['blockid']) || empty($record['competencyid'])) {
            throw new coding_exception('create_block_competency() benoetigt blockid und competencyid');
        }

        $abdeckung = new \local_berufsbildung\persistent\block_lk(0, (object) [
            'blockid' => $record['blockid'],
            'competencyid' => $record['competencyid'],
        ]);
        $abdeckung->create();

        return $abdeckung;
    }

    /**
     * Legt einen Einsatz aus dem Versetzungsplan an.
     *
     * @param array $record Muss userid, blockid, von und bis enthalten.
     * @return \local_berufsbildung\persistent\einsatz
     */
    public function create_einsatz(array $record): \local_berufsbildung\persistent\einsatz {
        foreach (['userid', 'blockid', 'von', 'bis'] as $feld) {
            if (empty($record[$feld])) {
                throw new coding_exception('create_einsatz() benoetigt ' . $feld);
            }
        }

        $record += [
            'kw_von' => date('o-\\WW', (int) $record['von']),
            'kw_bis' => date('o-\\WW', (int) $record['bis']),
            'importid' => 0,
        ];

        $einsatz = new \local_berufsbildung\persistent\einsatz(0, (object) $record);
        $einsatz->create();

        return $einsatz;
    }
}

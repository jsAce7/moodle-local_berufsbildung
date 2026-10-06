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
 * Abfrage der Einsaetze und ihrer Kompetenzabdeckung. Reine Lesezugriffe -
 * das Schreiben uebernimmt import_service.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\versetzungsplan;

use local_berufsbildung\persistent\block;
use local_berufsbildung\persistent\block_lk;
use local_berufsbildung\persistent\einsatz;
use core_competency\competency;

/**
 * Liest den importierten Versetzungsplan aus.
 */
class plan_service {
    /**
     * Einsaetze einer lernenden Person, optional auf einen Zeitraum
     * eingeschraenkt. Einsaetze, die sich nur teilweise mit dem Zeitraum
     * ueberschneiden, zaehlen mit - ein Block, der Mitte Januar endet,
     * gehoert zum Semester, das am 31. Januar endet.
     *
     * @param int $lernendeid
     * @param int|null $von Timestamp, null = kein unterer Rand
     * @param int|null $bis Timestamp, null = kein oberer Rand
     * @return einsatz[]
     */
    public function get_einsaetze(int $lernendeid, ?int $von = null, ?int $bis = null): array {
        $bedingungen = ['userid = :userid'];
        $parameter = ['userid' => $lernendeid];

        if ($von !== null) {
            $bedingungen[] = 'bis >= :von';
            $parameter['von'] = $von;
        }
        if ($bis !== null) {
            $bedingungen[] = 'von <= :bis';
            $parameter['bis'] = $bis;
        }

        return einsatz::get_records_select(implode(' AND ', $bedingungen), $parameter, 'von ASC');
    }

    /**
     * Vereinigung der Kompetenzen aller betrieblichen Einsaetze im
     * Zeitraum (Architekturregel 6: Vorschlag, keine Festlegung). Blöcke
     * ohne Kompetenzabdeckung - z.B. weil ihre Nummer beim Import unbekannt
     * war - tragen einfach nichts bei.
     *
     * @param int $lernendeid
     * @param int $von Timestamp
     * @param int $bis Timestamp
     * @return int[] Deduplizierte competencyids
     */
    public function get_ausgebildete_kompetenzen(int $lernendeid, int $von, int $bis): array {
        $kompetenzen = [];
        foreach ($this->get_zugeordnete_kompetenzen($lernendeid, $von, $bis) as $competencyid) {
            // Ein Ausbildungsblock wird auf Ebene LK gepflegt. Fuer die
            // Ausbildungsplanung zählt diese LK zugleich für alle ihre
            // übergeordneten Handlungskompetenzen.
            foreach ($this->mit_uebergeordneten_kompetenzen($competencyid) as $kompetenzid) {
                $kompetenzen[$kompetenzid] = true;
            }
        }

        return array_keys($kompetenzen);
    }

    /**
     * Die Kompetenzen, die den betrieblichen Bloecken der Einsaetze im
     * Zeitraum zugeordnet sind - so, wie sie zugeordnet sind, ohne die
     * uebergeordneten. Erst damit laesst sich sagen, welche
     * Leistungskriterien einer Handlungskompetenz vorkommen: ueber
     * get_ausgebildete_kompetenzen() steht die HK schon da, sobald eines
     * ihrer LK vorkommt.
     *
     * @param int $lernendeid
     * @param int $von Timestamp
     * @param int $bis Timestamp
     * @return int[] Deduplizierte competencyids
     */
    public function get_zugeordnete_kompetenzen(int $lernendeid, int $von, int $bis): array {
        $einsaetze = $this->get_einsaetze($lernendeid, $von, $bis);
        if (empty($einsaetze)) {
            return [];
        }

        $blockids = array_values(array_unique(array_map(
            static fn (einsatz $einsatz): int => (int) $einsatz->get('blockid'),
            $einsaetze
        )));

        $kompetenzen = [];
        foreach ($blockids as $blockid) {
            $block = block::get_record(['id' => $blockid]);
            if (!$block || !$block->get('ist_betrieb')) {
                continue;
            }

            foreach (block_lk::get_records(['blockid' => $blockid]) as $abdeckung) {
                $kompetenzen[(int) $abdeckung->get('competencyid')] = true;
            }
        }

        return array_keys($kompetenzen);
    }

    /**
     * Ergaenzt die uebergeordneten Kompetenzen.
     *
     * @param int $kompetenzid
     * @return int[] Kompetenz selbst, gefolgt von ihren Vorfahren
     */
    private function mit_uebergeordneten_kompetenzen(int $kompetenzid): array {
        $ids = [$kompetenzid];
        $kompetenz = competency::get_record(['id' => $kompetenzid]);

        // Bei alten oder extern gelöschten Referenzen bleibt die direkte
        // Zuordnung sichtbar; sie darf nicht den übrigen Plan blockieren.
        while ($kompetenz !== false && (int) $kompetenz->get('parentid') !== 0) {
            $elternid = (int) $kompetenz->get('parentid');
            $ids[] = $elternid;
            $kompetenz = competency::get_record(['id' => $elternid]);
        }

        return $ids;
    }

    /**
     * Der Einsatz, in dem sich die lernende Person gerade befindet.
     *
     * @param int $lernendeid
     * @return einsatz|null
     */
    public function get_aktueller_einsatz(int $lernendeid): ?einsatz {
        $jetzt = time();

        $treffer = einsatz::get_records_select(
            'userid = :userid AND von <= :jetzt1 AND bis >= :jetzt2',
            ['userid' => $lernendeid, 'jetzt1' => $jetzt, 'jetzt2' => $jetzt],
            'von DESC',
            '*',
            0,
            1
        );

        return !empty($treffer) ? reset($treffer) : null;
    }
}

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
 * Uebernimmt die Kompetenzzuordnung eines Blocks in einen anderen.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\service;

use core_competency\competency;
use core_competency\competency_framework;
use local_berufsbildung\api;
use local_berufsbildung\persistent\block;
use local_berufsbildung\persistent\block_lk;

/**
 * Fuer "Block kopieren": ein neuer Block mit aehnlichem Inhalt soll nicht
 * bei null anfangen. Uebernommen wird nur, was im Rahmen des Berufs des
 * Zielblocks steht - eine Kopie fuer einen anderen Beruf mit eigenem
 * Rahmen bekaeme sonst Kompetenzen, die die Kompetenzauswahl dort gar
 * nicht anbietet und das Raster nie zeigt.
 */
class block_kopie_service {
    /**
     * Uebernimmt die Kompetenzen von $quelle in $ziel.
     *
     * Was $ziel schon hat, wird nicht doppelt angelegt.
     *
     * @param block $quelle
     * @param block $ziel
     * @return array{uebernommen: int, verworfen: int} Verworfen: nicht im Rahmen des Zielberufs
     */
    public function uebernimm_kompetenzen(block $quelle, block $ziel): array {
        $competencyids = [];
        foreach (block_lk::get_records(['blockid' => (int) $quelle->get('id')], 'id') as $abdeckung) {
            $competencyids[] = (int) $abdeckung->get('competencyid');
        }
        if (empty($competencyids)) {
            return ['uebernommen' => 0, 'verworfen' => 0];
        }

        $imrahmen = $this->im_rahmen($competencyids, (string) $ziel->get('beruf'));

        $vorhanden = [];
        foreach (block_lk::get_records(['blockid' => (int) $ziel->get('id')]) as $abdeckung) {
            $vorhanden[(int) $abdeckung->get('competencyid')] = true;
        }

        $uebernommen = 0;
        foreach ($imrahmen as $competencyid) {
            if (isset($vorhanden[$competencyid])) {
                continue;
            }
            (new block_lk(0, (object) [
                'blockid' => (int) $ziel->get('id'),
                'competencyid' => $competencyid,
            ]))->create();
            $uebernommen++;
        }

        return ['uebernommen' => $uebernommen, 'verworfen' => count($competencyids) - count($imrahmen)];
    }

    /**
     * Die Kompetenzen, die im Rahmen eines Berufs stehen.
     *
     * @param int[] $competencyids
     * @param string $beruf
     * @return int[] In der Reihenfolge von $competencyids
     */
    private function im_rahmen(array $competencyids, string $beruf): array {
        global $DB;

        $frameworkidnumber = $beruf !== '' ? api::get_kompetenzrahmen_for_beruf($beruf) : null;
        $framework = $frameworkidnumber !== null
            ? competency_framework::get_record(['idnumber' => $frameworkidnumber])
            : false;
        if (!$framework) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal($competencyids, SQL_PARAMS_NAMED);
        $params['frameworkid'] = (int) $framework->get('id');
        $gueltig = [];
        foreach (competency::get_records_select("id {$insql} AND competencyframeworkid = :frameworkid", $params) as $kompetenz) {
            $gueltig[(int) $kompetenz->get('id')] = true;
        }

        return array_values(array_filter(
            $competencyids,
            static fn (int $competencyid): bool => isset($gueltig[$competencyid])
        ));
    }
}

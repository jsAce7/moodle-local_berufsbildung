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
 * Loescht einen Ausbildungsblock samt Kompetenzzuordnung und geplanten Einsaetzen.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\service;

use local_berufsbildung\persistent\block;
use local_berufsbildung\persistent\block_lk;
use local_berufsbildung\persistent\einsatz;

/**
 * Ein Block, der falsch, doppelt oder nur vorlaeufig angelegt wurde, soll
 * wieder weg koennen - auch wenn der Versetzungsplan ihn schon fuer spaeter
 * einplant. Nicht aber ein Block, in dem ein Einsatz bereits begonnen hat:
 * Raster, Lerndokumentation und Einsatzuebersicht loesen Name, Kurs und
 * Kompetenzen ueber den Block des Einsatzes auf, und was vorgekommen ist,
 * bleibt nachvollziehbar. Ein solcher Block wird deaktiviert, nicht
 * geloescht.
 *
 * Begonnen hat ein Einsatz, dessen Beginn am Stichtag oder davor liegt -
 * dieselbe Grenze wie im Kompetenzraster (raster_analyse). Die geplanten
 * Einsaetze verschwinden mit dem Block. Enthaelt der naechste
 * Versetzungsplan den Block noch, legt der Import ihn samt Einsaetzen neu
 * an, ohne Kompetenzzuordnung.
 */
class block_loesch_service {
    /**
     * Darf der Block geloescht werden - hat keiner seiner Einsaetze bis zum
     * Stichtag begonnen?
     *
     * @param block $block
     * @param int|null $stichtag Timestamp, null = jetzt
     */
    public function kann_loeschen(block $block, ?int $stichtag = null): bool {
        return !einsatz::record_exists_select(
            'blockid = :blockid AND von <= :stichtag',
            ['blockid' => (int) $block->get('id'), 'stichtag' => $stichtag ?? time()]
        );
    }

    /**
     * Die Bloecke mit mindestens einem bis zum Stichtag begonnenen Einsatz -
     * fuer die Uebersicht, in einer Abfrage statt einer je Block.
     *
     * @param int|null $stichtag Timestamp, null = jetzt
     * @return array<int, true> blockid => true
     */
    public function bloecke_mit_begonnenen_einsaetzen(?int $stichtag = null): array {
        global $DB;

        $ids = $DB->get_fieldset_sql(
            'SELECT DISTINCT blockid FROM {' . einsatz::TABLE . '} WHERE von <= :stichtag',
            ['stichtag' => $stichtag ?? time()]
        );

        return array_fill_keys(array_map('intval', $ids), true);
    }

    /**
     * Was mit dem Block verschwinden wuerde - fuer die Rueckfrage.
     *
     * @param block $block
     * @return array{kompetenzen: int, einsaetze: int, personen: int}
     */
    public function umfang(block $block): array {
        $einsaetze = einsatz::get_records(['blockid' => (int) $block->get('id')]);
        $personen = array_unique(array_map(static fn (einsatz $e): int => (int) $e->get('userid'), $einsaetze));

        return [
            'kompetenzen' => block_lk::count_records(['blockid' => (int) $block->get('id')]),
            'einsaetze' => count($einsaetze),
            'personen' => count($personen),
        ];
    }

    /**
     * Loescht den Block, seine Kompetenzzuordnung und seine geplanten
     * Einsaetze - alles oder nichts.
     *
     * @param block $block
     * @param int|null $stichtag Timestamp, null = jetzt
     * @return array{kompetenzen: int, einsaetze: int} Anzahl mitgeloeschter Datensaetze
     * @throws \moodle_exception Wenn ein Einsatz des Blocks bis zum Stichtag begonnen hat
     */
    public function loesche(block $block, ?int $stichtag = null): array {
        global $DB;

        $blockid = (int) $block->get('id');
        $transaktion = $DB->start_delegated_transaction();
        try {
            // In der Transaktion geprueft, damit ein Import dazwischen den
            // Block nicht unbemerkt in Gebrauch nimmt.
            if (!$this->kann_loeschen($block, $stichtag)) {
                throw new \moodle_exception('block:loeschen_begonnen', 'local_berufsbildung');
            }

            $zuordnungen = block_lk::get_records(['blockid' => $blockid]);
            foreach ($zuordnungen as $zuordnung) {
                $zuordnung->delete();
            }
            $einsaetze = einsatz::get_records(['blockid' => $blockid]);
            foreach ($einsaetze as $einsatz) {
                $einsatz->delete();
            }
            $block->delete();

            $transaktion->allow_commit();
        } catch (\Throwable $e) {
            // Wirft $e weiter.
            $transaktion->rollback($e);
        }

        return ['kompetenzen' => count($zuordnungen), 'einsaetze' => count($einsaetze)];
    }
}

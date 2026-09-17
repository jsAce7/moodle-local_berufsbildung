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
 * Tests fuer den Zeitstrahl des Versetzungsplans.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\output;

use advanced_testcase;
use local_berufsbildung\persistent\einsatz;

/**
 * Tests fuer einsatz_timeline.
 *
 * @covers \local_berufsbildung\output\einsatz_timeline
 */
final class einsatz_timeline_test extends advanced_testcase {
    /**
     * Baut einen Einsatz fuer die Timeline.
     *
     * @param int $blockid
     * @param int $von Timestamp
     * @param int $bis Timestamp
     * @return einsatz Nicht gespeichert - die Timeline rendert nur, sie liest nicht nach.
     */
    private function einsatz(int $blockid, int $von, int $bis): einsatz {
        return new einsatz(0, (object) [
            'userid' => 1,
            'blockid' => $blockid,
            'von' => $von,
            'bis' => $bis,
            'kw_von' => '2027-W01',
            'kw_bis' => '2027-W04',
            'importid' => 0,
        ]);
    }

    /**
     * Ohne importierten Versetzungsplan rendert die Timeline bewusst
     * nichts - der Plan ist eine Verbesserung, keine Voraussetzung, und
     * eine leere Ueberschrift waere nur Rauschen auf der Seite.
     */
    public function test_ohne_einsaetze_bleibt_leer(): void {
        $this->resetAfterTest();

        $this->assertSame('', einsatz_timeline::render([], []));
    }

    public function test_ordnet_vergangen_aktuell_und_kommend_ein(): void {
        $this->resetAfterTest();

        $jetzt = 5000;
        $einsaetze = [
            $this->einsatz(1, 1000, 1999),
            $this->einsatz(2, 4000, 6000),
            $this->einsatz(3, 8000, 9000),
        ];
        $blocknamen = [1 => 'Vergangene Abteilung', 2 => 'Laufende Abteilung', 3 => 'Kommende Abteilung'];

        $html = einsatz_timeline::render($einsaetze, $blocknamen, $jetzt);

        $this->assertStringContainsString('Vergangene Abteilung', $html);
        $this->assertStringContainsString('Laufende Abteilung', $html);
        $this->assertStringContainsString('Kommende Abteilung', $html);

        // Genau ein Eintrag ist als laufend markiert.
        $this->assertSame(1, substr_count($html, 'local-berufsbildung-timeline-aktuell'));
        $this->assertSame(1, substr_count($html, 'local-berufsbildung-timeline-vergangen'));
    }

    /**
     * Randfall an den Einsatzgrenzen: der erste und der letzte Zeitpunkt
     * eines Einsatzes zaehlen noch als laufend.
     */
    public function test_einsatzgrenzen_zaehlen_als_laufend(): void {
        $this->resetAfterTest();

        $einsatz = $this->einsatz(1, 4000, 6000);
        $blocknamen = [1 => 'Abteilung'];

        foreach ([4000, 6000] as $jetzt) {
            $html = einsatz_timeline::render([$einsatz], $blocknamen, $jetzt);
            $this->assertSame(1, substr_count($html, 'local-berufsbildung-timeline-aktuell'));
        }

        $html = einsatz_timeline::render([$einsatz], $blocknamen, 3999);
        $this->assertSame(0, substr_count($html, 'local-berufsbildung-timeline-aktuell'));

        $html = einsatz_timeline::render([$einsatz], $blocknamen, 6001);
        $this->assertSame(0, substr_count($html, 'local-berufsbildung-timeline-aktuell'));
        $this->assertSame(1, substr_count($html, 'local-berufsbildung-timeline-vergangen'));
    }

    /**
     * Derselbe Ausbildungsblock steht im Versetzungsplan regelmaessig
     * mehrfach - ein ueK etwa in zwei getrennten Wochen. Ohne Kennzeichnung
     * liest sich der zweite Eintrag wie ein doppelt importierter.
     */
    public function test_mehrfacher_block_weist_seine_folge_aus(): void {
        $this->resetAfterTest();

        $einsaetze = [
            $this->einsatz(1, 1000, 2000),
            $this->einsatz(2, 3000, 4000),
            $this->einsatz(1, 5000, 6000),
        ];

        $html = einsatz_timeline::render($einsaetze, [1 => 'üK 3', 2 => 'Betriebsferien'], 9000);

        // Nummeriert in Kalenderreihenfolge, nicht in Importreihenfolge.
        $this->assertStringContainsString(
            get_string('einsatz:teil', 'local_berufsbildung', (object) ['nummer' => 1, 'gesamt' => 2]),
            $html
        );
        $this->assertStringContainsString(
            get_string('einsatz:teil', 'local_berufsbildung', (object) ['nummer' => 2, 'gesamt' => 2]),
            $html
        );
    }

    /**
     * Der Randfall dazu: ein Block, der nur einmal vorkommt, bekommt keine
     * Teilangabe - "Teil 1 von 1" waere Rauschen an jedem zweiten Eintrag.
     */
    public function test_einmaliger_block_bleibt_ohne_teilangabe(): void {
        $this->resetAfterTest();

        $html = einsatz_timeline::render(
            [$this->einsatz(1, 1000, 2000), $this->einsatz(2, 3000, 4000)],
            [1 => 'Montage', 2 => 'Lager'],
            9000
        );

        $this->assertStringNotContainsString(
            get_string('einsatz:teil', 'local_berufsbildung', (object) ['nummer' => 1, 'gesamt' => 1]),
            $html
        );
    }

    /**
     * Ein Block, dessen Nummer beim Import unbekannt war, hat keinen
     * Namen - der Einsatz selbst bleibt trotzdem sichtbar, statt aus dem
     * Zeitstrahl zu verschwinden.
     */
    public function test_einsatz_ohne_blockname_bleibt_sichtbar(): void {
        $this->resetAfterTest();

        $html = einsatz_timeline::render([$this->einsatz(99, 1000, 2000)], [], 5000);

        $this->assertStringContainsString(
            get_string('einsatz:unbekannter_block', 'local_berufsbildung'),
            $html
        );
    }
}

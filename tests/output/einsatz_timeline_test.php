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
#[\PHPUnit\Framework\Attributes\CoversClass(\local_berufsbildung\output\einsatz_timeline::class)]
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
        $this->assertSame(1, substr_count($html, 'local-berufsbildung-timeline-kommend'));
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
     * Ein Plan ueber die ganze Lehrzeit ist sonst eine Liste aus zwanzig
     * gleich aussehenden Zeilen. Gegliedert wird nach Semestern, mit
     * denselben Bezeichnungen wie in der Taetigkeitenliste.
     */
    public function test_gliedert_nach_semestern(): void {
        $this->resetAfterTest();

        $grenzen = [1 => [1000, 4999], 2 => [5000, 9999]];
        $einsaetze = [$this->einsatz(1, 1000, 2000), $this->einsatz(2, 6000, 7000)];

        $html = einsatz_timeline::render($einsaetze, [1 => 'Montage', 2 => 'Lager'], 9500, $grenzen);

        $this->assertStringContainsString(get_string('nachweis:semester', 'local_berufsbildung', 1), $html);
        $this->assertStringContainsString(get_string('nachweis:semester', 'local_berufsbildung', 2), $html);
    }

    /**
     * Der Randfall an der Semestergrenze: ein Einsatz, der ueber den
     * Wechsel laeuft, ist in keinem Semester vollstaendig enthalten. Er
     * gehoert trotzdem in den Plan - und zwar in das Semester, in dem er
     * begonnen hat.
     */
    public function test_einsatz_ueber_die_semestergrenze_bleibt_im_frueheren(): void {
        $this->resetAfterTest();

        $grenzen = [1 => [1000, 4999], 2 => [5000, 9999]];

        $html = einsatz_timeline::render([$this->einsatz(1, 4500, 5500)], [1 => 'Montage'], 9500, $grenzen);

        $this->assertStringContainsString(get_string('nachweis:semester', 'local_berufsbildung', 1), $html);
        $this->assertStringNotContainsString(get_string('nachweis:semester', 'local_berufsbildung', 2), $html);
        $this->assertStringNotContainsString(get_string('nachweis:ohne_semester', 'local_berufsbildung'), $html);
    }

    /**
     * Ein Einsatz vor Lehrbeginn - etwa Betriebsferien unmittelbar davor -
     * faellt in kein Semester und darf trotzdem nicht aus dem Plan fallen.
     */
    public function test_einsatz_ausserhalb_der_lehrzeit_bleibt_sichtbar(): void {
        $this->resetAfterTest();

        $grenzen = [1 => [5000, 9999]];

        $html = einsatz_timeline::render([$this->einsatz(1, 1000, 2000)], [1 => 'Betriebsferien'], 9500, $grenzen);

        $this->assertStringContainsString('Betriebsferien', $html);
        $this->assertStringContainsString(get_string('nachweis:ohne_semester', 'local_berufsbildung'), $html);
    }

    /**
     * Ohne Semestergrenzen - etwa wenn sich Beruf oder Jahrgang nicht
     * aufloesen lassen - bleibt es die durchgehende Liste. Eine Gliederung
     * mit einer einzigen unbeschrifteten Gruppe waere keine.
     */
    public function test_ohne_semestergrenzen_bleibt_die_liste_ungegliedert(): void {
        $this->resetAfterTest();

        $html = einsatz_timeline::render([$this->einsatz(1, 1000, 2000)], [1 => 'Montage'], 5000);

        $this->assertStringContainsString('Montage', $html);
        $this->assertStringNotContainsString('local-berufsbildung-timeline-gruppenname', $html);
        // Nichts zu falten heisst auch: kein Aufklapper, der zugeklappt
        // waere und die einzige Liste der Seite verbergen wuerde.
        $this->assertStringNotContainsString('<details', $html);
    }

    /**
     * Genau ein Aufklapper steht offen, und zwar der des erwarteten
     * Semesters.
     *
     * Geprueft ueber die Reihenfolge im Markup: das <details open> steht
     * unmittelbar vor der Ueberschrift seiner eigenen Gruppe und hinter
     * allen frueheren Gruppen.
     *
     * @param string $html
     * @param int $semester Semesternummer, deren Gruppe offen sein soll
     */
    private function assert_semester_offen(string $html, int $semester): void {
        $this->assertSame(1, substr_count($html, ' open>'), 'Es steht nicht genau ein Aufklapper offen.');

        $offen = strpos($html, ' open>');
        $eigene = strpos($html, get_string('nachweis:semester', 'local_berufsbildung', $semester));
        $this->assertNotFalse($eigene);
        $this->assertLessThan($eigene, $offen);

        if ($semester > 1) {
            $vorherige = strpos($html, get_string('nachweis:semester', 'local_berufsbildung', $semester - 1));
            $this->assertNotFalse($vorherige);
            $this->assertGreaterThan($vorherige, $offen);
        }
    }

    /**
     * Offen ist genau das Semester, in dem gerade ein Einsatz laeuft - der
     * ganze Plan ausgeklappt waere ueber vier Lehrjahre ein
     * Referenzdokument, kein Ueberblick.
     */
    public function test_nur_das_laufende_semester_steht_offen(): void {
        $this->resetAfterTest();

        $grenzen = [1 => [1000, 4999], 2 => [5000, 9999]];
        $einsaetze = [$this->einsatz(1, 1000, 2000), $this->einsatz(2, 6000, 7000)];

        $html = einsatz_timeline::render($einsaetze, [1 => 'Montage', 2 => 'Lager'], 6500, $grenzen);

        $this->assertSame(2, substr_count($html, '<details'));
        $this->assert_semester_offen($html, 2);
    }

    /**
     * Randfall: "jetzt" faellt in keinen Einsatz - etwa in die Luecke
     * zwischen zwei Bloecken. Dann oeffnet das Semester mit dem naechsten
     * kommenden Einsatz, damit die Frage "was kommt als Naechstes" ohne
     * Klick beantwortet bleibt.
     */
    public function test_ohne_laufenden_einsatz_oeffnet_das_naechste_semester(): void {
        $this->resetAfterTest();

        $grenzen = [1 => [1000, 4999], 2 => [5000, 9999]];
        $einsaetze = [$this->einsatz(1, 1000, 2000), $this->einsatz(2, 6000, 7000)];

        // Zwischen den beiden Einsaetzen, noch im ersten Semester.
        $html = einsatz_timeline::render($einsaetze, [1 => 'Montage', 2 => 'Lager'], 3000, $grenzen);

        $this->assert_semester_offen($html, 2);
    }

    /**
     * Der andere Randfall: der ganze Plan liegt in der Vergangenheit. Dann
     * bleibt das letzte Semester offen - eine Liste aus lauter
     * zugeklappten Zeilen waere die schlechteste aller Ansichten.
     */
    public function test_abgelaufener_plan_oeffnet_das_letzte_semester(): void {
        $this->resetAfterTest();

        $grenzen = [1 => [1000, 4999], 2 => [5000, 9999]];
        $einsaetze = [$this->einsatz(1, 1000, 2000), $this->einsatz(2, 6000, 7000)];

        $html = einsatz_timeline::render($einsaetze, [1 => 'Montage', 2 => 'Lager'], 50000, $grenzen);

        $this->assert_semester_offen($html, 2);
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

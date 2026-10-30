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

namespace local_berufsbildung\pdf;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_berufsbildung\pdf\zeichnen::class)]
/**
 * Tests for the drawing primitives shared by the PDF documents.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_berufsbildung\pdf\zeichnen
 */
final class zeichnen_test extends \advanced_testcase {
    /**
     * An empty A4 page with the margins of the documents.
     *
     * @return \pdf
     */
    private function seite(): \pdf {
        global $CFG;
        require_once($CFG->libdir . '/pdflib.php');

        $pdf = new \pdf('P', 'mm', 'A4');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(zeichnen::RAND_SEITE, zeichnen::RAND_OBEN, zeichnen::RAND_SEITE);
        $pdf->AddPage();
        return $pdf;
    }

    /**
     * A short group heading keeps its one line of 5.5 mm.
     */
    public function test_gruppenzeile_kurz_bleibt_einzeilig(): void {
        $this->resetAfterTest();
        $pdf = $this->seite();

        $y = $pdf->GetY();
        zeichnen::gruppenzeile($pdf, 'LFB_El_LBB · Leiterplatte bestücken und löten Basis', [zeichnen::BREITE]);

        $this->assertEqualsWithDelta(5.5, $pdf->GetY() - $y, 0.01);
        $this->assertEqualsWithDelta(zeichnen::RAND_SEITE, $pdf->GetX(), 0.01);
    }

    /**
     * A heading wider than the table wraps onto further lines instead of running
     * past its right edge, and the next row starts below it at the left margin.
     */
    public function test_gruppenzeile_lang_bricht_um(): void {
        $this->resetAfterTest();
        $pdf = $this->seite();
        $text = 'LFB_ES_UMSBAS2 · Verbindungsprogrammierte Steuerungen im Niederspannungsbereich '
            . 'aufbauen und in Betrieb nehmen, ergänzt um einen Zusatz, der die Zeile sicher sprengt';

        $y = $pdf->GetY();
        zeichnen::gruppenzeile($pdf, $text, [zeichnen::BREITE]);

        $this->assertGreaterThan(5.5 + 2.0, $pdf->GetY() - $y);
        $this->assertEqualsWithDelta(zeichnen::RAND_SEITE, $pdf->GetX(), 0.01);
        $this->assertSame(1, $pdf->getNumPages());
    }

    /**
     * With a mark in the first columns the text wraps in the width that is left:
     * a title that fits the whole table no longer fits beside the mark.
     */
    public function test_gruppenzeile_mit_marke_bricht_in_der_restbreite_um(): void {
        $this->resetAfterTest();
        $breiten = [9.0, 25.0, 68.0, 20.0, 48.0];
        $text = 'Verbindungsprogrammierte Steuerungen im Niederspannungsbereich aufbauen und in Betrieb nehmen';

        $ohne = $this->seite();
        $y = $ohne->GetY();
        zeichnen::gruppenzeile($ohne, $text, $breiten);
        $this->assertEqualsWithDelta(5.5, $ohne->GetY() - $y, 0.01);

        $mit = $this->seite();
        $y = $mit->GetY();
        zeichnen::gruppenzeile($mit, $text, $breiten, null, null, 'LFB_ES_UMSBAS2', 2);
        $this->assertGreaterThan(5.5 + 2.0, $mit->GetY() - $y);
        $this->assertEqualsWithDelta(zeichnen::RAND_SEITE, $mit->GetX(), 0.01);

        $kurz = $this->seite();
        $y = $kurz->GetY();
        zeichnen::gruppenzeile($kurz, 'Leiterplatte bestücken und löten Basis', $breiten, null, null, 'LFB_El_LBB', 2);
        $this->assertEqualsWithDelta(5.5, $kurz->GetY() - $y, 0.01);
    }
}

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
 * Tests fuer den Versetzungsplan-CSV-Parser.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\versetzungsplan;

use advanced_testcase;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_berufsbildung\versetzungsplan\csv_parser::class)]
/**
 * Tests fuer csv_parser.
 *
 * @covers \local_berufsbildung\versetzungsplan\csv_parser
 */
final class csv_parser_test extends advanced_testcase {
    public function test_blockformat_normalfall(): void {
        $csv = "email;block;kw_von;kw_bis;bemerkung\n"
            . "anna.muster@firma.ch;4;2027-W15;2027-W26;\n"
            . "anna.muster@firma.ch;7;2027-W27;2027-W33;\n";

        $ergebnis = (new csv_parser())->parsen($csv);

        $this->assertSame([], $ergebnis['fehler']);
        $this->assertCount(2, $ergebnis['eintraege']);
        $this->assertSame('anna.muster@firma.ch', $ergebnis['eintraege'][0]['email']);
        $this->assertSame('4', $ergebnis['eintraege'][0]['block']);
    }

    /**
     * Regel aus docs/konzept.md §5.2: zwoelf Zeilen im Wochenformat mit
     * demselben Block ergeben einen Einsatz, nicht zwoelf.
     */
    public function test_wochenformat_fasst_zwoelf_aufeinanderfolgende_wochen_zu_einem_eintrag_zusammen(): void {
        $zeilen = ["email;block;kw"];
        for ($woche = 15; $woche <= 26; $woche++) {
            $zeilen[] = sprintf('anna.muster@firma.ch;4;2027-W%02d', $woche);
        }
        $csv = implode("\n", $zeilen);

        $ergebnis = (new csv_parser())->parsen($csv);

        $this->assertSame([], $ergebnis['fehler']);
        $this->assertCount(1, $ergebnis['eintraege']);
        $this->assertSame('2027-W15', $ergebnis['eintraege'][0]['kw_von']);
        $this->assertSame('2027-W26', $ergebnis['eintraege'][0]['kw_bis']);
    }

    /**
     * Randfall: eine Luecke zwischen den Wochen ergibt zwei getrennte
     * Eintraege statt eines durchgehenden.
     */
    public function test_wochenformat_mit_luecke_ergibt_zwei_eintraege(): void {
        $csv = "email;block;kw\n"
            . "anna.muster@firma.ch;4;2027-W15\n"
            . "anna.muster@firma.ch;4;2027-W16\n"
            . "anna.muster@firma.ch;4;2027-W20\n"
            . "anna.muster@firma.ch;4;2027-W21\n";

        $ergebnis = (new csv_parser())->parsen($csv);

        $this->assertSame([], $ergebnis['fehler']);
        $this->assertCount(2, $ergebnis['eintraege']);
        $this->assertSame('2027-W15', $ergebnis['eintraege'][0]['kw_von']);
        $this->assertSame('2027-W16', $ergebnis['eintraege'][0]['kw_bis']);
        $this->assertSame('2027-W20', $ergebnis['eintraege'][1]['kw_von']);
        $this->assertSame('2027-W21', $ergebnis['eintraege'][1]['kw_bis']);
    }

    public function test_gemischtes_format_wird_abgewiesen(): void {
        $csv = "email;block;kw_von;kw_bis;kw\n"
            . "anna.muster@firma.ch;4;2027-W15;2027-W16;2027-W15\n";

        $ergebnis = (new csv_parser())->parsen($csv);

        $this->assertSame([], $ergebnis['eintraege']);
        $this->assertNotEmpty($ergebnis['fehler']);
    }

    public function test_fehlende_pflichtspalte_wird_abgewiesen(): void {
        $csv = "block;kw_von;kw_bis\n4;2027-W15;2027-W16\n";

        $ergebnis = (new csv_parser())->parsen($csv);

        $this->assertSame([], $ergebnis['eintraege']);
        $this->assertNotEmpty($ergebnis['fehler']);
    }

    /**
     * Randfall: eine einzelne fehlerhafte Zeile bricht den Rest des Imports
     * nicht ab.
     */
    public function test_ungueltige_kalenderwoche_wird_uebersprungen_rest_bleibt(): void {
        $csv = "email;block;kw_von;kw_bis\n"
            . "anna.muster@firma.ch;4;nicht-eine-kw;2027-W16\n"
            . "beat.beispiel@firma.ch;4;2027-W15;2027-W16\n";

        $ergebnis = (new csv_parser())->parsen($csv);

        $this->assertCount(1, $ergebnis['eintraege']);
        $this->assertSame('beat.beispiel@firma.ch', $ergebnis['eintraege'][0]['email']);
        $this->assertNotEmpty($ergebnis['fehler']);
    }

    public function test_kw_bis_vor_kw_von_wird_uebersprungen(): void {
        $csv = "email;block;kw_von;kw_bis\nanna.muster@firma.ch;4;2027-W20;2027-W15\n";

        $ergebnis = (new csv_parser())->parsen($csv);

        $this->assertSame([], $ergebnis['eintraege']);
        $this->assertNotEmpty($ergebnis['fehler']);
    }

    /**
     * Randfall: ueberschneidende Zeitraeume derselben Person - auch ueber
     * verschiedene Bloecke hinweg - werden beide abgewiesen, nicht
     * stillschweigend zusammengefuehrt.
     */
    public function test_ueberschneidende_zeitraeume_werden_beide_abgewiesen(): void {
        $csv = "email;block;kw_von;kw_bis\n"
            . "anna.muster@firma.ch;4;2027-W15;2027-W20\n"
            . "anna.muster@firma.ch;7;2027-W18;2027-W22\n";

        $ergebnis = (new csv_parser())->parsen($csv);

        $this->assertSame([], $ergebnis['eintraege']);
        $this->assertNotEmpty($ergebnis['fehler']);
    }

    public function test_verschachtelte_ueberschneidungen_werden_vollstaendig_abgewiesen(): void {
        $csv = "email;block;kw_von;kw_bis\n"
            . "anna.muster@firma.ch;4;2027-W15;2027-W25\n"
            . "anna.muster@firma.ch;7;2027-W17;2027-W18\n"
            . "anna.muster@firma.ch;9;2027-W20;2027-W21\n";

        $ergebnis = (new csv_parser())->parsen($csv);

        $this->assertSame([], $ergebnis['eintraege']);
        $this->assertNotEmpty($ergebnis['fehler']);
    }

    public function test_direkt_anschliessende_zeitraeume_ueberschneiden_sich_nicht(): void {
        $csv = "email;block;kw_von;kw_bis\n"
            . "anna.muster@firma.ch;4;2027-W15;2027-W20\n"
            . "anna.muster@firma.ch;7;2027-W21;2027-W22\n";

        $ergebnis = (new csv_parser())->parsen($csv);

        $this->assertSame([], $ergebnis['fehler']);
        $this->assertCount(2, $ergebnis['eintraege']);
    }

    public function test_leere_datei_liefert_leeres_ergebnis(): void {
        $ergebnis = (new csv_parser())->parsen('');

        $this->assertSame([], $ergebnis['eintraege']);
        $this->assertSame([], $ergebnis['fehler']);
        $this->assertSame(0, $ergebnis['zeilen_gelesen']);
    }

    public function test_semikolon_in_angefuehrter_bemerkung_wird_korrekt_gelesen(): void {
        $csv = "email;block;kw_von;kw_bis;bemerkung\n"
            . 'anna.muster@firma.ch;4;2027-W15;2027-W16;"Vertretung; nur zur Info"' . "\n";

        $ergebnis = (new csv_parser())->parsen($csv);

        $this->assertSame([], $ergebnis['fehler']);
        $this->assertSame('Vertretung; nur zur Info', $ergebnis['eintraege'][0]['bemerkung']);
    }
}

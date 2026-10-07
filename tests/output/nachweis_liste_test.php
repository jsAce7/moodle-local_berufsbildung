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
 * Tests fuer die nach Quelle gruppierte Nachweis-Darstellung.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\output;

use advanced_testcase;
use local_berufsbildung\nachweis\ausstehend;
use local_berufsbildung\nachweis\nachweis;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_berufsbildung\output\nachweis_liste::class)]
/**
 * Tests fuer nachweis_liste.
 *
 * @covers \local_berufsbildung\output\nachweis_liste
 */
final class nachweis_liste_test extends advanced_testcase {
    public function test_ohne_nachweise_zeigt_leertext(): void {
        $this->resetAfterTest();

        $html = nachweis_liste::render([], []);

        $this->assertStringContainsString(
            get_string('form:keine_taetigkeiten', 'local_berufsbildung'),
            $html
        );
    }

    /**
     * Getrennte Darstellung je Quelle (z.B. Lerndokumentation vs. ueK) -
     * jede Quelle bekommt ihre eigene Ueberschrift, die Reihenfolge
     * innerhalb einer Gruppe bleibt wie eingegeben (bereits vom Collector
     * sortiert, hier nicht neu sortiert).
     */
    public function test_gruppiert_nach_quelle(): void {
        $this->resetAfterTest();

        $nachweise = [
            new nachweis('lerndoku', 'Neuerer Lerndoku-Eintrag', time(), null, null, null),
            new nachweis('uek', 'üK 3: Steuerungstechnik', time() - 10, '5.0', null, null),
            new nachweis('lerndoku', 'Aelterer Lerndoku-Eintrag', time() - 100, null, null, null),
        ];

        $html = nachweis_liste::render($nachweise, [
            'lerndoku' => 'Lerndokumentation',
            'uek' => 'Überbetriebliche Kurse',
        ]);

        $this->assertStringContainsString('Lerndokumentation', $html);
        $this->assertStringContainsString('Überbetriebliche Kurse', $html);
        $this->assertStringContainsString('üK 3: Steuerungstechnik', $html);

        // Reihenfolge innerhalb der Lerndoku-Gruppe bleibt neueste zuerst.
        $posneuer = strpos($html, 'Neuerer Lerndoku-Eintrag');
        $posaelter = strpos($html, 'Aelterer Lerndoku-Eintrag');
        $this->assertNotFalse($posneuer);
        $this->assertNotFalse($posaelter);
        $this->assertLessThan($posaelter, $posneuer);
    }

    /**
     * Das Ergebnis steht beschriftet da - "5.5" allein sagt nicht, wovon.
     * Neutral als "Ergebnis", weil eine Quelle auch "bestanden" liefern darf.
     */
    public function test_ergebnis_wird_beschriftet_angezeigt(): void {
        $this->resetAfterTest();

        $nachweise = [new nachweis('uek', 'üK 3', time(), 'bestanden', null, null)];

        $html = nachweis_liste::render($nachweise, ['uek' => 'ÜK']);

        $this->assertStringContainsString(
            get_string('nachweis:ergebnis', 'local_berufsbildung', 'bestanden'),
            $html
        );
    }

    /**
     * Randfall: eine Quelle ohne Eintrag im uebergebenen Namen-Mapping
     * (z.B. ein inzwischen deaktivierter Provider) faellt auf den
     * Quelle-Key zurueck, statt die Darstellung abzubrechen.
     */
    public function test_unbekannte_quelle_faellt_auf_key_zurueck(): void {
        $this->resetAfterTest();

        $nachweise = [new nachweis('unbekannt', 'Eintrag', time(), null, null, null)];

        $html = nachweis_liste::render($nachweise, []);

        $this->assertStringContainsString('unbekannt', $html);
    }

    /**
     * Auch mit Semestergrenzen bleibt die Gruppe die Quelle - jede Art von
     * Nachweis hat ihre eigene Zusammenfassung. Das Semester steht dafuer
     * in jeder Zeile.
     */
    public function test_nennt_das_semester_je_zeile_und_gruppiert_nach_quelle(): void {
        $this->resetAfterTest();

        $semestergrenzen = [
            1 => [1000, 1999],
            2 => [2000, 2999],
        ];

        $nachweise = [
            new nachweis('uek', 'üK im zweiten Semester', 2500, null, null, null),
            new nachweis('uek', 'üK im ersten Semester', 1500, null, null, null),
        ];

        $html = nachweis_liste::render($nachweise, ['uek' => 'Überbetriebliche Kurse'], $semestergrenzen);

        $this->assertSame(1, substr_count($html, 'Überbetriebliche Kurse'));
        $this->assertStringContainsString(get_string('nachweis:semester', 'local_berufsbildung', 1), $html);
        $this->assertStringContainsString(get_string('nachweis:semester', 'local_berufsbildung', 2), $html);

        // Innerhalb der Gruppe bleibt die Reihenfolge des Collectors: neueste zuerst.
        $this->assertLessThan(
            strpos($html, 'üK im ersten Semester'),
            strpos($html, 'üK im zweiten Semester')
        );
    }

    /**
     * Die Gruppen stehen in der Reihenfolge der registrierten Quellen, nicht
     * in der des juengsten Nachweises - sonst wechselte eine Quelle mit
     * jedem neuen Eintrag den Platz.
     */
    public function test_gruppen_folgen_der_reihenfolge_der_quellen(): void {
        $this->resetAfterTest();

        $nachweise = [
            new nachweis('uek', 'Neuester üK', 3000, '5.0', null, null),
            new nachweis('lerndoku', 'Aelterer Eintrag', 1000, null, null, null),
        ];

        $html = nachweis_liste::render($nachweise, [
            'lerndoku' => 'Lerndokumentation',
            'uek' => 'Überbetriebliche Kurse',
        ]);

        $this->assertLessThan(strpos($html, 'Überbetriebliche Kurse'), strpos($html, 'Lerndokumentation'));
    }

    /**
     * Die Lerndokumentation liefert einen Eintrag je Handlungskompetenz als
     * eigenen Nachweis. In der Liste steht er trotzdem nur einmal, und die
     * Anzahl im Gruppenkopf zaehlt ihn einmal.
     */
    public function test_fasst_nachweise_je_kompetenz_zu_einer_zeile_zusammen(): void {
        $this->resetAfterTest();

        $nachweise = [
            new nachweis('lerndoku', 'Schaltschrank verdrahtet', 1000, null, 11, '/local/x'),
            new nachweis('lerndoku', 'Schaltschrank verdrahtet', 1000, null, 12, '/local/x'),
            new nachweis('lerndoku', 'Schaltschrank verdrahtet', 1000, null, 13, '/local/x'),
        ];

        $html = nachweis_liste::render($nachweise, ['lerndoku' => 'Lerndokumentation']);

        $this->assertSame(1, substr_count($html, 'Schaltschrank verdrahtet'));
        $this->assertStringContainsString(get_string('nachweis:anzahl_eins', 'local_berufsbildung'), $html);
    }

    /**
     * Randfall zur Zusammenfassung: gleich ist nur, was in allem uebereinstimmt,
     * was die Liste zeigt. Ein anderes Datum oder eine andere Bezeichnung ist
     * ein anderer Nachweis.
     */
    public function test_eindeutige_behaelt_verschiedene_nachweise(): void {
        $nachweise = [
            new nachweis('lerndoku', 'Eintrag', 1000, null, 11, '/local/x'),
            new nachweis('lerndoku', 'Eintrag', 1000, null, 12, '/local/x'),
            new nachweis('lerndoku', 'Eintrag', 2000, null, 11, '/local/x'),
            new nachweis('lerndoku', 'Anderer Eintrag', 1000, null, 11, '/local/x'),
            new nachweis('uek', 'Eintrag', 1000, null, null, '/local/x'),
        ];

        $eindeutige = nachweis_liste::eindeutige($nachweise);

        $this->assertCount(4, $eindeutige);
        // Der erste gewinnt, die Reihenfolge bleibt.
        $this->assertSame(11, $eindeutige[0]->competencyid);
        $this->assertSame(2000, $eindeutige[1]->datum);
    }

    /**
     * Die Zusammenfassung einer Quelle steht in deren Gruppenkopf, und nur
     * dort.
     */
    public function test_zeigt_die_zusammenfassung_im_kopf_ihrer_quelle(): void {
        $this->resetAfterTest();

        $nachweise = [
            new nachweis('lerndoku', 'Eintrag', 2000, null, null, null),
            new nachweis('uek', 'üK 2', 1000, '5.5', null, null),
        ];

        $html = nachweis_liste::render(
            $nachweise,
            ['lerndoku' => 'Lerndokumentation', 'uek' => 'Überbetriebliche Kurse'],
            zusammenfassungen: ['uek' => 'Schnitt 5.5']
        );

        $this->assertSame(1, substr_count($html, 'Schnitt 5.5'));
        $this->assertGreaterThan(strpos($html, 'Überbetriebliche Kurse'), strpos($html, 'Schnitt 5.5'));
        $this->assertLessThan(strpos($html, 'üK 2'), strpos($html, 'Schnitt 5.5'));
    }

    /**
     * Was noch aussteht, steht in der Gruppe seiner Quelle unter den
     * Nachweisen, mit dem Stand, den die Quelle formuliert hat.
     */
    public function test_ausstehendes_steht_unter_den_nachweisen_seiner_quelle(): void {
        $this->resetAfterTest();

        $html = nachweis_liste::render(
            [new nachweis('uek', 'üK 2: Kleinspannung', 1000, '5.5', null, null)],
            ['lerndoku' => 'Lerndokumentation', 'uek' => 'Überbetriebliche Kurse'],
            ausstehende: ['uek' => [new ausstehend('uek', 'üK 4: Steuerungen', 'noch nicht eingeplant')]]
        );

        $this->assertStringContainsString('noch nicht eingeplant', $html);
        $this->assertStringContainsString(get_string('nachweis:ausstehend_titel', 'local_berufsbildung'), $html);
        $this->assertLessThan(strpos($html, 'üK 4: Steuerungen'), strpos($html, 'üK 2: Kleinspannung'));
        // Die Lerndokumentation hat weder Nachweise noch Ausstehendes - keine Gruppe.
        $this->assertStringNotContainsString('Lerndokumentation', $html);
    }

    /**
     * Randfall zu Beginn der Lehre: noch kein einziger Nachweis, aber die
     * kommenden ueK sind bekannt. Dann steht die Gruppe trotzdem da, ohne
     * "0 Nachweise" im Kopf, und nicht der Leertext.
     */
    public function test_quelle_nur_mit_ausstehendem_bekommt_ihre_gruppe(): void {
        $this->resetAfterTest();

        $html = nachweis_liste::render(
            [],
            ['uek' => 'Überbetriebliche Kurse'],
            ausstehende: ['uek' => [new ausstehend('uek', 'üK 1: Grundlagen', 'geplant')]]
        );

        $this->assertStringContainsString('Überbetriebliche Kurse', $html);
        $this->assertStringContainsString('üK 1: Grundlagen', $html);
        $this->assertStringNotContainsString(get_string('nachweis:anzahl', 'local_berufsbildung', 0), $html);
        $this->assertStringNotContainsString(get_string('form:keine_taetigkeiten', 'local_berufsbildung'), $html);
    }

    /**
     * Randfall an der Semestergrenze: der letzte Zeitpunkt eines Semesters
     * gehoert noch zu diesem, der erste Zeitpunkt danach zum naechsten.
     */
    public function test_semestergrenzen_sind_einschliesslich(): void {
        $this->resetAfterTest();

        $semestergrenzen = [1 => [1000, 1999], 2 => [2000, 2999]];

        $html = nachweis_liste::render(
            [new nachweis('uek', 'Letzte Sekunde Semester 1', 1999, null, null, null)],
            [],
            $semestergrenzen
        );
        $this->assertStringContainsString(get_string('nachweis:semester', 'local_berufsbildung', 1), $html);

        $html = nachweis_liste::render(
            [new nachweis('uek', 'Erste Sekunde Semester 2', 2000, null, null, null)],
            [],
            $semestergrenzen
        );
        $this->assertStringContainsString(get_string('nachweis:semester', 'local_berufsbildung', 2), $html);
    }

    /**
     * Ein Nachweis ausserhalb jeder Semestergrenze - etwa aus der Zeit vor
     * dem Lehrbeginn - darf nicht stillschweigend verschwinden.
     */
    public function test_nachweis_ausserhalb_der_lehrzeit_geht_nicht_verloren(): void {
        $this->resetAfterTest();

        $html = nachweis_liste::render(
            [new nachweis('uek', 'Vor dem Lehrbeginn', 500, null, null, null)],
            [],
            [1 => [1000, 1999]]
        );

        $this->assertStringContainsString('Vor dem Lehrbeginn', $html);
        $this->assertStringContainsString(get_string('nachweis:ohne_semester', 'local_berufsbildung'), $html);
    }
}

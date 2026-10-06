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
 * Tests fuer den Versetzungsplan-Import.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\versetzungsplan;

use advanced_testcase;
use local_berufsbildung\persistent\block;
use local_berufsbildung\persistent\einsatz;
use local_berufsbildung\persistent\plan_import;
use stdClass;

/**
 * Tests fuer import_service.
 *
 * @covers \local_berufsbildung\versetzungsplan\import_service
 */
final class import_service_test extends advanced_testcase {
    /**
     * Gibt der lernenden Person eine laufende Zuordnung, damit der Import sie kennt.
     *
     * @param stdClass $lernende
     */
    private function lege_aktive_zuordnung_an(stdClass $lernende): void {
        $berufsbildner = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->get_plugin_generator('local_berufsbildung')->create_zuordnung([
            'berufsbildnerid' => (int) $berufsbildner->id,
            'lernendeid' => (int) $lernende->id,
            'gueltig_von' => strtotime('-1 year'),
        ]);
    }

    public function test_normalfall_erzeugt_einsatz_und_protokoll(): void {
        $this->resetAfterTest();

        $admin = $this->getDataGenerator()->create_user();
        $lernende = $this->getDataGenerator()->create_user(['email' => 'anna.muster@firma.ch']);
        $this->lege_aktive_zuordnung_an($lernende);

        $csv = "email;block;kw_von;kw_bis\nanna.muster@firma.ch;4;2027-W15;2027-W20\n";

        $ergebnis = (new import_service())->verarbeiten($csv, 'upload', (int) $admin->id);

        // Der unbekannte Block wird protokolliert.
        $this->assertSame('mit_warnungen', $ergebnis['status']);
        $this->assertSame(1, $ergebnis['personen_verarbeitet']);
        $this->assertSame(1, $ergebnis['einsaetze_erzeugt']);
        $this->assertCount(1, einsatz::get_records(['userid' => (int) $lernende->id]));
        $this->assertCount(1, plan_import::get_records([]));
        $this->assertNotEmpty(block::get_record(['nummer' => '4']));
    }

    /**
     * Abnahmekriterium: eine unbekannte Mailadresse landet im Protokoll,
     * ohne den Lauf abzubrechen.
     */
    public function test_unbekannte_mailadresse_landet_im_protokoll_bricht_lauf_nicht_ab(): void {
        $this->resetAfterTest();

        $admin = $this->getDataGenerator()->create_user();
        $lernende = $this->getDataGenerator()->create_user(['email' => 'anna.muster@firma.ch']);
        $this->lege_aktive_zuordnung_an($lernende);
        // Damit der Block schon bekannt ist und die Statusmeldung sauber pruefbar bleibt.
        (new block(0, (object) ['nummer' => '4', 'name' => '4', 'ist_betrieb' => true, 'aktiv' => true]))->create();

        $csv = "email;block;kw_von;kw_bis\n"
            . "alt.name@firma.ch;4;2027-W15;2027-W16\n"
            . "anna.muster@firma.ch;4;2027-W17;2027-W18\n";

        $ergebnis = (new import_service())->verarbeiten($csv, 'upload', (int) $admin->id);

        $this->assertSame(1, $ergebnis['personen_verarbeitet']);
        $this->assertSame(1, $ergebnis['einsaetze_erzeugt']);
        $this->assertCount(1, array_filter($ergebnis['protokoll'], static fn ($z) => str_contains($z, 'alt.name@firma.ch')));
    }

    /**
     * Bekannte Person ohne aktive Zuordnung: nicht protokolliert, nur gezaehlt.
     */
    public function test_person_ohne_aktive_zuordnung_wird_nur_gezaehlt_nicht_protokolliert(): void {
        $this->resetAfterTest();

        $admin = $this->getDataGenerator()->create_user();
        // Ohne Zuordnung.
        $this->getDataGenerator()->create_user(['email' => 'fremd@firma.ch']);

        $csv = "email;block;kw_von;kw_bis\nfremd@firma.ch;4;2027-W15;2027-W16\n";

        $ergebnis = (new import_service())->verarbeiten($csv, 'upload', (int) $admin->id);

        $this->assertSame(0, $ergebnis['personen_verarbeitet']);
        $this->assertSame(1, $ergebnis['zeilen_ausserhalb_geltungsbereich']);
        $this->assertSame([], $ergebnis['protokoll']);
        $this->assertSame('ok', $ergebnis['status']);
    }

    /**
     * Abnahmekriterium: eine unveraenderte Lieferung wird beim zweiten
     * Aufruf ohne Schreibvorgang beendet.
     */
    public function test_unveraenderte_lieferung_wird_beim_zweiten_aufruf_nicht_erneut_verarbeitet(): void {
        $this->resetAfterTest();

        $admin = $this->getDataGenerator()->create_user();
        $lernende = $this->getDataGenerator()->create_user(['email' => 'anna.muster@firma.ch']);
        $this->lege_aktive_zuordnung_an($lernende);

        $csv = "email;block;kw_von;kw_bis\nanna.muster@firma.ch;4;2027-W15;2027-W16\n";
        $service = new import_service();

        $service->verarbeiten($csv, 'upload', (int) $admin->id);
        $this->assertCount(1, plan_import::get_records([]));

        $zweitesergebnis = $service->verarbeiten($csv, 'upload', (int) $admin->id);

        $this->assertTrue($zweitesergebnis['unveraendert']);
        // Kein zweiter plan_import-Datensatz, keine zusaetzlichen Einsaetze.
        $this->assertCount(1, plan_import::get_records([]));
        $this->assertCount(1, einsatz::get_records(['userid' => (int) $lernende->id]));
    }

    /**
     * Abnahmekriterium: eine inhaltlich unveraenderte CSV wird trotzdem neu
     * verarbeitet, wenn zwischen den Lieferungen eine Zuordnung dazugekommen
     * ist - sonst wuerde eine neu zugeordnete Person erst mit der naechsten
     * inhaltlich abweichenden Lieferung erfasst, nicht schon mit der
     * naechsten (identischen) woechentlichen.
     */
    public function test_unveraenderte_csv_aber_neue_zuordnung_wird_erneut_verarbeitet(): void {
        $this->resetAfterTest();

        $admin = $this->getDataGenerator()->create_user();
        $lernende = $this->getDataGenerator()->create_user(['email' => 'anna.muster@firma.ch']);
        (new block(0, (object) ['nummer' => '4', 'name' => '4', 'ist_betrieb' => true, 'aktiv' => true]))->create();

        $csv = "email;block;kw_von;kw_bis\nanna.muster@firma.ch;4;2027-W15;2027-W16\n";
        $service = new import_service();

        // Erste Lieferung: noch keine Zuordnung, Zeile laeuft ins Leere.
        $erstesergebnis = $service->verarbeiten($csv, 'upload', (int) $admin->id);
        $this->assertFalse($erstesergebnis['unveraendert']);
        $this->assertSame(0, $erstesergebnis['personen_verarbeitet']);
        $this->assertCount(1, plan_import::get_records([]));

        $this->lege_aktive_zuordnung_an($lernende);

        // Zweite Lieferung: derselbe CSV-Text, aber jetzt existiert die Zuordnung.
        $zweitesergebnis = $service->verarbeiten($csv, 'upload', (int) $admin->id);

        $this->assertFalse($zweitesergebnis['unveraendert']);
        $this->assertSame(1, $zweitesergebnis['personen_verarbeitet']);
        $this->assertCount(2, plan_import::get_records([]));
        $this->assertCount(1, einsatz::get_records(['userid' => (int) $lernende->id]));
    }

    /**
     * Abnahmekriterium: eine Lieferung mit deutlich weniger verarbeiteten
     * Personen wird abgewiesen und loescht nichts.
     */
    public function test_deutlicher_rueckgang_wird_abgewiesen_und_loescht_nichts(): void {
        $this->resetAfterTest();

        $admin = $this->getDataGenerator()->create_user();
        $lernende = [];
        for ($i = 0; $i < 5; $i++) {
            $person = $this->getDataGenerator()->create_user(['email' => "person{$i}@firma.ch"]);
            $this->lege_aktive_zuordnung_an($person);
            $lernende[] = $person;
        }

        $vollstaendigezeilen = array_map(
            static fn ($p) => "{$p->email};4;2027-W15;2027-W16",
            $lernende
        );
        $vollstaendigescsv = "email;block;kw_von;kw_bis\n" . implode("\n", $vollstaendigezeilen) . "\n";

        $service = new import_service();
        $erstesergebnis = $service->verarbeiten($vollstaendigescsv, 'upload', (int) $admin->id);
        $this->assertSame(5, $erstesergebnis['personen_verarbeitet']);
        $this->assertCount(5, einsatz::get_records([]));

        // Zweite Lieferung enthaelt nur noch eine Person - deutlicher
        // Rueckgang, sollte abgewiesen werden.
        $unvollstaendigescsv = "email;block;kw_von;kw_bis\nperson0@firma.ch;4;2027-W20;2027-W21\n";
        $zweitesergebnis = $service->verarbeiten($unvollstaendigescsv, 'upload', (int) $admin->id);

        $this->assertSame('abgewiesen', $zweitesergebnis['status']);
        // Bestehende Einsaetze aller fuenf Personen bleiben unangetastet.
        $this->assertCount(5, einsatz::get_records([]));
    }

    /**
     * Derselbe Rueckgang, aber mit rueckgang_bestaetigt=true - wird
     * verarbeitet statt abgewiesen.
     */
    public function test_bestaetigter_rueckgang_wird_verarbeitet(): void {
        $this->resetAfterTest();

        $admin = $this->getDataGenerator()->create_user();
        for ($i = 0; $i < 5; $i++) {
            $person = $this->getDataGenerator()->create_user(['email' => "person{$i}@firma.ch"]);
            $this->lege_aktive_zuordnung_an($person);
        }

        $vollstaendigescsv = "email;block;kw_von;kw_bis\n"
            . implode("\n", array_map(static fn ($i) => "person{$i}@firma.ch;4;2027-W15;2027-W16", range(0, 4))) . "\n";

        $service = new import_service();
        $service->verarbeiten($vollstaendigescsv, 'upload', (int) $admin->id);

        $unvollstaendigescsv = "email;block;kw_von;kw_bis\nperson0@firma.ch;4;2027-W20;2027-W21\n";
        $ergebnis = $service->verarbeiten($unvollstaendigescsv, 'upload', (int) $admin->id, false, true);

        $this->assertSame(1, $ergebnis['personen_verarbeitet']);
        $this->assertNotSame('abgewiesen', $ergebnis['status']);
    }

    /**
     * Abnahmekriterium: der Testlauf schreibt nachweislich nichts.
     */
    public function test_testlauf_schreibt_nichts(): void {
        $this->resetAfterTest();

        $admin = $this->getDataGenerator()->create_user();
        $lernende = $this->getDataGenerator()->create_user(['email' => 'anna.muster@firma.ch']);
        $this->lege_aktive_zuordnung_an($lernende);

        $csv = "email;block;kw_von;kw_bis\nanna.muster@firma.ch;4;2027-W15;2027-W16\n";

        $ergebnis = (new import_service())->verarbeiten($csv, 'upload', (int) $admin->id, true);

        $this->assertSame(1, $ergebnis['personen_verarbeitet']);
        // Im Ergebnis vorhergesagt ...
        $this->assertSame(1, $ergebnis['einsaetze_erzeugt']);
        $this->assertCount(0, einsatz::get_records([])); // ... aber nicht geschrieben.
        $this->assertCount(0, plan_import::get_records([]));
        $this->assertEmpty(block::get_record(['nummer' => '4']));
    }

    /**
     * Eine Lieferung deckt nur einen Planungszeitraum ab, nicht die ganze
     * Lehrzeit: Einsaetze ausserhalb dieses Zeitraums bleiben erhalten,
     * damit sich die Historie ueber mehrere Lieferungen ergaenzt. Die
     * Einsaetze einer nicht gelieferten Person werden ohnehin nie beruehrt.
     */
    public function test_zweiter_import_ersetzt_nur_den_gelieferten_zeitraum(): void {
        $this->resetAfterTest();

        $admin = $this->getDataGenerator()->create_user();
        $anna = $this->getDataGenerator()->create_user(['email' => 'anna@firma.ch']);
        $beat = $this->getDataGenerator()->create_user(['email' => 'beat@firma.ch']);
        $this->lege_aktive_zuordnung_an($anna);
        $this->lege_aktive_zuordnung_an($beat);

        $service = new import_service();
        $service->verarbeiten(
            "email;block;kw_von;kw_bis\nanna@firma.ch;4;2027-W15;2027-W16\nbeat@firma.ch;4;2027-W15;2027-W16\n",
            'upload',
            (int) $admin->id
        );
        $this->assertCount(1, einsatz::get_records(['userid' => (int) $anna->id]));

        // Neue Lieferung fuer Anna allein, spaeterer Zeitraum ohne
        // Ueberschneidung. Nur noch eine von zuvor zwei Personen wuerde
        // sonst den Vollstaendigkeitsschutz ausloesen - hier nicht
        // Testgegenstand.
        $service->verarbeiten(
            "email;block;kw_von;kw_bis\nanna@firma.ch;7;2027-W20;2027-W21\n",
            'upload',
            (int) $admin->id,
            false,
            true
        );

        $wochen = array_map(
            static fn (einsatz $eintrag): string => $eintrag->get('kw_von'),
            array_values(einsatz::get_records(['userid' => (int) $anna->id], 'von'))
        );
        $this->assertSame(['2027-W15', '2027-W20'], $wochen);

        // Beats Einsatz aus dem ersten Lauf bleibt unangetastet.
        $this->assertCount(1, einsatz::get_records(['userid' => (int) $beat->id]));
    }

    /**
     * Innerhalb ihres Zeitraums bleibt die Lieferung massgebend: ein
     * bestehender Einsatz, der hineinreicht, wird ersetzt und nicht neben
     * den neuen gestellt.
     */
    public function test_lieferung_ersetzt_einsaetze_im_gelieferten_zeitraum(): void {
        $this->resetAfterTest();

        $admin = $this->getDataGenerator()->create_user();
        $anna = $this->getDataGenerator()->create_user(['email' => 'anna@firma.ch']);
        $this->lege_aktive_zuordnung_an($anna);

        $service = new import_service();
        $service->verarbeiten(
            "email;block;kw_von;kw_bis\nanna@firma.ch;4;2027-W15;2027-W20\n",
            'upload',
            (int) $admin->id
        );

        // Ueberschneidet den bestehenden Einsatz ab W18.
        $service->verarbeiten(
            "email;block;kw_von;kw_bis\nanna@firma.ch;7;2027-W18;2027-W22\n",
            'upload',
            (int) $admin->id
        );

        $einsaetze = einsatz::get_records(['userid' => (int) $anna->id]);
        $this->assertCount(1, $einsaetze);
        $this->assertSame('2027-W18', reset($einsaetze)->get('kw_von'));
    }

    public function test_kopfzeilenfehler_ergibt_fehlgeschlagen_und_schreibt_nichts(): void {
        $this->resetAfterTest();

        $admin = $this->getDataGenerator()->create_user();
        $csv = "spalte1;spalte2\nx;y\n";

        $ergebnis = (new import_service())->verarbeiten($csv, 'upload', (int) $admin->id);

        $this->assertSame('fehlgeschlagen', $ergebnis['status']);
        $this->assertCount(1, plan_import::get_records([])); // Fehlschlag wird protokolliert ...
        $this->assertCount(0, einsatz::get_records([])); // ... aber nichts an Einsaetzen erzeugt.
    }
}

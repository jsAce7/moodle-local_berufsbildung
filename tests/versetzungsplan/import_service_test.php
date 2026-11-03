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
use local_berufsbildung\persistent\einsatz_aenderung;
use local_berufsbildung\persistent\plan_import;
use stdClass;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_berufsbildung\versetzungsplan\import_service::class)]
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
     * Eine unbekannte Adresse mit mehreren Einsaetzen wird nur einmal gemeldet.
     */
    public function test_unbekannte_mailadresse_mit_mehreren_einsaetzen_wird_einmal_gemeldet(): void {
        $this->resetAfterTest();

        $admin = $this->getDataGenerator()->create_user();
        $lernende = $this->getDataGenerator()->create_user(['email' => 'anna.muster@firma.ch']);
        $this->lege_aktive_zuordnung_an($lernende);
        (new block(0, (object) ['nummer' => '4', 'name' => '4', 'ist_betrieb' => true, 'aktiv' => true]))->create();

        $csv = "email;block;kw_von;kw_bis\n"
            . "alt.name@firma.ch;4;2027-W15;2027-W16\n"
            . "alt.name@firma.ch;4;2027-W20;2027-W21\n"
            . "anna.muster@firma.ch;4;2027-W17;2027-W18\n";

        $ergebnis = (new import_service())->verarbeiten($csv, 'upload', (int) $admin->id);

        $this->assertSame('mit_warnungen', $ergebnis['status']);
        $this->assertCount(1, $ergebnis['protokoll']);
        $this->assertStringContainsString('alt.name@firma.ch', $ergebnis['protokoll'][0]);
    }

    /**
     * Der erste Import legt nur neue Einsaetze an. Der zweite verschiebt
     * einen: der bisherige entfaellt, der verschobene ist neu - beides im
     * Ergebnis und festgehalten beim zweiten Lauf.
     */
    public function test_uebernahme_haelt_geaenderte_einsaetze_fest(): void {
        $this->resetAfterTest();

        $admin = $this->getDataGenerator()->create_user();
        $lernende = $this->getDataGenerator()->create_user(['email' => 'anna.muster@firma.ch']);
        $this->lege_aktive_zuordnung_an($lernende);
        (new block(0, (object) ['nummer' => '4', 'name' => '4', 'ist_betrieb' => true, 'aktiv' => true]))->create();
        $service = new import_service();

        $erster = $service->verarbeiten(
            "email;block;kw_von;kw_bis\nanna.muster@firma.ch;4;2027-W15;2027-W16\n",
            'upload',
            (int) $admin->id
        );

        $this->assertCount(1, $erster['aenderungen']);
        $this->assertSame('neu', $erster['aenderungen'][0]['art']);
        $this->assertSame('anna.muster@firma.ch', $erster['aenderungen'][0]['email']);
        $this->assertSame((int) $lernende->id, $erster['aenderungen'][0]['userid']);
        $ersterlauf = (int) $service->letzter_erfolgreicher_import()->get('id');
        $this->assertCount(1, einsatz_aenderung::get_records(['importid' => $ersterlauf]));

        $zweiter = $service->verarbeiten(
            "email;block;kw_von;kw_bis\nanna.muster@firma.ch;4;2027-W16;2027-W18\n",
            'upload',
            (int) $admin->id
        );

        $erwartet = [
            ['art' => 'entfaellt', 'block' => '4', 'kw_von' => '2027-W15', 'kw_bis' => '2027-W16'],
            ['art' => 'neu', 'block' => '4', 'kw_von' => '2027-W16', 'kw_bis' => '2027-W18'],
        ];
        $nurvergleich = static fn (array $a): array => [
            'art' => $a['art'], 'block' => $a['block'], 'kw_von' => $a['kw_von'], 'kw_bis' => $a['kw_bis'],
        ];
        $this->assertSame($erwartet, array_map($nurvergleich, $zweiter['aenderungen']));

        $zweiterlauf = (int) $service->letzter_erfolgreicher_import()->get('id');
        $this->assertNotSame($ersterlauf, $zweiterlauf);
        $festgehalten = $service->aenderungen_fuer_import($zweiterlauf);
        $this->assertSame($erwartet, array_map($nurvergleich, $festgehalten));
        $this->assertSame([(int) $lernende->id], array_values(array_unique(array_column($festgehalten, 'userid'))));
    }

    /**
     * Wessen Einsaetze gleich bleiben, erscheint nicht im Protokoll.
     */
    public function test_unveraenderte_person_erscheint_nicht_im_aenderungsprotokoll(): void {
        $this->resetAfterTest();

        $admin = $this->getDataGenerator()->create_user();
        $anna = $this->getDataGenerator()->create_user(['email' => 'anna.muster@firma.ch']);
        $ben = $this->getDataGenerator()->create_user(['email' => 'ben.beispiel@firma.ch']);
        $this->lege_aktive_zuordnung_an($anna);
        $this->lege_aktive_zuordnung_an($ben);
        (new block(0, (object) ['nummer' => '4', 'name' => '4', 'ist_betrieb' => true, 'aktiv' => true]))->create();
        $service = new import_service();

        $service->verarbeiten(
            "email;block;kw_von;kw_bis\n"
                . "anna.muster@firma.ch;4;2027-W15;2027-W16\n"
                . "ben.beispiel@firma.ch;4;2027-W15;2027-W16\n",
            'upload',
            (int) $admin->id
        );
        $ergebnis = $service->verarbeiten(
            "email;block;kw_von;kw_bis\n"
                . "anna.muster@firma.ch;4;2027-W15;2027-W16\n"
                . "ben.beispiel@firma.ch;4;2027-W20;2027-W21\n",
            'upload',
            (int) $admin->id
        );

        $this->assertSame(['ben.beispiel@firma.ch'], array_values(array_unique(array_column($ergebnis['aenderungen'], 'email'))));
        $lauf = (int) $service->letzter_erfolgreicher_import()->get('id');
        $this->assertSame(0, einsatz_aenderung::count_records(['importid' => $lauf, 'userid' => (int) $anna->id]));
        $this->assertSame(2, einsatz_aenderung::count_records(['importid' => $lauf, 'userid' => (int) $ben->id]));
    }

    /**
     * Randfall der Jahresfrist fuer geaenderte Einsaetze: ein Eintrag genau
     * ein Jahr vor dem Stichtag bleibt, eine Sekunde aelter wird geloescht.
     */
    public function test_bereinige_verlauf_aenderungen_grenze_genau_ein_jahr(): void {
        global $DB;
        $this->resetAfterTest();

        $stichtag = mktime(12, 0, 0, 11, 3, 2026);
        $grenze = mktime(12, 0, 0, 11, 3, 2025);
        $ids = [];
        foreach ([$grenze, $grenze - 1, $stichtag] as $erstellt) {
            $aenderung = new einsatz_aenderung(0, (object) [
                'importid' => 1, 'userid' => 2, 'art' => einsatz_aenderung::NEU,
                'block' => '4', 'kw_von' => '2027-W15', 'kw_bis' => '2027-W16', 'von' => $stichtag,
            ]);
            $aenderung->create();
            $DB->set_field(einsatz_aenderung::TABLE, 'timecreated', $erstellt, ['id' => (int) $aenderung->get('id')]);
            $ids[$erstellt] = (int) $aenderung->get('id');
        }

        $ergebnis = (new import_service())->bereinige_verlauf($stichtag);

        $this->assertSame(1, $ergebnis['aenderungen']);
        $this->assertTrue(einsatz_aenderung::record_exists($ids[$grenze]));
        $this->assertFalse(einsatz_aenderung::record_exists($ids[$grenze - 1]));
        $this->assertTrue(einsatz_aenderung::record_exists($ids[$stichtag]));
    }

    /**
     * Randfall der Jahresfrist fuer den Protokolltext: genau ein Jahr alt
     * bleibt er, eine Sekunde aelter wird er geleert - der Lauf selbst
     * bleibt. Eine wartende Lieferung behaelt ihn in jedem Fall.
     */
    public function test_bereinige_verlauf_leert_alte_protokolle_ausser_wartende(): void {
        $this->resetAfterTest();

        $stichtag = mktime(12, 0, 0, 11, 3, 2026);
        $grenze = mktime(12, 0, 0, 11, 3, 2025);
        $faelle = [
            'grenze' => [$grenze, 'ok'],
            'alt' => [$grenze - 1, 'mit_warnungen'],
            'wartend' => [$grenze - 1, 'zur_freigabe'],
        ];
        $laeufe = [];
        foreach ($faelle as $name => [$zeitpunkt, $status]) {
            $lauf = new plan_import(0, (object) [
                'quelle' => 'upload', 'daten_hash' => str_repeat('a', 64), 'zeitpunkt' => $zeitpunkt,
                'ausgefuehrt_von' => 0, 'status' => $status,
                'protokoll' => 'Mailadresse alt.name@firma.ch keinem Moodle-Konto zugeordnet – übersprungen',
            ]);
            $lauf->create();
            $laeufe[$name] = $lauf;
        }

        $ergebnis = (new import_service())->bereinige_verlauf($stichtag);

        $this->assertSame(1, $ergebnis['protokolle']);
        foreach ($laeufe as $lauf) {
            $lauf->read();
        }
        $this->assertStringContainsString('alt.name@firma.ch', $laeufe['grenze']->get('protokoll'));
        $this->assertSame('', (string) $laeufe['alt']->get('protokoll'));
        $this->assertSame('mit_warnungen', $laeufe['alt']->get('status'));
        $this->assertStringContainsString('alt.name@firma.ch', $laeufe['wartend']->get('protokoll'));
    }

    /**
     * Der Testlauf nennt, was sich aendern wuerde, haelt aber nichts fest.
     */
    public function test_testlauf_nennt_aenderungen_ohne_sie_festzuhalten(): void {
        $this->resetAfterTest();

        $admin = $this->getDataGenerator()->create_user();
        $lernende = $this->getDataGenerator()->create_user(['email' => 'anna.muster@firma.ch']);
        $this->lege_aktive_zuordnung_an($lernende);

        $ergebnis = (new import_service())->verarbeiten(
            "email;block;kw_von;kw_bis\nanna.muster@firma.ch;4;2027-W15;2027-W16\n",
            'upload',
            (int) $admin->id,
            true
        );

        $this->assertCount(1, $ergebnis['aenderungen']);
        $this->assertSame('neu', $ergebnis['aenderungen'][0]['art']);
        $this->assertSame(0, einsatz_aenderung::count_records());
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
     * Legt Lernende person0@firma.ch, person1@firma.ch, ... mit laufender
     * Zuordnung an.
     *
     * @param int $anzahl
     * @return stdClass[]
     */
    private function lege_lernende_an(int $anzahl): array {
        $lernende = [];
        for ($i = 0; $i < $anzahl; $i++) {
            $person = $this->getDataGenerator()->create_user(['email' => "person{$i}@firma.ch"]);
            $this->lege_aktive_zuordnung_an($person);
            $lernende[] = $person;
        }

        return $lernende;
    }

    /**
     * Eine Lieferung mit je einem Einsatz fuer person0 bis person($anzahl - 1).
     *
     * @param int $anzahl
     * @param string $kwvon
     * @param string $kwbis
     * @return string
     */
    private function lieferung(int $anzahl, string $kwvon = '2027-W15', string $kwbis = '2027-W16'): string {
        return "email;block;kw_von;kw_bis\n"
            . implode("\n", array_map(static fn ($i) => "person{$i}@firma.ch;4;{$kwvon};{$kwbis}", range(0, $anzahl - 1)))
            . "\n";
    }

    /**
     * Bringt den Bestand auf fuenf verarbeitete Personen und schickt dann
     * eine Lieferung mit nur noch einer - deutlicher Rueckgang.
     *
     * @param import_service $service
     * @param int $ausgefuehrtvon
     * @return array Ergebnis der zweiten Lieferung
     */
    private function liefere_mit_rueckgang(import_service $service, int $ausgefuehrtvon): array {
        $this->lege_lernende_an(5);
        $erstesergebnis = $service->verarbeiten($this->lieferung(5), 'webservice', $ausgefuehrtvon);
        $this->assertSame(5, $erstesergebnis['personen_verarbeitet']);

        return $service->verarbeiten($this->lieferung(1, '2027-W20', '2027-W21'), 'webservice', $ausgefuehrtvon);
    }

    /**
     * Die eine wartende Lieferung.
     *
     * @return plan_import
     */
    private function wartende(): plan_import {
        $wartende = plan_import::get_records(['status' => 'zur_freigabe']);
        $this->assertCount(1, $wartende);

        return reset($wartende);
    }

    /**
     * Abnahmekriterium: eine Lieferung mit deutlich weniger verarbeiteten
     * Personen wird nicht abgewiesen, sondern zurueckgehalten, der
     * Administration gemeldet und loescht nichts.
     */
    public function test_deutlicher_rueckgang_wartet_auf_freigabe_und_loescht_nichts(): void {
        $this->resetAfterTest();

        $dienstkonto = $this->getDataGenerator()->create_user();
        $sink = $this->redirectMessages();

        $ergebnis = $this->liefere_mit_rueckgang(new import_service(), (int) $dienstkonto->id);

        $this->assertSame('zur_freigabe', $ergebnis['status']);
        $this->assertSame(0, $ergebnis['einsaetze_erzeugt']);
        // Bestehende Einsaetze aller fuenf Personen bleiben unangetastet.
        $this->assertCount(5, einsatz::get_records([]));

        // Die Lieferung ist gespeichert, damit sie ohne erneutes Senden
        // freigegeben werden kann.
        $wartend = $this->wartende();
        $this->assertSame($this->lieferung(1, '2027-W20', '2027-W21'), $wartend->get('csvdaten'));
        $this->assertSame(1, (int) $wartend->get('personen_verarbeitet'));

        // Gemeldet an die Site-Administration (im Test nur admin).
        $nachrichten = $sink->get_messages();
        $this->assertCount(1, $nachrichten);
        $this->assertSame((int) get_admin()->id, (int) $nachrichten[0]->useridto);
        $this->assertSame(import_service::NACHRICHT_FREIGABE, $nachrichten[0]->eventtype);
    }

    /**
     * Gemeldet wird auch, wer die Capability ueber eine Rolle hat - nicht
     * aber, wer die Lieferung selbst von Hand hochgeladen hat.
     */
    public function test_benachrichtigt_freigabeberechtigte_aber_nicht_die_hochladende_person(): void {
        $this->resetAfterTest();

        $verwaltung = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability(import_service::CAPABILITY_FREIGABE, CAP_ALLOW, $roleid, \context_system::instance()->id);
        role_assign($roleid, (int) $verwaltung->id, \context_system::instance()->id);
        $sink = $this->redirectMessages();

        $this->liefere_mit_rueckgang(new import_service(), (int) get_admin()->id);

        $empfaenger = array_map(static fn ($n): int => (int) $n->useridto, $sink->get_messages());
        $this->assertSame([(int) $verwaltung->id], $empfaenger);
    }

    /**
     * Das Skript liefert woechentlich dieselbe Datei: sie wird nur einmal
     * zurueckgehalten und nur einmal gemeldet.
     */
    public function test_dieselbe_lieferung_wird_nur_einmal_gemeldet(): void {
        $this->resetAfterTest();

        $dienstkonto = $this->getDataGenerator()->create_user();
        $sink = $this->redirectMessages();
        $service = new import_service();

        $this->liefere_mit_rueckgang($service, (int) $dienstkonto->id);
        $wiederholt = $service->verarbeiten($this->lieferung(1, '2027-W20', '2027-W21'), 'webservice', (int) $dienstkonto->id);

        $this->assertSame('zur_freigabe', $wiederholt['status']);
        $this->wartende();
        $this->assertCount(1, $sink->get_messages());
    }

    /**
     * Randfall der Schwelle (Standard 20 %): von fuenf Personen duerfen es
     * vier werden (ceil(5 * 0.8) = 4), erst drei gelten als Rueckgang. Die
     * zurueckgehaltene Lieferung zaehlt nicht als erfolgreich, beide werden
     * also an denselben fuenf Personen gemessen.
     */
    public function test_rueckgang_genau_an_der_schwelle_wird_verarbeitet_einer_mehr_nicht(): void {
        $this->resetAfterTest();

        $dienstkonto = $this->getDataGenerator()->create_user();
        $this->lege_lernende_an(5);
        $service = new import_service();
        $service->verarbeiten($this->lieferung(5), 'webservice', (int) $dienstkonto->id);

        $darunter = $service->verarbeiten($this->lieferung(3, '2027-W20', '2027-W21'), 'webservice', (int) $dienstkonto->id);
        $this->assertSame('zur_freigabe', $darunter['status']);

        $genauan = $service->verarbeiten($this->lieferung(4, '2027-W20', '2027-W21'), 'webservice', (int) $dienstkonto->id);
        $this->assertSame('ok', $genauan['status']);
        $this->assertSame(4, $genauan['personen_verarbeitet']);
    }

    /**
     * Die Freigabe uebernimmt die gespeicherte Lieferung und haelt fest,
     * wer entschieden hat.
     */
    public function test_freigabe_uebernimmt_die_zurueckgehaltene_lieferung(): void {
        $this->resetAfterTest();

        $dienstkonto = $this->getDataGenerator()->create_user();
        $verwaltung = $this->getDataGenerator()->create_user();
        $service = new import_service();
        $this->liefere_mit_rueckgang($service, (int) $dienstkonto->id);
        $wartend = $this->wartende();

        $ergebnis = $service->freigeben((int) $wartend->get('id'), (int) $verwaltung->id);

        $this->assertSame(1, $ergebnis['personen_verarbeitet']);
        $this->assertSame(1, $ergebnis['einsaetze_erzeugt']);
        $person0 = \core_user::get_user_by_email('person0@firma.ch');
        $wochen = array_map(
            static fn (einsatz $eintrag): string => $eintrag->get('kw_von'),
            array_values(einsatz::get_records(['userid' => (int) $person0->id], 'von'))
        );
        $this->assertSame(['2027-W15', '2027-W20'], $wochen);

        $wartend->read();
        $this->assertSame('freigegeben', $wartend->get('status'));
        $this->assertSame((int) $verwaltung->id, (int) $wartend->get('entschieden_von'));
        $this->assertNull($wartend->get('csvdaten'));
        $this->assertSame(1, (int) $service->letzter_erfolgreicher_import()->get('personen_verarbeitet'));
        $this->assertSame((int) $verwaltung->id, (int) $service->letzter_erfolgreicher_import()->get('ausgefuehrt_von'));
    }

    /**
     * Verwerfen laesst den Bestand stehen und loescht die gespeicherte
     * Lieferung.
     */
    public function test_verwerfen_laesst_den_bestand_unangetastet(): void {
        $this->resetAfterTest();

        $dienstkonto = $this->getDataGenerator()->create_user();
        $verwaltung = $this->getDataGenerator()->create_user();
        $service = new import_service();
        $this->liefere_mit_rueckgang($service, (int) $dienstkonto->id);
        $wartend = $this->wartende();

        $service->verwerfen((int) $wartend->get('id'), (int) $verwaltung->id);

        $this->assertCount(5, einsatz::get_records([]));
        $wartend->read();
        $this->assertSame('verworfen', $wartend->get('status'));
        $this->assertNull($wartend->get('csvdaten'));

        $this->expectException(\moodle_exception::class);
        $service->freigeben((int) $wartend->get('id'), (int) $verwaltung->id);
    }

    /**
     * Eine neuere, regulaer verarbeitete Lieferung ueberholt die wartende.
     * Sonst koennte eine spaete Freigabe den neueren Plan mit dem aelteren
     * ueberschreiben.
     */
    public function test_neuere_lieferung_ueberholt_die_wartende(): void {
        $this->resetAfterTest();

        $dienstkonto = $this->getDataGenerator()->create_user();
        $service = new import_service();
        $this->liefere_mit_rueckgang($service, (int) $dienstkonto->id);
        $wartend = $this->wartende();

        $service->verarbeiten($this->lieferung(5, '2027-W30', '2027-W31'), 'webservice', (int) $dienstkonto->id);

        $wartend->read();
        $this->assertSame('ueberholt', $wartend->get('status'));
        $this->assertNull($wartend->get('csvdaten'));

        $this->expectException(\moodle_exception::class);
        $service->freigeben((int) $wartend->get('id'), (int) get_admin()->id);
    }

    /**
     * Fuer die Pruefung: wer beim letzten erfolgreichen Import dabei war und
     * jetzt fehlt.
     */
    public function test_pruefung_nennt_die_fehlenden_personen(): void {
        $this->resetAfterTest();

        $dienstkonto = $this->getDataGenerator()->create_user();
        $service = new import_service();
        $this->liefere_mit_rueckgang($service, (int) $dienstkonto->id);

        $pruefung = $service->pruefe_wartende($this->wartende());

        $this->assertSame(1, $pruefung['personen_verarbeitet']);
        $this->assertSame(1, $pruefung['einsaetze_erzeugt']);
        $fehlend = array_map(
            static fn (int $userid): string => \core_user::get_user($userid)->email,
            $pruefung['fehlende_personen']
        );
        sort($fehlend);
        $this->assertSame(['person1@firma.ch', 'person2@firma.ch', 'person3@firma.ch', 'person4@firma.ch'], $fehlend);
        // Die Pruefung schreibt nichts.
        $this->assertCount(5, einsatz::get_records([]));
    }

    /**
     * Fuer die Pruefung: je gelieferter Person nur, was sich aendert. Ein
     * aufgeteilter Einsatz erscheint als entfallen und zweimal neu, eine
     * unveraenderte Person nur in der Zahl.
     */
    public function test_pruefung_zeigt_nur_die_geaenderten_einsaetze(): void {
        $this->resetAfterTest();

        $dienstkonto = $this->getDataGenerator()->create_user();
        $lernende = $this->lege_lernende_an(5);
        $service = new import_service();
        $service->verarbeiten(
            "email;block;kw_von;kw_bis\n"
                . "person0@firma.ch;4;2027-W15;2027-W20\n"
                . "person1@firma.ch;4;2027-W15;2027-W20\n"
                . "person2@firma.ch;4;2027-W15;2027-W20\n"
                . "person3@firma.ch;4;2027-W15;2027-W20\n"
                . "person4@firma.ch;4;2027-W15;2027-W20\n",
            'webservice',
            (int) $dienstkonto->id
        );

        // Zwei von fuenf Personen: wird zurueckgehalten.
        $ergebnis = $service->verarbeiten(
            "email;block;kw_von;kw_bis\n"
                . "person0@firma.ch;7;2027-W15;2027-W17\n"
                . "person0@firma.ch;4;2027-W18;2027-W20\n"
                . "person1@firma.ch;4;2027-W15;2027-W20\n",
            'webservice',
            (int) $dienstkonto->id
        );
        $this->assertSame('zur_freigabe', $ergebnis['status']);

        $pruefung = $service->pruefe_wartende($this->wartende());

        $this->assertSame([(int) $lernende[0]->id], array_keys($pruefung['aenderungen']));
        $this->assertSame(1, $pruefung['personen_unveraendert']);
        $ohnebeginn = static fn (array $e): string => "{$e['block']} {$e['kw_von']}-{$e['kw_bis']}";
        $this->assertSame(
            ['4 2027-W15-2027-W20'],
            array_map($ohnebeginn, $pruefung['aenderungen'][(int) $lernende[0]->id]['entfaellt'])
        );
        $this->assertSame(
            ['7 2027-W15-2027-W17', '4 2027-W18-2027-W20'],
            array_map($ohnebeginn, $pruefung['aenderungen'][(int) $lernende[0]->id]['neu'])
        );
        // Die Pruefung schreibt nichts.
        $this->assertCount(5, einsatz::get_records([]));
    }

    /**
     * Derselbe Rueckgang, aber mit rueckgang_bestaetigt=true - wird
     * verarbeitet statt zurueckgehalten.
     */
    public function test_bestaetigter_rueckgang_wird_verarbeitet(): void {
        $this->resetAfterTest();

        $admin = $this->getDataGenerator()->create_user();
        $this->lege_lernende_an(5);

        $service = new import_service();
        $service->verarbeiten($this->lieferung(5), 'upload', (int) $admin->id);

        $ergebnis = $service->verarbeiten($this->lieferung(1, '2027-W20', '2027-W21'), 'upload', (int) $admin->id, false, true);

        $this->assertSame(1, $ergebnis['personen_verarbeitet']);
        $this->assertNotSame('zur_freigabe', $ergebnis['status']);
        $this->assertCount(0, plan_import::get_records(['status' => 'zur_freigabe']));
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

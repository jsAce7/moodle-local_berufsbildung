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
 * Tests fuer den Nachweis-Collector.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\nachweis;

use advanced_testcase;
use local_berufsbildung\persistent\zuordnung;
use moodle_exception;

defined('MOODLE_INTERNAL') || die();

// Die Test-Provider liegen als Fixtures daneben: eine Klasse je Datei, und
// tests/fixtures/ wird nicht autogeladen.
require_once(__DIR__ . '/../fixtures/collector_test_provider.php');
require_once(__DIR__ . '/../fixtures/collector_test_erfassbarer_provider.php');
require_once(__DIR__ . '/../fixtures/collector_test_hinweis_provider.php');
require_once(__DIR__ . '/../fixtures/collector_test_zusammenfassung_provider.php');
require_once(__DIR__ . '/../fixtures/collector_test_ausstehend_provider.php');
require_once(__DIR__ . '/../fixtures/collector_test_zustaendigen_provider.php');
require_once(__DIR__ . '/../fixtures/collector_test_schnellaktion_provider.php');

/**
 * Tests fuer collector.
 *
 * @covers \local_berufsbildung\nachweis\collector
 */
final class collector_test extends advanced_testcase {
    /**
     * Legt eine Zuordnung zwischen Berufsbildner/in und Lernender an.
     *
     * @param int $berufsbildnerid
     * @param int $lernendeid
     */
    private function lege_zuordnung_an(int $berufsbildnerid, int $lernendeid): void {
        $this->getDataGenerator()->get_plugin_generator('local_berufsbildung')->create_zuordnung([
            'berufsbildnerid' => $berufsbildnerid,
            'lernendeid' => $lernendeid,
            'gueltig_von' => time() - YEARSECS,
        ]);
    }

    public function test_sammelt_nachweise_von_allen_registrierten_providern(): void {
        $this->resetAfterTest();

        $berufsbildner = $this->getDataGenerator()->create_user();
        $lernende = $this->getDataGenerator()->create_user();
        $this->lege_zuordnung_an((int) $berufsbildner->id, (int) $lernende->id);

        $providera = new collector_test_provider([
            new nachweis('a', 'Erster', time() - 100, null, null, null),
        ]);
        $providerb = new collector_test_provider([
            new nachweis('b', 'Zweiter', time(), null, null, null),
        ]);

        $collector = new collector([$providera, $providerb]);
        $nachweise = $collector->get_nachweise((int) $berufsbildner->id, (int) $lernende->id, 0, time() + 1);

        $this->assertCount(2, $nachweise);
        $this->assertTrue($providera->wurdeaufgerufen);
        $this->assertTrue($providerb->wurdeaufgerufen);
    }

    public function test_sortiert_neueste_zuerst(): void {
        $this->resetAfterTest();

        $berufsbildner = $this->getDataGenerator()->create_user();
        $lernende = $this->getDataGenerator()->create_user();
        $this->lege_zuordnung_an((int) $berufsbildner->id, (int) $lernende->id);

        $provider = new collector_test_provider([
            new nachweis('a', 'Aelter', time() - 1000, null, null, null),
            new nachweis('a', 'Neuer', time(), null, null, null),
        ]);

        $collector = new collector([$provider]);
        $nachweise = $collector->get_nachweise((int) $berufsbildner->id, (int) $lernende->id, 0, time() + 1);

        $this->assertSame('Neuer', $nachweise[0]->bezeichnung);
        $this->assertSame('Aelter', $nachweise[1]->bezeichnung);
    }

    /**
     * Randfall: eine fremde Person ohne Zuordnung darf gar nicht erst bei
     * den Providern nachfragen - die Pruefung passiert vorher.
     */
    public function test_fremde_person_bekommt_exception_und_provider_wird_nicht_gefragt(): void {
        $this->resetAfterTest();

        $lernende = $this->getDataGenerator()->create_user();
        $fremder = $this->getDataGenerator()->create_user();
        $provider = new collector_test_provider([new nachweis('a', 'X', time(), null, null, null)]);
        $collector = new collector([$provider]);

        $this->expectException(moodle_exception::class);

        try {
            $collector->get_nachweise((int) $fremder->id, (int) $lernende->id, 0, time());
        } finally {
            $this->assertFalse($provider->wurdeaufgerufen);
        }
    }

    public function test_eigene_person_braucht_keine_zuordnung(): void {
        $this->resetAfterTest();

        $lernende = $this->getDataGenerator()->create_user();
        $provider = new collector_test_provider([new nachweis('a', 'X', time(), null, null, null)]);
        $collector = new collector([$provider]);

        $nachweise = $collector->get_nachweise((int) $lernende->id, (int) $lernende->id, 0, time() + 1);

        $this->assertCount(1, $nachweise);
    }

    public function test_historische_semesteransicht_prueft_zustaendigkeit_am_semesterende(): void {
        $this->resetAfterTest();

        $berufsbildner = $this->getDataGenerator()->create_user();
        $lernende = $this->getDataGenerator()->create_user();
        $semesterende = time() - WEEKSECS;
        $this->getDataGenerator()->get_plugin_generator('local_berufsbildung')->create_zuordnung([
            'berufsbildnerid' => (int) $berufsbildner->id,
            'lernendeid' => (int) $lernende->id,
            'gueltig_von' => $semesterende - YEARSECS,
            'gueltig_bis' => $semesterende,
        ]);
        $provider = new collector_test_provider([new nachweis('uekkn', 'üK 2', $semesterende, '5.0', null, null)]);

        $nachweise = (new collector([$provider]))->get_nachweise(
            (int) $berufsbildner->id,
            (int) $lernende->id,
            $semesterende - (30 * DAYSECS),
            $semesterende,
            $semesterende
        );

        $this->assertCount(1, $nachweise);
        $this->assertTrue($provider->wurdeaufgerufen);
    }

    public function test_get_erfassen_aktionen_liefert_aktion_von_erfassbarer_quelle(): void {
        $this->resetAfterTest();

        $lernende = $this->getDataGenerator()->create_user();

        $provider = new collector_test_erfassbarer_provider('/local/test/edit.php');
        $aktionen = (new collector([$provider]))->get_erfassen_aktionen((int) $lernende->id, (int) $lernende->id);

        $this->assertCount(1, $aktionen);
        $this->assertSame('testerfassbar', $aktionen[0]->quellekey);
        $this->assertSame('Neuer Test-Eintrag', $aktionen[0]->label);
        $this->assertStringContainsString('/local/test/edit.php', $aktionen[0]->url);
    }

    /**
     * Ein normaler `provider` ohne `erfassbare_quelle` liefert keine Aktion
     * und wird dafuer auch gar nicht erst gefragt.
     */
    public function test_get_erfassen_aktionen_ignoriert_provider_ohne_erfassbare_quelle(): void {
        $this->resetAfterTest();

        $lernende = $this->getDataGenerator()->create_user();

        $provider = new collector_test_provider();
        $aktionen = (new collector([$provider]))->get_erfassen_aktionen((int) $lernende->id, (int) $lernende->id);

        $this->assertSame([], $aktionen);
    }

    /**
     * Liefert die Quelle keine URL (z.B. weil die Berechtigung fehlt), gibt
     * es dafuer auch keine Aktion.
     */
    public function test_get_erfassen_aktionen_ohne_url_ergibt_keine_aktion(): void {
        $this->resetAfterTest();

        $lernende = $this->getDataGenerator()->create_user();

        $provider = new collector_test_erfassbarer_provider(null);
        $aktionen = (new collector([$provider]))->get_erfassen_aktionen((int) $lernende->id, (int) $lernende->id);

        $this->assertSame([], $aktionen);
        $this->assertTrue($provider->erfassenwurdeaufgerufen);
    }

    /**
     * Randfall: eine fremde Person bekommt nie eine Erfassen-Aktion fuer
     * jemand anderen - unabhaengig von einer allfaelligen Zustaendigkeit.
     * Anders als bei get_nachweise() wird der Provider dafuer gar nicht erst
     * gefragt (Architekturregel 7).
     */
    public function test_get_erfassen_aktionen_fuer_fremde_person_bleibt_leer(): void {
        $this->resetAfterTest();

        $lernende = $this->getDataGenerator()->create_user();
        $fremder = $this->getDataGenerator()->create_user();

        $provider = new collector_test_erfassbarer_provider();
        $aktionen = (new collector([$provider]))->get_erfassen_aktionen((int) $fremder->id, (int) $lernende->id);

        $this->assertSame([], $aktionen);
        $this->assertFalse($provider->erfassenwurdeaufgerufen);
    }

    /**
     * Eine Quelle ohne `quelle_mit_hinweis` liefert eine Aktion ohne
     * Hinweis - der Hinweis ist optional, nicht Pflicht.
     */
    public function test_get_erfassen_aktionen_ohne_quelle_mit_hinweis_hat_keinen_hinweis(): void {
        $this->resetAfterTest();

        $lernende = $this->getDataGenerator()->create_user();

        $provider = new collector_test_erfassbarer_provider();
        $aktionen = (new collector([$provider]))->get_erfassen_aktionen((int) $lernende->id, (int) $lernende->id);

        $this->assertCount(1, $aktionen);
        $this->assertNull($aktionen[0]->hinweis);
    }

    public function test_get_erfassen_aktionen_uebernimmt_den_hinweis_der_quelle(): void {
        $this->resetAfterTest();

        $lernende = $this->getDataGenerator()->create_user();

        $provider = new collector_test_hinweis_provider('/local/test/edit.php', 'Naechster Eintrag faellig bis morgen');
        $aktionen = (new collector([$provider]))->get_erfassen_aktionen((int) $lernende->id, (int) $lernende->id);

        $this->assertCount(1, $aktionen);
        $this->assertSame('Naechster Eintrag faellig bis morgen', $aktionen[0]->hinweis);
        $this->assertTrue($provider->hinweiswurdeaufgerufen);
    }

    /**
     * Eine Quelle darf `quelle_mit_hinweis` implementieren und trotzdem
     * nichts zu sagen haben - dann steht unter der Schaltflaeche nichts.
     */
    public function test_get_erfassen_aktionen_mit_leerem_hinweis_bleibt_ohne_hinweis(): void {
        $this->resetAfterTest();

        $lernende = $this->getDataGenerator()->create_user();

        $provider = new collector_test_hinweis_provider('/local/test/edit.php', null);
        $aktionen = (new collector([$provider]))->get_erfassen_aktionen((int) $lernende->id, (int) $lernende->id);

        $this->assertCount(1, $aktionen);
        $this->assertNull($aktionen[0]->hinweis);
        $this->assertTrue($provider->hinweiswurdeaufgerufen);
    }

    /**
     * Ohne Erfassungs-URL gibt es keine Aktion - und damit auch keinen
     * Grund, die Quelle nach einem Hinweis zu fragen.
     */
    public function test_get_erfassen_aktionen_ohne_url_fragt_nicht_nach_dem_hinweis(): void {
        $this->resetAfterTest();

        $lernende = $this->getDataGenerator()->create_user();

        $provider = new collector_test_hinweis_provider(null);
        $aktionen = (new collector([$provider]))->get_erfassen_aktionen((int) $lernende->id, (int) $lernende->id);

        $this->assertSame([], $aktionen);
        $this->assertFalse($provider->hinweiswurdeaufgerufen);
    }

    /**
     * Jede Quelle fasst nur ihre eigenen Nachweise zusammen - der ueK-Schnitt
     * darf keinen Lerndoku-Eintrag mitzaehlen. Eine Quelle ohne
     * quelle_mit_zusammenfassung bleibt aussen vor.
     */
    public function test_get_zusammenfassungen_gibt_jeder_quelle_nur_ihre_nachweise(): void {
        $uek = new collector_test_zusammenfassung_provider('uek', 'Schnitt 5.5');
        $ohne = new collector_test_provider();

        $nachweise = [
            new nachweis('uek', 'üK 2', 2000, '5.5', null, null),
            new nachweis('test', 'Eintrag', 1500, null, null, null),
            new nachweis('uek', 'üK 1', 1000, '5.0', null, null),
        ];

        $zusammenfassungen = (new collector([$uek, $ohne]))->get_zusammenfassungen($nachweise);

        $this->assertSame(['uek' => 'Schnitt 5.5'], $zusammenfassungen);
        $this->assertNotNull($uek->erhaltene);
        $this->assertCount(2, $uek->erhaltene);
        foreach ($uek->erhaltene as $erhalten) {
            $this->assertSame('uek', $erhalten->quellekey);
        }
    }

    /**
     * Randfall: eine Quelle ohne Nachweise wird nicht gefragt, und eine, die
     * nichts zu sagen hat, erscheint nicht im Ergebnis.
     */
    public function test_get_zusammenfassungen_ohne_nachweise_oder_ohne_aussage_bleibt_leer(): void {
        $ohnenachweise = new collector_test_zusammenfassung_provider('uek', 'Schnitt 5.5');
        $ohneaussage = new collector_test_zusammenfassung_provider('bericht', null);

        $zusammenfassungen = (new collector([$ohnenachweise, $ohneaussage]))->get_zusammenfassungen([
            new nachweis('bericht', 'Bildungsbericht 1', 1000, null, null, null),
        ]);

        $this->assertSame([], $zusammenfassungen);
        $this->assertNull($ohnenachweise->erhaltene);
        $this->assertNotNull($ohneaussage->erhaltene);
    }

    /**
     * Die zustaendige Berufsbildner/in bekommt das Ausstehende je Quelle;
     * eine Quelle ohne quelle_mit_ausstehenden und eine ohne Eintraege
     * erscheinen nicht.
     */
    public function test_get_ausstehende_liefert_je_quelle(): void {
        $this->resetAfterTest();

        $berufsbildner = $this->getDataGenerator()->create_user();
        $lernende = $this->getDataGenerator()->create_user();
        $this->lege_zuordnung_an((int) $berufsbildner->id, (int) $lernende->id);

        $mitsoll = new collector_test_ausstehend_provider([
            new ausstehend('soll', 'üK 4: Steuerungen', 'noch nicht eingeplant'),
        ]);
        $leer = new collector_test_zusammenfassung_provider('leer', null);

        $ergebnis = (new collector([$mitsoll, $leer, new collector_test_provider()]))
            ->get_ausstehende((int) $berufsbildner->id, (int) $lernende->id);

        $this->assertSame(['soll'], array_keys($ergebnis));
        $this->assertSame('üK 4: Steuerungen', $ergebnis['soll'][0]->bezeichnung);
    }

    /**
     * Auch das Soll einer Person ist nur fuer sie selbst und ihre
     * zustaendige Berufsbildner/in: eine fremde Person bekommt eine
     * Exception, und die Quelle wird gar nicht erst gefragt.
     */
    public function test_get_ausstehende_fuer_fremde_person_fragt_die_quelle_nicht(): void {
        $this->resetAfterTest();

        $fremder = $this->getDataGenerator()->create_user();
        $lernende = $this->getDataGenerator()->create_user();

        $provider = new collector_test_ausstehend_provider([new ausstehend('soll', 'üK 4', 'offen')]);

        try {
            (new collector([$provider]))->get_ausstehende((int) $fremder->id, (int) $lernende->id);
            $this->fail('Eine fremde Person darf das Ausstehende nicht abfragen.');
        } catch (moodle_exception $e) {
            $this->assertSame('error:keinezustaendigkeit', $e->errorcode);
        }

        $this->assertFalse($provider->wurdeaufgerufen);
    }

    /**
     * Randfall am Stichtag: eine Zuordnung, die gestern geendet hat, gibt
     * heute keinen Zugriff mehr auf das Soll.
     */
    public function test_get_ausstehende_nach_beendeter_zuordnung_verweigert(): void {
        $this->resetAfterTest();

        $berufsbildner = $this->getDataGenerator()->create_user();
        $lernende = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->get_plugin_generator('local_berufsbildung')->create_zuordnung([
            'berufsbildnerid' => (int) $berufsbildner->id,
            'lernendeid' => (int) $lernende->id,
            'gueltig_von' => time() - YEARSECS,
            'gueltig_bis' => time() - DAYSECS,
        ]);

        $provider = new collector_test_ausstehend_provider([new ausstehend('soll', 'üK 4', 'offen')]);

        $this->expectException(moodle_exception::class);
        (new collector([$provider]))->get_ausstehende((int) $berufsbildner->id, (int) $lernende->id);
    }

    public function test_get_zustaendigen_aktionen_liefert_aktion_mit_schluessel_der_quelle(): void {
        $this->resetAfterTest();

        $berufsbildner = $this->getDataGenerator()->create_user();
        $lernende = $this->getDataGenerator()->create_user();
        $this->lege_zuordnung_an((int) $berufsbildner->id, (int) $lernende->id);

        $provider = new collector_test_zustaendigen_provider(
            new erfassen_aktion('fremd', 'Bericht erfassen', '/local/test/bericht.php', 'Fällig bis morgen')
        );
        $aktionen = (new collector([$provider, new collector_test_provider()]))
            ->get_zustaendigen_aktionen((int) $berufsbildner->id, (int) $lernende->id);

        $this->assertCount(1, $aktionen);
        $this->assertSame('testzustaendig', $aktionen[0]->quellekey);
        $this->assertSame('Bericht erfassen', $aktionen[0]->label);
        $this->assertSame('/local/test/bericht.php', $aktionen[0]->url);
        $this->assertSame('Fällig bis morgen', $aktionen[0]->hinweis);
    }

    public function test_get_zustaendigen_aktionen_ohne_anstehendes_bleibt_leer(): void {
        $this->resetAfterTest();

        $berufsbildner = $this->getDataGenerator()->create_user();
        $lernende = $this->getDataGenerator()->create_user();
        $this->lege_zuordnung_an((int) $berufsbildner->id, (int) $lernende->id);

        $provider = new collector_test_zustaendigen_provider(null);
        $aktionen = (new collector([$provider]))->get_zustaendigen_aktionen((int) $berufsbildner->id, (int) $lernende->id);

        $this->assertSame([], $aktionen);
        $this->assertTrue($provider->wurdeaufgerufen);
    }

    /**
     * Wer fuer sich selbst erfasst, bekommt hier nichts - dafuer gibt es
     * get_erfassen_aktionen(). Die Quelle wird gar nicht erst gefragt.
     */
    public function test_get_zustaendigen_aktionen_fuer_sich_selbst_bleibt_leer(): void {
        $this->resetAfterTest();

        $lernende = $this->getDataGenerator()->create_user();
        $provider = new collector_test_zustaendigen_provider(new erfassen_aktion('x', 'X', '/x.php'));

        $aktionen = (new collector([$provider]))->get_zustaendigen_aktionen((int) $lernende->id, (int) $lernende->id);

        $this->assertSame([], $aktionen);
        $this->assertFalse($provider->wurdeaufgerufen);
    }

    public function test_get_zustaendigen_aktionen_fuer_fremde_person_fragt_die_quelle_nicht(): void {
        $this->resetAfterTest();

        $fremder = $this->getDataGenerator()->create_user();
        $lernende = $this->getDataGenerator()->create_user();
        $provider = new collector_test_zustaendigen_provider(new erfassen_aktion('x', 'X', '/x.php'));

        $this->expectException(moodle_exception::class);

        try {
            (new collector([$provider]))->get_zustaendigen_aktionen((int) $fremder->id, (int) $lernende->id);
        } finally {
            $this->assertFalse($provider->wurdeaufgerufen);
        }
    }

    /**
     * Erfasst wird jetzt: eine Zuordnung, die gestern geendet hat, gibt
     * keine Aktion mehr, auch wenn sie das Semester des Berichts abdeckte.
     */
    public function test_get_zustaendigen_aktionen_nach_beendeter_zuordnung_verweigert(): void {
        $this->resetAfterTest();

        $berufsbildner = $this->getDataGenerator()->create_user();
        $lernende = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->get_plugin_generator('local_berufsbildung')->create_zuordnung([
            'berufsbildnerid' => (int) $berufsbildner->id,
            'lernendeid' => (int) $lernende->id,
            'gueltig_von' => time() - YEARSECS,
            'gueltig_bis' => time() - DAYSECS,
        ]);

        $this->expectException(moodle_exception::class);
        (new collector([new collector_test_zustaendigen_provider()]))
            ->get_zustaendigen_aktionen((int) $berufsbildner->id, (int) $lernende->id);
    }

    public function test_get_schnellaktionen_liefert_aktion_mit_schluessel_der_quelle(): void {
        $this->resetAfterTest();

        $berufsbildner = $this->getDataGenerator()->create_user();
        $lernende = $this->getDataGenerator()->create_user();
        $this->lege_zuordnung_an((int) $berufsbildner->id, (int) $lernende->id);

        $provider = new collector_test_schnellaktion_provider(
            new schnellaktion('fremd', 'Notiz (2)', '/local/test/notiz.php', 'fa-sticky-note', 'local_test/notiz')
        );
        $aktionen = (new collector([$provider, new collector_test_provider()]))
            ->get_schnellaktionen((int) $berufsbildner->id, (int) $lernende->id);

        $this->assertCount(1, $aktionen);
        $this->assertSame('testschnell', $aktionen[0]->quellekey);
        $this->assertSame('Notiz (2)', $aktionen[0]->label);
        $this->assertSame('/local/test/notiz.php', $aktionen[0]->url);
        $this->assertSame('fa-sticky-note', $aktionen[0]->icon);
        $this->assertSame('local_test/notiz', $aktionen[0]->amdmodul);
    }

    public function test_get_schnellaktionen_ohne_aktion_bleibt_leer(): void {
        $this->resetAfterTest();

        $berufsbildner = $this->getDataGenerator()->create_user();
        $lernende = $this->getDataGenerator()->create_user();
        $this->lege_zuordnung_an((int) $berufsbildner->id, (int) $lernende->id);

        $provider = new collector_test_schnellaktion_provider(null);
        $aktionen = (new collector([$provider]))->get_schnellaktionen((int) $berufsbildner->id, (int) $lernende->id);

        $this->assertSame([], $aktionen);
        $this->assertTrue($provider->wurdeaufgerufen);
    }

    public function test_get_schnellaktionen_fuer_sich_selbst_bleibt_leer(): void {
        $this->resetAfterTest();

        $lernende = $this->getDataGenerator()->create_user();
        $provider = new collector_test_schnellaktion_provider(new schnellaktion('x', 'X', '/x.php', 'fa-x'));

        $aktionen = (new collector([$provider]))->get_schnellaktionen((int) $lernende->id, (int) $lernende->id);

        $this->assertSame([], $aktionen);
        $this->assertFalse($provider->wurdeaufgerufen);
    }

    public function test_get_schnellaktionen_fuer_fremde_person_fragt_die_quelle_nicht(): void {
        $this->resetAfterTest();

        $fremder = $this->getDataGenerator()->create_user();
        $lernende = $this->getDataGenerator()->create_user();
        $provider = new collector_test_schnellaktion_provider(new schnellaktion('x', 'X', '/x.php', 'fa-x'));

        $this->expectException(moodle_exception::class);

        try {
            (new collector([$provider]))->get_schnellaktionen((int) $fremder->id, (int) $lernende->id);
        } finally {
            $this->assertFalse($provider->wurdeaufgerufen);
        }
    }

    /**
     * Wie bei den Erfassen-Aktionen zaehlt heute: eine Zuordnung, die
     * gestern geendet hat, gibt keine Schnellaktion mehr.
     */
    public function test_get_schnellaktionen_nach_beendeter_zuordnung_verweigert(): void {
        $this->resetAfterTest();

        $berufsbildner = $this->getDataGenerator()->create_user();
        $lernende = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->get_plugin_generator('local_berufsbildung')->create_zuordnung([
            'berufsbildnerid' => (int) $berufsbildner->id,
            'lernendeid' => (int) $lernende->id,
            'gueltig_von' => time() - YEARSECS,
            'gueltig_bis' => time() - DAYSECS,
        ]);

        $this->expectException(moodle_exception::class);
        (new collector([new collector_test_schnellaktion_provider()]))
            ->get_schnellaktionen((int) $berufsbildner->id, (int) $lernende->id);
    }
}

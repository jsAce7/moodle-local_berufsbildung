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
 * Tests fuer die Faelligkeitstermine im Kalender.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\service;

use advanced_testcase;
use local_berufsbildung\nachweis\collector;
use local_berufsbildung\nachweis\collector_test_faelligkeiten_provider;
use local_berufsbildung\nachweis\faelligkeit;
use local_berufsbildung\persistent\zuordnung;
use stdClass;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../fixtures/collector_test_faelligkeiten_provider.php');

#[\PHPUnit\Framework\Attributes\CoversClass(\local_berufsbildung\service\termin_service::class)]
/**
 * Tests fuer termin_service.
 *
 * @covers \local_berufsbildung\service\termin_service
 */
final class termin_service_test extends advanced_testcase {
    /** @var collector_test_faelligkeiten_provider */
    private collector_test_faelligkeiten_provider $quelle;

    /** @var termin_service */
    private termin_service $service;

    /** @var stdClass */
    private stdClass $berufsbildner;

    /** @var stdClass */
    private stdClass $lernende;

    /**
     * Eine zustaendige Person, eine lernende Person und eine Quelle mit zwei
     * Faelligkeiten: einer fuer beide, einer nur fuer die zustaendige Person.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $this->berufsbildner = $this->getDataGenerator()->create_user();
        $this->lernende = $this->getDataGenerator()->create_user(['firstname' => 'Anna', 'lastname' => 'Muster']);
        $this->lege_zuordnung_an((int) $this->berufsbildner->id);

        $this->quelle = new collector_test_faelligkeiten_provider();
        $this->quelle->faelligkeiten = [
            new faelligkeit('test', 'Rueckmeldung ausstehend', time() + WEEKSECS, 'https://example.org/r', 'Rueckmeldung geben'),
            new faelligkeit('test', 'Bericht schreiben', time() + 2 * WEEKSECS, 'https://example.org/b', null, false),
        ];
        $this->service = new termin_service(new collector([$this->quelle]));
    }

    /**
     * Legt eine Zuordnung zur lernenden Person an.
     *
     * @param int $berufsbildnerid
     * @param int|null $gueltigvon Standard: vor einem Jahr
     * @param int|null $gueltigbis Standard: laufend
     * @param string $rolle
     * @return zuordnung
     */
    private function lege_zuordnung_an(
        int $berufsbildnerid,
        ?int $gueltigvon = null,
        ?int $gueltigbis = null,
        string $rolle = 'hauptverantwortlich'
    ): zuordnung {
        return $this->getDataGenerator()->get_plugin_generator('local_berufsbildung')->create_zuordnung([
            'berufsbildnerid' => $berufsbildnerid,
            'lernendeid' => (int) $this->lernende->id,
            'gueltig_von' => $gueltigvon ?? time() - YEARSECS,
            'gueltig_bis' => $gueltigbis,
            'rolle' => $rolle,
        ]);
    }

    /**
     * Die Termine im Kalender einer Person, nach Datum.
     *
     * @param int $userid
     * @return stdClass[]
     */
    private function termine_von(int $userid): array {
        global $DB;

        return array_values($DB->get_records(
            'event',
            ['component' => termin_service::KOMPONENTE, 'userid' => $userid],
            'timestart ASC'
        ));
    }

    public function test_legt_termine_fuer_zustaendige_und_lernende_an(): void {
        $ergebnis = $this->service->nachfuehren((int) $this->lernende->id);

        $this->assertSame(['angelegt' => 3, 'geaendert' => 0, 'entfernt' => 0], $ergebnis);

        $termine = $this->termine_von((int) $this->berufsbildner->id);
        $this->assertCount(2, $termine);
        $this->assertStringContainsString('Rueckmeldung ausstehend', $termine[0]->name);
        $this->assertStringContainsString('Anna Muster', $termine[0]->name);
        $this->assertStringContainsString('Bericht schreiben', $termine[1]->name);
        $this->assertEquals(CALENDAR_EVENT_TYPE_ACTION, $termine[0]->type);
        $this->assertEquals(0, $termine[0]->courseid);
        $this->assertEquals($this->lernende->id, $termine[0]->instance);
        $this->assertEquals($termine[0]->timestart, $termine[0]->timesort);
        $this->assertSame('Test-Quelle', $termine[0]->description);

        // Die lernende Person sieht nur, was fuer sie bestimmt ist, mit der
        // eigenen Bezeichnung und ohne ihren Namen.
        $eigene = $this->termine_von((int) $this->lernende->id);
        $this->assertCount(1, $eigene);
        $this->assertSame('Rueckmeldung geben', $eigene[0]->name);
    }

    public function test_zweiter_lauf_aendert_nichts_und_verschobene_frist_bleibt_derselbe_termin(): void {
        $this->service->nachfuehren((int) $this->lernende->id);
        $vorher = $this->termine_von((int) $this->berufsbildner->id);

        $this->assertSame(
            ['angelegt' => 0, 'geaendert' => 0, 'entfernt' => 0],
            $this->service->nachfuehren((int) $this->lernende->id)
        );

        $neuesdatum = time() + 3 * WEEKSECS;
        $this->quelle->faelligkeiten[1] = new faelligkeit(
            'test',
            'Bericht schreiben',
            $neuesdatum,
            'https://example.org/b2',
            null,
            false
        );

        $this->assertSame(
            ['angelegt' => 0, 'geaendert' => 1, 'entfernt' => 0],
            $this->service->nachfuehren((int) $this->lernende->id)
        );
        $nachher = $this->termine_von((int) $this->berufsbildner->id);
        $this->assertSame($vorher[1]->id, $nachher[1]->id);
        $this->assertEquals(faelligkeit::ueberfaellig_ab($neuesdatum) - 1, $nachher[1]->timestart);
        $this->assertEquals(faelligkeit::ueberfaellig_ab($neuesdatum) - 1, $nachher[1]->timesort);
    }

    /**
     * Der Kalender zeigt jeder Person nur ihre eigenen Aufgaben, am Ende des
     * Faelligkeitstags - oder des abweichenden Kalenderdatums, etwa des
     * Probezeitendes.
     */
    public function test_eigene_aufgaben_am_ende_des_kalendertags(): void {
        $this->setTimezone('Europe/Zurich');
        $zone = \core_date::get_server_timezone_object();
        $am = static fn (string $zeit): int => (new \DateTime($zeit, $zone))->getTimestamp();

        $this->quelle->faelligkeiten = [
            new faelligkeit(
                'test',
                'Eintrag erfassen',
                $am('2027-03-10 00:00:00'),
                'https://example.org/e',
                null,
                true,
                faelligkeit::AUFGABE_LERNENDE
            ),
            new faelligkeit(
                'test',
                'Semester kontrollieren',
                $am('2027-03-11 14:30:00'),
                'https://example.org/k',
                'Kontrolle ausstehend',
                true,
                faelligkeit::AUFGABE_ZUSTAENDIGE
            ),
            new faelligkeit(
                'test',
                'Probezeitbericht',
                $am('2027-03-12 00:00:00'),
                'https://example.org/p',
                null,
                false,
                faelligkeit::AUFGABE_ZUSTAENDIGE,
                $am('2027-04-02 00:00:00')
            ),
        ];

        $this->service->nachfuehren((int) $this->lernende->id);

        $eigene = $this->termine_von((int) $this->lernende->id);
        $this->assertCount(1, $eigene);
        $this->assertSame('Eintrag erfassen', $eigene[0]->name);
        $this->assertEquals($am('2027-03-10 23:59:59'), $eigene[0]->timestart);

        $termine = $this->termine_von((int) $this->berufsbildner->id);
        $this->assertCount(2, $termine);
        $this->assertStringContainsString('Semester kontrollieren', $termine[0]->name);
        $this->assertEquals($am('2027-03-11 23:59:59'), $termine[0]->timestart);
        $this->assertStringContainsString('Probezeitbericht', $termine[1]->name);
        $this->assertEquals($am('2027-04-02 23:59:59'), $termine[1]->timestart);

        // Wer eine fremde Aufgabe oeffnet, findet kein Ziel.
        $this->assertSame('https://example.org/e', $this->service->ziel_fuer((int) $eigene[0]->id, (int) $this->lernende->id));
        $this->assertSame(
            'https://example.org/k',
            $this->service->ziel_fuer((int) $termine[0]->id, (int) $this->berufsbildner->id)
        );
    }

    public function test_erledigte_faelligkeit_verschwindet_aus_allen_kalendern(): void {
        $this->service->nachfuehren((int) $this->lernende->id);

        array_shift($this->quelle->faelligkeiten);

        $this->assertSame(
            ['angelegt' => 0, 'geaendert' => 0, 'entfernt' => 2],
            $this->service->nachfuehren((int) $this->lernende->id)
        );
        $this->assertCount(1, $this->termine_von((int) $this->berufsbildner->id));
        $this->assertCount(0, $this->termine_von((int) $this->lernende->id));
    }

    /**
     * Stichtag-Randfaelle: eine Zuordnung, die heute noch laeuft, gilt;
     * eine, die erst morgen beginnt, noch nicht; eine beendete nimmt ihre
     * Termine mit.
     */
    public function test_termine_nur_bei_heutiger_zustaendigkeit(): void {
        $stellvertretung = $this->getDataGenerator()->create_user();
        $zuordnung = $this->lege_zuordnung_an((int) $stellvertretung->id, null, time() + HOURSECS, 'stellvertretung');
        $kuenftig = $this->getDataGenerator()->create_user();
        $this->lege_zuordnung_an((int) $kuenftig->id, time() + DAYSECS, null, 'stellvertretung');

        $this->service->nachfuehren((int) $this->lernende->id);

        $this->assertCount(2, $this->termine_von((int) $stellvertretung->id));
        $this->assertCount(0, $this->termine_von((int) $kuenftig->id));

        $zuordnung->set('gueltig_bis', time() - 1);
        $zuordnung->update();

        $this->assertSame(
            ['angelegt' => 0, 'geaendert' => 0, 'entfernt' => 2],
            $this->service->nachfuehren((int) $this->lernende->id)
        );
        $this->assertCount(0, $this->termine_von((int) $stellvertretung->id));
        $this->assertCount(2, $this->termine_von((int) $this->berufsbildner->id));
    }

    public function test_ohne_zustaendige_person_keine_termine_auch_nicht_eigene(): void {
        $lernende = $this->getDataGenerator()->create_user();

        $this->assertSame(
            ['angelegt' => 0, 'geaendert' => 0, 'entfernt' => 0],
            $this->service->nachfuehren((int) $lernende->id)
        );
        $this->assertCount(0, $this->termine_von((int) $lernende->id));
    }

    public function test_synchronisiere_raeumt_termine_ohne_zuordnung_auf(): void {
        $this->service->nachfuehren((int) $this->lernende->id);

        foreach (zuordnung::get_records(['lernendeid' => (int) $this->lernende->id]) as $zuordnung) {
            $zuordnung->delete();
        }

        $this->assertSame(['angelegt' => 0, 'geaendert' => 0, 'entfernt' => 3], $this->service->synchronisiere());
        $this->assertCount(0, $this->termine_von((int) $this->berufsbildner->id));
        $this->assertCount(0, $this->termine_von((int) $this->lernende->id));
    }

    public function test_ausgeschaltet_entfernt_der_abgleich_alle_termine(): void {
        $this->service->nachfuehren((int) $this->lernende->id);

        set_config('kalender_termine', 0, 'local_berufsbildung');

        $this->assertFalse(termin_service::ist_aktiv());
        $this->assertSame(['angelegt' => 0, 'geaendert' => 0, 'entfernt' => 3], $this->service->synchronisiere());
        $this->assertSame(['angelegt' => 0, 'geaendert' => 0, 'entfernt' => 0], $this->service->synchronisiere());
    }

    public function test_ziel_fuer_loest_die_adresse_beim_klick_auf(): void {
        $this->service->nachfuehren((int) $this->lernende->id);
        $termine = $this->termine_von((int) $this->berufsbildner->id);
        $eigener = $this->termine_von((int) $this->lernende->id)[0];
        $fremd = $this->getDataGenerator()->create_user();

        $this->assertSame(
            'https://example.org/r',
            $this->service->ziel_fuer((int) $termine[0]->id, (int) $this->berufsbildner->id)
        );
        $this->assertSame('https://example.org/r', $this->service->ziel_fuer((int) $eigener->id, (int) $this->lernende->id));
        $this->assertNull($this->service->ziel_fuer((int) $termine[0]->id, (int) $fremd->id));

        // Inzwischen angelegt: dieselbe Faelligkeit, neue Adresse.
        $this->quelle->faelligkeiten[1] = new faelligkeit(
            'test',
            'Bericht schreiben',
            time() + 2 * WEEKSECS,
            'https://example.org/erfassen',
            null,
            false
        );
        $this->assertSame(
            'https://example.org/erfassen',
            $this->service->ziel_fuer((int) $termine[1]->id, (int) $this->berufsbildner->id)
        );
    }

    public function test_veralteter_termin_wird_beim_oeffnen_bereinigt(): void {
        $this->service->nachfuehren((int) $this->lernende->id);
        $termin = $this->termine_von((int) $this->berufsbildner->id)[0];
        $eigener = $this->termine_von((int) $this->lernende->id)[0];

        array_shift($this->quelle->faelligkeiten);

        // Die lernende Person landet auf ihrer eigenen Uebersicht, und der
        // Abgleich entfernt den Termin in beiden Kalendern.
        $this->assertNull($this->service->ziel_fuer((int) $eigener->id, (int) $this->lernende->id));
        $ziel = $this->service->veralteten_termin_bereinigen((int) $eigener->id, (int) $this->lernende->id);
        $this->assertStringContainsString('/local/berufsbildung/meine_lehre.php', $ziel->out(false));
        $this->assertCount(0, $this->termine_von((int) $this->lernende->id));
        $this->assertCount(1, $this->termine_von((int) $this->berufsbildner->id));

        // Ist der Termin schon weg, fuehrt er die zustaendige Person auf ihre Lernenden.
        $this->assertNull($this->service->ziel_fuer((int) $termin->id, (int) $this->berufsbildner->id));
        $ziel = $this->service->veralteten_termin_bereinigen((int) $termin->id, (int) $this->berufsbildner->id);
        $this->assertStringContainsString('/local/berufsbildung/meine_lernenden.php', $ziel->out(false));
    }

    public function test_ist_sichtbar_nur_fuer_eigene_person_und_heute_zustaendige(): void {
        $fremd = $this->getDataGenerator()->create_user();

        $this->assertTrue(termin_service::ist_sichtbar((int) $this->lernende->id, (int) $this->lernende->id));
        $this->assertTrue(termin_service::ist_sichtbar((int) $this->berufsbildner->id, (int) $this->lernende->id));
        $this->assertFalse(termin_service::ist_sichtbar((int) $fremd->id, (int) $this->lernende->id));

        set_config('kalender_termine', 0, 'local_berufsbildung');
        $this->assertFalse(termin_service::ist_sichtbar((int) $this->berufsbildner->id, (int) $this->lernende->id));
    }

    public function test_loesche_fuer_entfernt_termine_zur_person_in_allen_kalendern(): void {
        $andere = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->get_plugin_generator('local_berufsbildung')->create_zuordnung([
            'berufsbildnerid' => (int) $this->berufsbildner->id,
            'lernendeid' => (int) $andere->id,
        ]);
        $this->service->nachfuehren((int) $this->lernende->id);
        $this->service->nachfuehren((int) $andere->id);

        $this->assertSame(3, $this->service->loesche_fuer((int) $this->lernende->id));

        $this->assertCount(0, $this->termine_von((int) $this->lernende->id));
        $termine = $this->termine_von((int) $this->berufsbildner->id);
        $this->assertCount(2, $termine);
        $this->assertEquals($andere->id, $termine[0]->instance);
    }

    public function test_kalender_callbacks_liefern_schaltflaeche_und_sichtbarkeit(): void {
        global $CFG;
        require_once($CFG->dirroot . '/local/berufsbildung/lib.php');

        $this->service->nachfuehren((int) $this->lernende->id);
        $termin = \calendar_event::load((int) $this->termine_von((int) $this->berufsbildner->id)[0]->id);
        $fremd = $this->getDataGenerator()->create_user();
        $factory = new \core_calendar\action_factory();

        $aktion = local_berufsbildung_core_calendar_provide_event_action($termin, $factory, (int) $this->berufsbildner->id);
        $this->assertNotNull($aktion);
        $this->assertTrue($aktion->is_actionable());
        $this->assertStringContainsString('/local/berufsbildung/termin.php', $aktion->get_url()->out(false));
        $this->assertNull(local_berufsbildung_core_calendar_provide_event_action($termin, $factory, (int) $fremd->id));

        $this->assertTrue(local_berufsbildung_core_calendar_is_event_visible($termin, (int) $this->berufsbildner->id));

        // Der Kalender reicht ein nachgebautes Ereignis weiter, in dem
        // instance ohne Aktivitaet 0 ist - die Sichtbarkeit haengt nicht daran.
        $termin->instance = 0;
        $this->assertTrue(local_berufsbildung_core_calendar_is_event_visible($termin, (int) $this->berufsbildner->id));

        $fremdertermin = \calendar_event::load((int) $this->termine_von((int) $this->berufsbildner->id)[1]->id);
        foreach (zuordnung::get_records(['lernendeid' => (int) $this->lernende->id]) as $zuordnung) {
            $zuordnung->delete();
        }
        $this->assertFalse(local_berufsbildung_core_calendar_is_event_visible($fremdertermin, (int) $this->berufsbildner->id));
    }

    /**
     * Die API fragt die registrierten Quellen - in diesem Plugin allein ist
     * keine registriert, die Termine aus der Testquelle verschwinden also.
     */
    public function test_api_termine_nachfuehren_fragt_die_registrierten_quellen(): void {
        $this->service->nachfuehren((int) $this->lernende->id);

        \local_berufsbildung\api::termine_nachfuehren((int) $this->lernende->id);

        $this->assertCount(0, $this->termine_von((int) $this->berufsbildner->id));
        $this->assertCount(0, $this->termine_von((int) $this->lernende->id));
    }

    /**
     * Ende zu Ende ueber die Kalender-API: die Termine stehen in der
     * Zeitleiste, auch ohne jede Kurseinschreibung, mit der Schaltflaeche.
     */
    public function test_termine_erscheinen_in_der_zeitleiste(): void {
        $this->service->nachfuehren((int) $this->lernende->id);
        $this->setUser($this->berufsbildner);

        $ereignisse = \core_calendar\local\api::get_action_events_by_timesort(time() - DAYSECS);

        $this->assertCount(2, $ereignisse);
        foreach ($ereignisse as $ereignis) {
            $this->assertStringContainsString('/local/berufsbildung/termin.php', $ereignis->get_action()->get_url()->out(false));
        }
    }
}

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
 * Tests fuer die Alterung des Versetzungsplans.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\versetzungsplan;

use advanced_testcase;
use local_berufsbildung\persistent\plan_import;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_berufsbildung\versetzungsplan\alterung_service::class)]
/**
 * Tests fuer alterung_service.
 *
 * @covers \local_berufsbildung\versetzungsplan\alterung_service
 */
final class alterung_service_test extends advanced_testcase {
    /**
     * Spielt eine Lieferung fuer anna@firma.ch ein, die beim ersten Aufruf
     * mit laufender Zuordnung angelegt wird.
     *
     * @param string $kw Kalenderwoche des einen Einsatzes
     * @return plan_import der neue erfolgreiche Import
     */
    private function importiere(string $kw): plan_import {
        $generator = $this->getDataGenerator()->get_plugin_generator('local_berufsbildung');
        if (!\core_user::get_user_by_email('anna@firma.ch')) {
            $lernende = $this->getDataGenerator()->create_user(['email' => 'anna@firma.ch']);
            $berufsbildner = $this->getDataGenerator()->create_user();
            $generator->create_zuordnung([
                'berufsbildnerid' => (int) $berufsbildner->id,
                'lernendeid' => (int) $lernende->id,
            ]);
        }

        $generator->create_plan_lieferung(['emails' => ['anna@firma.ch'], 'block' => '4', 'kw_von' => $kw, 'kw_bis' => $kw]);

        return (new import_service())->letzter_erfolgreicher_import();
    }

    public function test_ohne_import_ist_nichts_veraltet(): void {
        $this->resetAfterTest();
        set_config('versetzungsplan_alterung_meldung', 1, 'local_berufsbildung');
        $sink = $this->redirectMessages();

        $service = new alterung_service();

        $this->assertNull($service->veralteter_import(time() + 1000 * DAYSECS));
        $this->assertSame(0, $service->melde_falls_veraltet(time() + 1000 * DAYSECS));
        $this->assertCount(0, $sink->get_messages());
    }

    /**
     * Randfall: genau an der Frist ist der Import noch aktuell, eine
     * Sekunde spaeter veraltet - wie bisher der Hinweis auf import_plan.php.
     */
    public function test_genau_an_der_frist_aktuell_eine_sekunde_spaeter_veraltet(): void {
        $this->resetAfterTest();
        set_config('versetzungsplan_alterung_tage', 10, 'local_berufsbildung');

        $import = $this->importiere('2027-W15');
        $grenze = (int) $import->get('zeitpunkt') + 10 * DAYSECS;
        $service = new alterung_service();

        $this->assertSame(10, $service->frist_tage());
        $this->assertNull($service->veralteter_import($grenze));
        $this->assertSame((int) $import->get('id'), (int) $service->veralteter_import($grenze + 1)->get('id'));
    }

    public function test_ausgeschaltet_meldet_nichts(): void {
        $this->resetAfterTest();
        $sink = $this->redirectMessages();

        $import = $this->importiere('2027-W15');
        $veraltet = (int) $import->get('zeitpunkt') + 100 * DAYSECS;

        $this->assertNotNull((new alterung_service())->veralteter_import($veraltet));
        $this->assertSame(0, (new alterung_service())->melde_falls_veraltet($veraltet));
        $this->assertCount(0, $sink->get_messages());
    }

    /**
     * Gemeldet wird nur den Site-Admins, nicht wer den Plan freigeben darf,
     * und je veraltetem Import nur einmal. Erst ein neuer Import, der
     * seinerseits veraltet, meldet wieder.
     */
    public function test_meldet_den_site_admins_einmal_je_veraltetem_import(): void {
        $this->resetAfterTest();
        set_config('versetzungsplan_alterung_meldung', 1, 'local_berufsbildung');
        set_config('versetzungsplan_alterung_tage', 10, 'local_berufsbildung');

        $verwaltung = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability(import_service::CAPABILITY_FREIGABE, CAP_ALLOW, $roleid, \context_system::instance()->id);
        role_assign($roleid, (int) $verwaltung->id, \context_system::instance()->id);
        $sink = $this->redirectMessages();
        $service = new alterung_service();

        $erster = $this->importiere('2027-W15');
        $veraltet = (int) $erster->get('zeitpunkt') + 11 * DAYSECS;

        $this->assertSame(1, $service->melde_falls_veraltet($veraltet));
        $nachrichten = $sink->get_messages();
        $this->assertCount(1, $nachrichten);
        $this->assertSame((int) get_admin()->id, (int) $nachrichten[0]->useridto);
        $this->assertSame(alterung_service::NACHRICHT_ALTERUNG, $nachrichten[0]->eventtype);

        // Taeglicher Lauf, derselbe veraltete Import: keine zweite Meldung.
        $this->assertSame(0, $service->melde_falls_veraltet($veraltet + DAYSECS));

        $zweiter = $this->importiere('2027-W16');
        $this->assertNotSame((int) $erster->get('id'), (int) $zweiter->get('id'));
        $this->assertSame(1, $service->melde_falls_veraltet((int) $zweiter->get('zeitpunkt') + 11 * DAYSECS));
        $this->assertCount(2, $sink->get_messages());
    }
}

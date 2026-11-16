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
 * Privacy-Tests fuer die Fachvorgesetzten der Ausbildungsbloecke.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\privacy;

use context_system;
use context_user;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_berufsbildung\persistent\block_fv;
use local_berufsbildung\service\fachvorgesetzte_service;
use local_berufsbildung\service\fachvorgesetztenrolle_service;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_berufsbildung\privacy\provider::class)]
/**
 * Tests fuer provider, Teil local_berufsbildung_block_fv.
 *
 * Die Eintraege entstehen ohne angemeldete Person (usermodified = 0): so
 * findet der Provider die fachvorgesetzte Person ueber fv.userid und nicht
 * ueber die Bearbeitungsspur.
 *
 * @covers \local_berufsbildung\privacy\provider
 */
final class provider_fachvorgesetzte_test extends \core_privacy\tests\provider_testcase {
    /** @var int Block A1. */
    private int $blocka;

    /** @var int Block B1. */
    private int $blockb;

    /** @var \stdClass Die fachvorgesetzte Person, um die es geht. */
    private \stdClass $fv;

    /** @var int Eine zweite fachvorgesetzte Person im selben Block. */
    private int $andere;

    /**
     * Zwei Bloecke; $fv ist in beiden fachvorgesetzt, $andere nur in A1.
     */
    private function lege_an(): void {
        $generator = $this->getDataGenerator()->get_plugin_generator('local_berufsbildung');
        $this->blocka = (int) $generator->create_block(['nummer' => 'A1', 'name' => 'Abteilung A1', 'ist_betrieb' => true])
            ->get('id');
        $this->blockb = (int) $generator->create_block(['nummer' => 'B1', 'name' => 'Abteilung B1', 'ist_betrieb' => true])
            ->get('id');
        $this->fv = $this->getDataGenerator()->create_user();
        $this->andere = (int) $this->getDataGenerator()->create_user()->id;

        $service = new fachvorgesetzte_service();
        $service->setze_fuer_block($this->blocka, [(int) $this->fv->id, $this->andere]);
        $service->setze_fuer_block($this->blockb, [(int) $this->fv->id]);
    }

    /**
     * Hat die Person die vom Plugin vergebene Fachvorgesetztenrolle?
     *
     * @param int $userid
     * @return bool
     */
    private function hat_rolle(int $userid): bool {
        global $DB;

        return $DB->record_exists('role_assignments', [
            'roleid' => (new fachvorgesetztenrolle_service())->rolle_id(),
            'userid' => $userid,
            'contextid' => context_system::instance()->id,
            'component' => 'local_berufsbildung',
        ]);
    }

    /**
     * Nach jeder Loeschung gleich: $fv ist nirgends mehr eingetragen und hat
     * die Rolle nicht mehr, $andere bleibt mit Rolle stehen.
     */
    private function pruefe_entfernt(): void {
        $this->assertSame(0, block_fv::count_records(['userid' => (int) $this->fv->id]));
        $this->assertFalse($this->hat_rolle((int) $this->fv->id));
        $this->assertSame([$this->andere], (new fachvorgesetzte_service())->get_fuer_block($this->blocka, true));
        $this->assertTrue($this->hat_rolle($this->andere));
    }

    public function test_metadaten_nennen_block_fv(): void {
        $collection = provider::get_metadata(new \core_privacy\local\metadata\collection('local_berufsbildung'));

        $felder = null;
        foreach ($collection->get_collection() as $eintrag) {
            if (
                $eintrag instanceof \core_privacy\local\metadata\types\database_table
                && $eintrag->get_name() === 'local_berufsbildung_block_fv'
            ) {
                $felder = array_keys($eintrag->get_privacy_fields());
            }
        }

        $this->assertSame(['blockid', 'userid', 'usermodified'], $felder);
    }

    public function test_kontext_der_fachvorgesetzten_person_wird_gefunden(): void {
        $this->resetAfterTest();
        $this->lege_an();

        $this->assertSame(0, (int) block_fv::get_record(['userid' => (int) $this->fv->id, 'blockid' => $this->blocka])
            ->get('usermodified'));
        $this->assertEquals(
            [context_user::instance((int) $this->fv->id)->id],
            provider::get_contexts_for_userid((int) $this->fv->id)->get_contextids()
        );
    }

    public function test_get_users_in_context_liefert_die_fachvorgesetzte_person(): void {
        $this->resetAfterTest();
        $this->lege_an();

        $userlist = new userlist(context_user::instance((int) $this->fv->id), 'local_berufsbildung');
        provider::get_users_in_context($userlist);

        $this->assertEquals([(int) $this->fv->id], $userlist->get_userids());
    }

    public function test_export_enthaelt_die_abteilungen(): void {
        $this->resetAfterTest();
        $this->lege_an();

        $this->export_all_data_for_user((int) $this->fv->id, 'local_berufsbildung');

        $daten = writer::with_context(context_user::instance((int) $this->fv->id))
            ->get_data([get_string('privacy:pfad_fachvorgesetzt', 'local_berufsbildung')]);
        $this->assertCount(2, $daten->abteilungen);
        $this->assertSame('A1', $daten->abteilungen[0]['nummer']);
        $this->assertSame('Abteilung A1', $daten->abteilungen[0]['name']);
        $this->assertSame('B1', $daten->abteilungen[1]['nummer']);
    }

    public function test_delete_data_for_all_users_in_context_entfernt_eintraege_und_rolle(): void {
        $this->resetAfterTest();
        $this->lege_an();

        provider::delete_data_for_all_users_in_context(context_user::instance((int) $this->fv->id));

        $this->pruefe_entfernt();
    }

    public function test_delete_data_for_user_entfernt_eintraege_und_rolle(): void {
        $this->resetAfterTest();
        $this->lege_an();

        provider::delete_data_for_user(new approved_contextlist(
            \core_user::get_user((int) $this->fv->id, '*', MUST_EXIST),
            'local_berufsbildung',
            provider::get_contexts_for_userid((int) $this->fv->id)->get_contextids()
        ));

        $this->pruefe_entfernt();
    }

    public function test_delete_data_for_users_entfernt_eintraege_und_rolle(): void {
        $this->resetAfterTest();
        $this->lege_an();

        provider::delete_data_for_users(new approved_userlist(
            context_user::instance((int) $this->fv->id),
            'local_berufsbildung',
            [(int) $this->fv->id]
        ));

        $this->pruefe_entfernt();
    }

    /**
     * Wer die Fachvorgesetzten eingetragen hat, ist nur Bearbeitungsspur: der
     * Eintrag bleibt, usermodified wird 0.
     */
    public function test_loeschung_der_bearbeitenden_person_anonymisiert_nur_usermodified(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator()->get_plugin_generator('local_berufsbildung');
        $blockid = (int) $generator->create_block(['nummer' => 'A1', 'ist_betrieb' => true])->get('id');
        $verwaltung = $this->getDataGenerator()->create_user();
        $fv = (int) $this->getDataGenerator()->create_user()->id;
        $this->setUser($verwaltung);
        (new fachvorgesetzte_service())->setze_fuer_block($blockid, [$fv]);
        $this->setUser(null);
        $eintrag = block_fv::get_record(['blockid' => $blockid, 'userid' => $fv]);
        $this->assertSame((int) $verwaltung->id, (int) $eintrag->get('usermodified'));

        provider::delete_data_for_user(new approved_contextlist(
            \core_user::get_user((int) $verwaltung->id, '*', MUST_EXIST),
            'local_berufsbildung',
            provider::get_contexts_for_userid((int) $verwaltung->id)->get_contextids()
        ));

        $eintrag->read();
        $this->assertSame(0, (int) $eintrag->get('usermodified'));
        $this->assertSame([$fv], (new fachvorgesetzte_service())->get_fuer_block($blockid));
        $this->assertTrue($this->hat_rolle($fv));
    }
}

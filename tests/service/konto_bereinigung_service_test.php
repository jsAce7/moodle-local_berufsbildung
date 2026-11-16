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
 * Tests fuer die Bereinigung geloeschter Konten, Teil Fachvorgesetzte.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\service;

use advanced_testcase;
use context_system;
use local_berufsbildung\persistent\block_fv;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_berufsbildung\service\konto_bereinigung_service::class)]
/**
 * Tests fuer konto_bereinigung_service.
 *
 * Die Bereinigung der Zuordnungen pruefen die Privacy-Tests; hier geht es
 * um die Fachvorgesetzten.
 *
 * @covers \local_berufsbildung\service\konto_bereinigung_service
 */
final class konto_bereinigung_service_test extends advanced_testcase {
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
     * Ein Konto, das vor dem Observer geloescht wurde: Eintrag und Rolle
     * stehen noch und verschwinden mit bereinige_verwaiste().
     */
    public function test_bereinige_verwaiste_entfernt_fachvorgesetzte_samt_rolle(): void {
        global $DB;
        $this->resetAfterTest();
        $blockid = (int) $this->getDataGenerator()->get_plugin_generator('local_berufsbildung')
            ->create_block(['nummer' => 'A1', 'ist_betrieb' => true])->get('id');
        $geloescht = (int) $this->getDataGenerator()->create_user()->id;
        $bleibt = (int) $this->getDataGenerator()->create_user()->id;
        (new fachvorgesetzte_service())->setze_fuer_block($blockid, [$geloescht, $bleibt]);
        $this->assertTrue($this->hat_rolle($geloescht));

        // Nur als geloescht markiert: delete_user() liesse den Observer vorher bereinigen.
        $DB->set_field('user', 'deleted', 1, ['id' => $geloescht]);
        $this->assertSame(1, block_fv::count_records(['userid' => $geloescht]));

        $service = new konto_bereinigung_service();
        $this->assertSame(1, $service->bereinige_verwaiste());

        $this->assertSame(0, block_fv::count_records(['userid' => $geloescht]));
        $this->assertFalse($this->hat_rolle($geloescht));
        $this->assertSame([$bleibt], (new fachvorgesetzte_service())->get_fuer_block($blockid, true));
        $this->assertTrue($this->hat_rolle($bleibt));
        $this->assertSame(0, $service->bereinige_verwaiste());
    }
}

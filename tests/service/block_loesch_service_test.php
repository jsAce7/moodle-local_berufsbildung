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
 * Tests fuer das Loeschen eines Ausbildungsblocks.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\service;

use advanced_testcase;
use local_berufsbildung\persistent\block;
use local_berufsbildung\persistent\block_lk;
use local_berufsbildung\persistent\einsatz;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_berufsbildung\service\block_loesch_service::class)]
/**
 * Tests fuer block_loesch_service.
 *
 * @covers \local_berufsbildung\service\block_loesch_service
 */
final class block_loesch_service_test extends advanced_testcase {
    /** Fester Stichtag, damit die Grenzfaelle nicht von der Laufzeit abhaengen: Montag, 2. Maerz 2026. */
    private const STICHTAG = 1772406000;

    /**
     * Legt einen Block mit der gegebenen Zahl zugeordneter Kompetenzen an.
     *
     * @param string $nummer
     * @param int $kompetenzen
     */
    private function block(string $nummer, int $kompetenzen = 0): block {
        $plugin = $this->getDataGenerator()->get_plugin_generator('local_berufsbildung');
        $competency = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $rahmen = $competency->create_framework();

        $block = $plugin->create_block(['nummer' => $nummer, 'name' => 'Block ' . $nummer, 'beruf' => 'AU_EFZ']);
        for ($i = 0; $i < $kompetenzen; $i++) {
            $plugin->create_block_competency([
                'blockid' => $block->get('id'),
                'competencyid' => $competency->create_competency(['competencyframeworkid' => $rahmen->get('id')])->get('id'),
            ]);
        }

        return $block;
    }

    /**
     * Ein Einsatz von vier Wochen in einem Block.
     *
     * @param block $block
     * @param int $von Beginn
     * @param int|null $userid Lernende Person, null = eine neue
     */
    private function einsatz(block $block, int $von, ?int $userid = null): void {
        $this->getDataGenerator()->get_plugin_generator('local_berufsbildung')->create_einsatz([
            'userid' => $userid ?? (int) $this->getDataGenerator()->create_user()->id,
            'blockid' => (int) $block->get('id'),
            'von' => $von,
            'bis' => $von + 4 * WEEKSECS - 1,
        ]);
    }

    /**
     * Ein Block ohne Einsaetze verschwindet samt seiner Kompetenzzuordnung;
     * die Zuordnung eines anderen Blocks bleibt.
     */
    public function test_loescht_block_samt_zuordnung(): void {
        $this->resetAfterTest();
        $weg = $this->block('B1', 2);
        $bleibt = $this->block('B2', 1);
        $service = new block_loesch_service();

        $this->assertTrue($service->kann_loeschen($weg, self::STICHTAG));
        $this->assertSame(['kompetenzen' => 2, 'einsaetze' => 0], $service->loesche($weg, self::STICHTAG));

        $this->assertFalse(block::record_exists((int) $weg->get('id')));
        $this->assertSame(0, block_lk::count_records(['blockid' => (int) $weg->get('id')]));
        $this->assertTrue(block::record_exists((int) $bleibt->get('id')));
        $this->assertSame(1, block_lk::count_records(['blockid' => (int) $bleibt->get('id')]));
    }

    /**
     * Nur geplante Einsaetze sperren nicht: sie verschwinden mit dem Block.
     * Die Einsaetze anderer Bloecke bleiben, auch die derselben Person.
     * Randfall: ein Einsatz, der eine Sekunde nach dem Stichtag beginnt.
     */
    public function test_loescht_geplante_einsaetze_mit(): void {
        $this->resetAfterTest();
        $weg = $this->block('B1', 1);
        $anderer = $this->block('B2');
        $person = (int) $this->getDataGenerator()->create_user()->id;
        $this->einsatz($weg, self::STICHTAG + 1, $person);
        $this->einsatz($weg, self::STICHTAG + YEARSECS, $person);
        $this->einsatz($weg, self::STICHTAG + YEARSECS);
        $this->einsatz($anderer, self::STICHTAG - YEARSECS, $person);
        $service = new block_loesch_service();

        $this->assertTrue($service->kann_loeschen($weg, self::STICHTAG));
        $this->assertSame(['kompetenzen' => 1, 'einsaetze' => 3, 'personen' => 2], $service->umfang($weg));
        $this->assertSame(['kompetenzen' => 1, 'einsaetze' => 3], $service->loesche($weg, self::STICHTAG));

        $this->assertSame(0, einsatz::count_records(['blockid' => (int) $weg->get('id')]));
        $this->assertSame(1, einsatz::count_records(['blockid' => (int) $anderer->get('id'), 'userid' => $person]));
    }

    /**
     * Hat ein Einsatz begonnen, bleibt alles stehen - ob er vorbei ist oder
     * noch laeuft. Randfall: ein Einsatz, der genau am Stichtag beginnt,
     * gilt wie im Kompetenzraster als begonnen.
     *
     * @dataProvider begonnene_einsaetze
     * @param int $versatz Beginn des Einsatzes relativ zum Stichtag, in Sekunden
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('begonnene_einsaetze')]
    public function test_block_mit_begonnenem_einsatz_bleibt(int $versatz): void {
        $this->resetAfterTest();
        $block = $this->block('B1', 1);
        $this->einsatz($block, self::STICHTAG + $versatz);
        // Ein geplanter daneben aendert nichts.
        $this->einsatz($block, self::STICHTAG + YEARSECS);
        $service = new block_loesch_service();

        $this->assertFalse($service->kann_loeschen($block, self::STICHTAG));
        try {
            $service->loesche($block, self::STICHTAG);
            $this->fail('Ein Block mit begonnenen Einsaetzen darf nicht geloescht werden.');
        } catch (\moodle_exception $e) {
            $this->assertSame('block:loeschen_begonnen', $e->errorcode);
        }

        $this->assertTrue(block::record_exists((int) $block->get('id')));
        $this->assertSame(1, block_lk::count_records(['blockid' => (int) $block->get('id')]));
        $this->assertSame(2, einsatz::count_records(['blockid' => (int) $block->get('id')]));
    }

    /**
     * Beginne von Einsaetzen relativ zum Stichtag, die als begonnen gelten.
     *
     * @return array
     */
    public static function begonnene_einsaetze(): array {
        return [
            'vorbei' => [-YEARSECS],
            'laeuft' => [-WEEKSECS],
            'beginnt am stichtag' => [0],
        ];
    }

    /**
     * Ein Block, dessen Einsatz am Stichtag erst geplant ist, laesst sich
     * an einem spaeteren Stichtag nicht mehr loeschen.
     */
    public function test_stichtag_entscheidet(): void {
        $this->resetAfterTest();
        $block = $this->block('B1');
        $this->einsatz($block, self::STICHTAG + WEEKSECS);
        $service = new block_loesch_service();

        $this->assertTrue($service->kann_loeschen($block, self::STICHTAG));
        $this->assertFalse($service->kann_loeschen($block, self::STICHTAG + WEEKSECS));
    }

    /**
     * Die Uebersicht erfaehrt in einer Abfrage, welche Bloecke begonnene
     * Einsaetze haben; nur geplante zaehlen nicht.
     */
    public function test_bloecke_mit_begonnenen_einsaetzen(): void {
        $this->resetAfterTest();
        $begonnen = $this->block('B1');
        $geplant = $this->block('B2');
        $this->block('B3');
        $this->einsatz($begonnen, self::STICHTAG);
        $this->einsatz($begonnen, self::STICHTAG + YEARSECS);
        $this->einsatz($geplant, self::STICHTAG + 1);

        $this->assertSame(
            [(int) $begonnen->get('id') => true],
            (new block_loesch_service())->bloecke_mit_begonnenen_einsaetzen(self::STICHTAG)
        );
    }
}

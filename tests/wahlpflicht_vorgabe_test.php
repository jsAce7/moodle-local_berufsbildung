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
 * Tests fuer wahlpflicht_vorgabe.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung;

use advanced_testcase;
use local_berufsbildung\service\wahlpflicht_resolver;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_berufsbildung\wahlpflicht_vorgabe::class)]
/**
 * Tests fuer wahlpflicht_vorgabe.
 *
 * @covers \local_berufsbildung\wahlpflicht_vorgabe
 */
final class wahlpflicht_vorgabe_test extends advanced_testcase {
    /** Die Vorgabe des Polymechanikers: d6, oder zwei aus b5-b9, c5, c6, d4, d5, d7-d9, davon eine aus d. */
    private const POLYMECHANIK = 'PM_EFZ=d.06:1 | b,c,d:2; d:1';

    /**
     * Das Wahlpflicht-Raster des Polymechanikers, je Bereich eine
     * Pflicht-HK dazu.
     *
     * @param string[] $vollstaendig Kuerzel der HK, deren LK alle vorkamen
     * @param string[] $eingeplant Kuerzel der HK, die erst eingeplant sind
     * @return raster_bereich[]
     */
    private function raster(array $vollstaendig, array $eingeplant = []): array {
        $wahlpflicht = [
            'b' => ['b.05', 'b.06', 'b.07', 'b.08', 'b.09'],
            'c' => ['c.05', 'c.06'],
            'd' => ['d.04', 'd.05', 'd.06', 'd.07', 'd.08', 'd.09'],
        ];
        $raster = [];
        $id = 0;
        foreach ($wahlpflicht as $bereich => $kuerzel) {
            // Eine Pflicht-HK, die vollstaendig ist, zaehlt nie mit.
            $kompetenzen = [new raster_kompetenz(++$id, raster_kompetenz::STATUS_ABGEDECKT, false, kuerzel: $bereich . '.01')];
            foreach ($kuerzel as $hk) {
                $status = raster_kompetenz::STATUS_OFFEN;
                if (in_array($hk, $vollstaendig, true)) {
                    $status = raster_kompetenz::STATUS_ABGEDECKT;
                } else if (in_array($hk, $eingeplant, true)) {
                    $status = raster_kompetenz::STATUS_EINGEPLANT;
                }
                $kompetenzen[] = new raster_kompetenz(++$id, $status, true, kuerzel: $hk);
            }
            $raster[] = new raster_bereich(++$id, $kompetenzen, $bereich);
        }

        return $raster;
    }

    /**
     * Die Vorgabe aus der Einstellung.
     *
     * @param string $konfiguration
     */
    private function vorgabe(string $konfiguration): wahlpflicht_vorgabe {
        [$beruf] = explode('=', $konfiguration, 2);

        return (new wahlpflicht_resolver())->vorgabe($beruf, $konfiguration);
    }

    /**
     * Polymechaniker: was je Lernstand noch offen ist, und auf welchem Weg.
     *
     * @dataProvider polymechanik_faelle
     * @param string[] $vollstaendig
     * @param int[] $jeweg Erwartet offen je Weg
     * @param int $offen Erwartet offen insgesamt
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('polymechanik_faelle')]
    public function test_polymechanik(array $vollstaendig, array $jeweg, int $offen): void {
        $vorgabe = $this->vorgabe(self::POLYMECHANIK);
        $raster = $this->raster($vollstaendig);

        $this->assertSame($jeweg, $vorgabe->offen_je_weg($raster));
        $this->assertSame($offen, $vorgabe->offen($raster));
    }

    /**
     * Lernstaende des Polymechanikers.
     *
     * @return array
     */
    public static function polymechanik_faelle(): array {
        return [
            // Am wenigsten fehlt auf Weg 1: d6 allein genuegt.
            'noch nichts' => [[], [1, 2], 1],
            'd6 allein genuegt' => [['d.06'], [0, 1], 0],
            // Zwei vollstaendig, aber keine aus d: es fehlt noch eine aus d.
            'zwei aus b' => [['b.05', 'b.06'], [1, 1], 1],
            'eine aus b und eine aus d' => [['b.05', 'd.04'], [1, 0], 0],
            'zwei aus d' => [['d.04', 'd.05'], [1, 0], 0],
            'eine aus c und eine aus d' => [['c.06', 'd.09'], [1, 0], 0],
            'eine aus d' => [['d.04'], [1, 1], 1],
            'eine aus c' => [['c.05'], [1, 1], 1],
        ];
    }

    /**
     * Eingeplant ist noch nicht vollstaendig - wie bei einer einzelnen
     * Gruppe.
     */
    public function test_eingeplant_zaehlt_noch_als_offen(): void {
        $vorgabe = $this->vorgabe(self::POLYMECHANIK);

        $this->assertSame(1, $vorgabe->offen($this->raster(['b.05'], ['d.06', 'd.04'])));
    }

    /**
     * Gruppen, die sich nicht ueberschneiden, zaehlen zusammen - wie vor
     * den Wegen. Die bestehenden Einstellungen rechnen also unveraendert.
     */
    public function test_getrennte_gruppen_zaehlen_zusammen(): void {
        $vorgabe = $this->vorgabe('AU_EFZ=b,c:1; d:1');

        $this->assertSame(2, $vorgabe->offen($this->raster([])));
        $this->assertSame(1, $vorgabe->offen($this->raster(['d.04', 'd.05'])));
        $this->assertSame(0, $vorgabe->offen($this->raster(['c.05', 'd.05'])));
        $this->assertFalse($vorgabe->hat_ueberschneidungen());
    }

    /**
     * Mehrere Ebenen: eine Gruppe braucht so viel, wie den Gruppen in ihr
     * zusammen fehlt, wenn das mehr ist als ihr eigener Rest. Randfall:
     * zwei gleiche Gruppen zaehlen nicht doppelt.
     */
    public function test_ineinanderliegende_gruppen(): void {
        // Drei aus b, c, d; darin je eine aus c und aus d.
        $vorgabe = $this->vorgabe('PM_EFZ=b,c,d:3; c:1; d:1');
        $this->assertSame(3, $vorgabe->offen($this->raster([])));
        $this->assertSame(2, $vorgabe->offen($this->raster(['b.05', 'b.06'])));
        // Drei vollstaendig, aber weder aus c noch aus d: es fehlen zwei.
        $this->assertSame(2, $vorgabe->offen($this->raster(['b.05', 'b.06', 'b.07'])));
        $this->assertSame(1, $vorgabe->offen($this->raster(['b.05', 'b.06', 'c.05'])));

        // Darin zwei aus c und zwei aus d: mehr als die drei ausserhalb.
        $vorgabe = $this->vorgabe('PM_EFZ=b,c,d:3; c:2; d:2');
        $this->assertSame(4, $vorgabe->offen($this->raster([])));

        $doppelt = $this->vorgabe('PM_EFZ=d:1; d:1');
        $this->assertSame(1, $doppelt->offen($this->raster([])));
        $this->assertFalse($doppelt->hat_ueberschneidungen());
    }

    /**
     * Ueberschneiden sich zwei Gruppen eines Wegs nur teilweise, meldet die
     * Vorgabe das; zwischen verschiedenen Wegen ist es gewollt.
     */
    public function test_ueberschneidungen(): void {
        $this->assertFalse($this->vorgabe(self::POLYMECHANIK)->hat_ueberschneidungen());
        $this->assertTrue($this->vorgabe('PM_EFZ=b,c:1; c,d:1')->hat_ueberschneidungen());
        $this->assertFalse($this->vorgabe('PM_EFZ=b,c:1 | c,d:1')->hat_ueberschneidungen());
    }
}

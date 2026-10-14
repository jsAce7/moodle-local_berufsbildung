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
 * Wertobjekt fuer die Wahlpflicht-Vorgabe eines Bildungsplans.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung;

/**
 * Was ein Bildungsplan an Wahlpflicht-HK verlangt: ein oder mehrere Wege,
 * von denen einer genuegt. Ein Weg besteht aus Gruppen, die alle erfuellt
 * sein muessen. Beim Polymechaniker etwa "d6, oder zwei aus b5-b9, c5, c6,
 * d4-d9, davon eine aus d": Weg 1 ist die Gruppe d.06:1, Weg 2 sind die
 * Gruppen b,c,d:2 und d:1.
 *
 * Gruppen eines Wegs liegen ineinander (d in b,c,d) oder ueberschneiden
 * sich nicht (a,b,c und d). Eine HK, die in zwei ineinanderliegenden
 * Gruppen zaehlt, bringt beide weiter; deshalb zaehlt offen() nicht
 * einfach zusammen.
 *
 * Wird aus der Einstellung berechnet, nicht gespeichert - deshalb kein
 * Persistent.
 */
class wahlpflicht_vorgabe {
    /**
     * Konstruktor.
     *
     * @param array $wege wahlpflicht_gruppe[] je Weg, mindestens eine Gruppe je Weg
     */
    public function __construct(
        /** @var array<int, wahlpflicht_gruppe[]> Je Weg die Gruppen, die alle erfuellt sein muessen. */
        public readonly array $wege,
    ) {
    }

    /**
     * Wie viele Wahlpflicht-HK noch mindestens vollstaendig vorkommen
     * muessen: ueber den Weg, auf dem am wenigsten fehlt. 0 heisst
     * erfuellt.
     *
     * @param raster_bereich[] $raster Ergebnis von api::get_kompetenzraster()
     */
    public function offen(array $raster): int {
        $je = $this->offen_je_weg($raster);

        return $je === [] ? 0 : min($je);
    }

    /**
     * Je Weg, wie viele Wahlpflicht-HK dort noch vollstaendig vorkommen
     * muessen.
     *
     * Innerhalb eines Wegs braucht eine Gruppe mindestens so viele, wie ihr
     * selbst fehlen, und mindestens so viele, wie den Gruppen in ihr
     * zusammen fehlen - jede HK dort zaehlt auch fuer sie. Gruppen, die in
     * keiner anderen liegen, zaehlen zusammen.
     *
     * @param raster_bereich[] $raster Ergebnis von api::get_kompetenzraster()
     * @return int[] In der Reihenfolge der Wege
     */
    public function offen_je_weg(array $raster): array {
        $je = [];
        foreach ($this->wege as $gruppen) {
            $gruppen = array_values($gruppen);
            $fehlend = array_map(static fn (wahlpflicht_gruppe $gruppe): int => $gruppe->offen($raster), $gruppen);

            // Je Gruppe die kleinste, in der sie liegt.
            $kinder = [];
            $wurzeln = [];
            foreach (array_keys($gruppen) as $i) {
                $eltern = null;
                foreach (array_keys($gruppen) as $j) {
                    if (self::liegt_in($gruppen, $i, $j) && ($eltern === null || self::liegt_in($gruppen, $j, $eltern))) {
                        $eltern = $j;
                    }
                }
                if ($eltern === null) {
                    $wurzeln[] = $i;
                } else {
                    $kinder[$eltern][] = $i;
                }
            }

            $offen = 0;
            foreach ($wurzeln as $i) {
                $offen += self::bedarf($i, $fehlend, $kinder);
            }
            $je[] = $offen;
        }

        return $je;
    }

    /**
     * Gibt es in einem Weg zwei Gruppen, die sich ueberschneiden, ohne dass
     * eine in der anderen liegt? Dann kann offen() zu hoch zaehlen - ein
     * Fall fuer die Einrichtungspruefung.
     */
    public function hat_ueberschneidungen(): bool {
        foreach ($this->wege as $gruppen) {
            $gruppen = array_values($gruppen);
            foreach ($gruppen as $i => $gruppe) {
                foreach (array_slice($gruppen, $i + 1) as $andere) {
                    if ($gruppe->ueberschneidet($andere) && !$gruppe->enthaelt($andere) && !$andere->enthaelt($gruppe)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Liegt Gruppe $i in Gruppe $j? Zwei gleiche Gruppen liegen nicht
     * gegenseitig ineinander, sonst gaebe es keine kleinste: die spaetere
     * liegt in der frueheren.
     *
     * @param wahlpflicht_gruppe[] $gruppen
     * @param int $i
     * @param int $j
     */
    private static function liegt_in(array $gruppen, int $i, int $j): bool {
        return $i !== $j
            && $gruppen[$j]->enthaelt($gruppen[$i])
            && (!$gruppen[$i]->enthaelt($gruppen[$j]) || $j < $i);
    }

    /**
     * Was eine Gruppe samt den Gruppen in ihr noch braucht.
     *
     * @param int $i
     * @param int[] $fehlend Je Gruppe, was ihr selbst fehlt
     * @param array $kinder Je Gruppe die Gruppen direkt in ihr
     */
    private static function bedarf(int $i, array $fehlend, array $kinder): int {
        $summe = 0;
        foreach ($kinder[$i] ?? [] as $kind) {
            $summe += self::bedarf($kind, $fehlend, $kinder);
        }

        return max($fehlend[$i], $summe);
    }
}

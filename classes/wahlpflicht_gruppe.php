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
 * Wertobjekt fuer eine Gruppe von Wahlpflicht-Handlungskompetenzen.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung;

/**
 * Wie viele Wahlpflicht-HK der Bildungsplan aus welchen Bereichen verlangt,
 * z. B. eine aus den Bereichen a, b und c zusammen und eine aus d.
 *
 * Wird aus der Einstellung berechnet, nicht gespeichert - deshalb kein
 * Persistent. Einzelne readonly-Properties statt einer readonly-Klasse,
 * damit die Klasse auch unter PHP 8.1 (Moodle 4.5) funktioniert.
 */
class wahlpflicht_gruppe {
    /**
     * Konstruktor.
     *
     * @param string[] $bereiche Kuerzel der Handlungskompetenzbereiche, klein
     *                           geschrieben ("a", "b"); leer heisst: alle Bereiche
     * @param int $anzahl Wie viele Wahlpflicht-HK aus diesen Bereichen verlangt sind
     */
    public function __construct(
        /** @var string[] Kuerzel der Bereiche, leer fuer alle. */
        public readonly array $bereiche,
        /** @var int Verlangte Anzahl Wahlpflicht-HK. */
        public readonly int $anzahl,
    ) {
    }

    /**
     * Gehoert ein Bereich mit diesem Kuerzel zur Gruppe?
     *
     * @param string $kuerzel Kuerzel des Bereichs, z. B. "a"
     */
    public function umfasst(string $kuerzel): bool {
        return $this->bereiche === [] || in_array(\core_text::strtolower(trim($kuerzel)), $this->bereiche, true);
    }

    /**
     * Wie viele Wahlpflicht-HK aus den Bereichen dieser Gruppe vollstaendig
     * vorkamen (alle LK), wie viele teilweise und wie viele erst eingeplant
     * sind - siehe raster_kompetenz::anzeigestand().
     *
     * Raster und Kachel auf "Meine Lernenden" zaehlen beide hiermit, damit
     * sie nie auseinanderlaufen.
     *
     * @param raster_bereich[] $raster Ergebnis von api::get_kompetenzraster()
     * @return array{vollstaendig: int, teilweise: int, eingeplant: int}
     */
    public function stand(array $raster): array {
        $stand = [
            raster_kompetenz::ANZEIGE_VOLLSTAENDIG => 0,
            raster_kompetenz::ANZEIGE_TEILWEISE => 0,
            raster_kompetenz::ANZEIGE_EINGEPLANT => 0,
        ];
        foreach ($raster as $bereich) {
            if (!$this->umfasst($bereich->kuerzel)) {
                continue;
            }
            foreach ($bereich->kompetenzen as $kompetenz) {
                $anzeige = $kompetenz->anzeigestand();
                if ($kompetenz->istwahlpflicht && isset($stand[$anzeige])) {
                    $stand[$anzeige]++;
                }
            }
        }

        return $stand;
    }

    /**
     * Wie viele der verlangten Wahlpflicht-HK noch nicht vollstaendig
     * vorkamen. Erfuellt ist die Gruppe erst, wenn genug HK vollstaendig
     * sind - wie das Haekchen im Raster.
     *
     * @param raster_bereich[] $raster Ergebnis von api::get_kompetenzraster()
     */
    public function offen(array $raster): int {
        return max(0, $this->anzahl - $this->stand($raster)[raster_kompetenz::ANZEIGE_VOLLSTAENDIG]);
    }
}

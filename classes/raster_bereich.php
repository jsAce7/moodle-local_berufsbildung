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
 * Wertobjekt fuer eine Zeile des Kompetenzrasters.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung;

/**
 * Ein Handlungskompetenzbereich mit allen seinen Handlungskompetenzen -
 * eine Zeile des Rasters, so wie sie im Bildungsplan steht.
 *
 * Anders als bereich_abdeckung traegt diese Klasse den vollstaendigen
 * Bestand: auch die bereits abgedeckten und die Wahlpflicht-HK. Sie ist
 * damit die breitere Sicht, aus der sich bereich_abdeckung ableiten laesst
 * (siehe versetzungsplan\luecken_analyse::aus_raster()) - nicht umgekehrt.
 *
 * Wird berechnet, nicht gespeichert - deshalb kein Persistent.
 */
class raster_bereich {
    /**
     * Konstruktor.
     *
     * @param int $bereichid competencyid des Handlungskompetenzbereichs (oberste Ebene)
     * @param raster_kompetenz[] $kompetenzen Alle HK des Bereichs in Rahmenreihenfolge
     */
    public function __construct(
        /** @var int competencyid des Handlungskompetenzbereichs (oberste Ebene). */
        public readonly int $bereichid,
        /** @var array Alle HK des Bereichs in Rahmenreihenfolge. */
        public readonly array $kompetenzen,
    ) {
    }

    /**
     * Nur die Pflicht-HK dieses Bereichs.
     *
     * @return raster_kompetenz[]
     */
    public function pflicht(): array {
        return array_values(array_filter(
            $this->kompetenzen,
            static fn (raster_kompetenz $kompetenz): bool => !$kompetenz->istwahlpflicht
        ));
    }

    /**
     * Bezugsgroesse: Anzahl Pflicht-HK, ohne Wahlpflicht-HK.
     */
    public function soll(): int {
        return count($this->pflicht());
    }

    /**
     * competencyids der Pflicht-HK, die bis zum Stichtag in keinem Einsatz
     * vorkamen - einschliesslich der spaeter bereits eingeplanten. Eine
     * eingeplante HK ist zum Stichtag genauso wenig ausgebildet wie eine
     * offene; der Unterschied liegt in der Dringlichkeit, nicht im Stand.
     *
     * @return int[]
     */
    public function luecken(): array {
        return array_values(array_map(
            static fn (raster_kompetenz $kompetenz): int => $kompetenz->competencyid,
            array_filter(
                $this->pflicht(),
                static fn (raster_kompetenz $kompetenz): bool => !$kompetenz->ist_abgedeckt()
            )
        ));
    }

    /**
     * Anzahl der bis zum Stichtag abgedeckten Pflicht-HK dieses Bereichs.
     */
    public function anzahl_abgedeckt(): int {
        return $this->soll() - count($this->luecken());
    }
}

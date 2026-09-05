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
 * Wertobjekt fuer die Kompetenzabdeckung eines Handlungskompetenzbereichs.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung;

/**
 * Wie viele Pflicht-Handlungskompetenzen eines Bereichs bis zum Stichtag
 * in einem betrieblichen Einsatz vorkamen - und welche nicht.
 *
 * Wird berechnet, nicht gespeichert - deshalb kein Persistent. Traegt
 * bewusst nur IDs, keine Namen: die Bezeichnungen stehen in
 * core_competency, das die geteilte Grundlage bleibt und keine
 * Zwischenschicht hier braucht (siehe CLAUDE.md).
 *
 * Einzelne readonly-Properties statt einer readonly-Klasse, damit die
 * Klasse auch unter PHP 8.1 (Moodle 4.5) funktioniert.
 */
class bereich_abdeckung {

    /**
     * @param int $bereichid competencyid des Handlungskompetenzbereichs (oberste Ebene)
     * @param int $soll Anzahl Pflicht-HK des Bereichs, ohne Wahlpflicht-HK
     * @param int[] $luecken competencyids der HK ohne Abdeckung, Teilmenge von $soll
     */
    public function __construct(
        public readonly int $bereichid,
        public readonly int $soll,
        public readonly array $luecken,
    ) {
    }

    /** Anzahl der bis zum Stichtag abgedeckten Pflicht-HK dieses Bereichs. */
    public function anzahl_abgedeckt(): int {
        return $this->soll - count($this->luecken);
    }

    /** Sind in diesem Bereich alle Pflicht-HK abgedeckt? */
    public function ist_vollstaendig(): bool {
        return empty($this->luecken);
    }
}

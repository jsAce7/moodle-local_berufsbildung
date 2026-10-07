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
 * Wertobjekt fuer eine einzelne Handlungskompetenz im Kompetenzraster.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung;

/**
 * Eine Zelle des Kompetenzrasters: eine Handlungskompetenz und ihr Stand
 * im importierten Versetzungsplan zu einem Stichtag.
 *
 * Wird berechnet, nicht gespeichert - deshalb kein Persistent. Traegt
 * bewusst nur die competencyid, keine Bezeichnung: die steht in
 * core_competency, das die geteilte Grundlage bleibt (siehe CLAUDE.md).
 *
 * Einzelne readonly-Properties statt einer readonly-Klasse, damit die
 * Klasse auch unter PHP 8.1 (Moodle 4.5) funktioniert - wie in
 * bereich_abdeckung.
 */
class raster_kompetenz {
    /** Stand: kam bis zum Stichtag in einem betrieblichen Einsatz vor. */
    public const STATUS_ABGEDECKT = 'abgedeckt';

    /** Stand: kommt erst nach dem Stichtag vor, im bereits vorliegenden Plan. */
    public const STATUS_EINGEPLANT = 'eingeplant';

    /** Stand: steht im vorliegenden Plan in keinem Einsatz. */
    public const STATUS_OFFEN = 'offen';

    /** Anzeige: alle LK kamen bis zum Stichtag vor. */
    public const ANZEIGE_VOLLSTAENDIG = 'vollstaendig';

    /** Anzeige: ein Teil der LK kam vor, nicht alle. */
    public const ANZEIGE_TEILWEISE = 'teilweise';

    /** Anzeige: noch kein LK kam vor, aber welche sind eingeplant. */
    public const ANZEIGE_EINGEPLANT = 'eingeplant';

    /** Anzeige: kein LK steht im vorliegenden Plan. */
    public const ANZEIGE_OFFEN = 'offen';

    /**
     * Konstruktor.
     *
     * @param int $competencyid competencyid der Handlungskompetenz (zweite Ebene)
     * @param string $status Eine der STATUS_*-Konstanten
     * @param bool $istwahlpflicht Wahlpflicht-HK dieses Berufs - zaehlt nicht in die Bezugsgroesse
     * @param array $leistungskriterien competencyid => STATUS_* je Leistungskriterium dieser HK,
     *                           in Rahmenreihenfolge; leer, wenn die HK keine hat
     * @param array $lkeinsaetze competencyid => einsatz-ids je Leistungskriterium: die Einsaetze,
     *                           in denen es vorkam oder vorkommen wird, nach Beginn sortiert
     */
    public function __construct(
        /** @var int competencyid der Handlungskompetenz (zweite Ebene). */
        public readonly int $competencyid,
        /** @var string Eine der STATUS_*-Konstanten. */
        public readonly string $status,
        /** @var bool Wahlpflicht-HK dieses Berufs - zaehlt nicht in die Bezugsgroesse. */
        public readonly bool $istwahlpflicht,
        /** @var array<int, string> competencyid => STATUS_* je Leistungskriterium dieser HK. */
        public readonly array $leistungskriterien = [],
        /** @var array<int, int[]> competencyid => einsatz-ids je Leistungskriterium. */
        public readonly array $lkeinsaetze = [],
    ) {
    }

    /**
     * Stand fuer die Anzeige: eine HK ist erst vollstaendig, wenn alle ihre
     * LK vorkamen - abgeschlossen ist sie meist erst gegen Ende der Lehre.
     * Kam ein Teil vor, ist sie teilweise. Fuer die Lueckenanalyse zaehlt
     * weiterhin $status: dort ist eine HK keine Luecke mehr, sobald eines
     * ihrer LK vorkam.
     *
     * @return string Eine der ANZEIGE_*-Konstanten
     */
    public function anzeigestand(): string {
        if ($this->status === self::STATUS_EINGEPLANT) {
            return self::ANZEIGE_EINGEPLANT;
        }
        if ($this->status !== self::STATUS_ABGEDECKT) {
            return self::ANZEIGE_OFFEN;
        }
        foreach ($this->leistungskriterien as $lkstatus) {
            if ($lkstatus !== self::STATUS_ABGEDECKT) {
                return self::ANZEIGE_TEILWEISE;
            }
        }

        return self::ANZEIGE_VOLLSTAENDIG;
    }

    /**
     * Anzahl Leistungskriterien dieser HK mit dem gegebenen Stand.
     *
     * @param string $status Eine der STATUS_*-Konstanten
     */
    public function anzahl_lk(string $status): int {
        return count(array_filter(
            $this->leistungskriterien,
            static fn (string $lkstatus): bool => $lkstatus === $status
        ));
    }

    /**
     * Kam diese Handlungskompetenz bis zum Stichtag in einem Einsatz vor?
     */
    public function ist_abgedeckt(): bool {
        return $this->status === self::STATUS_ABGEDECKT;
    }
}

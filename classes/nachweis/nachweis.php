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
 * Wertobjekt fuer einen einzelnen Nachweis aus einer registrierten Quelle.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\nachweis;

/**
 * Bewusst arm gehalten: keine Notenlogik, keine quellenspezifischen Skalen.
 * Eine Quelle, die differenzierter arbeitet, verlinkt ueber $url auf ihre
 * eigene Detailansicht.
 *
 * Einzelne readonly-Properties statt einer readonly-Klasse, damit die
 * Klasse auch unter PHP 8.1 (Moodle 4.5) funktioniert.
 */
class nachweis {
    /**
     * Konstruktor.
     *
     * @param string $quellekey Schluessel der liefernden Quelle, z.B. 'lerndokumentation'
     * @param string $bezeichnung z.B. "üK 3: Steuerungstechnik"
     * @param int $datum Timestamp
     * @param string|null $ergebnis z.B. '5.0' | 'bestanden' | null, falls die Quelle keine Bewertung fuehrt
     * @param int|null $competencyid Kompetenz aus core_competency, falls die Quelle Kompetenzen kennt
     * @param string|null $url Link zur Detailansicht in der liefernden Quelle
     */
    public function __construct(
        /** @var string Schluessel der liefernden Quelle, z.B. 'lerndokumentation'. */
        public readonly string $quellekey,
        /** @var string z.B. "üK 3: Steuerungstechnik". */
        public readonly string $bezeichnung,
        /** @var int Timestamp. */
        public readonly int $datum,
        /** @var string|null z.B. '5.0' | 'bestanden' | null, falls die Quelle keine Bewertung fuehrt. */
        public readonly ?string $ergebnis,
        /** @var int|null Kompetenz aus core_competency, falls die Quelle Kompetenzen kennt. */
        public readonly ?int $competencyid,
        /** @var string|null Link zur Detailansicht in der liefernden Quelle. */
        public readonly ?string $url,
    ) {
    }
}

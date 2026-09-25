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
 * Wertobjekt fuer einen noch ausstehenden Nachweis aus einer registrierten Quelle.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\nachweis;

/**
 * Etwas, das eine Quelle fuer die Person noch erwartet - etwa ein ueK, der
 * geplant oder noch nicht eingeplant ist. Ein Soll, kein Nachweis: kein
 * Ergebnis, kein Link.
 *
 * Wie `nachweis` bewusst arm gehalten. Den Stand formuliert die Quelle
 * selbst ("geplant · 12. April 2027 bis 16. April 2027", "noch nicht
 * eingeplant") - das Basis-Plugin zeigt ihn unveraendert und deutet ihn
 * nicht.
 *
 * Einzelne readonly-Properties statt einer readonly-Klasse, damit die
 * Klasse auch unter PHP 8.1 (Moodle 4.5) funktioniert.
 */
class ausstehend {
    /**
     * Konstruktor.
     *
     * @param string $quellekey Schluessel der liefernden Quelle
     * @param string $bezeichnung z.B. "üK 4: Steuerungen programmieren"
     * @param string $stand z.B. "geplant · 12. April 2027 bis 16. April 2027"
     */
    public function __construct(
        /** @var string Schluessel der liefernden Quelle. */
        public readonly string $quellekey,
        /** @var string z.B. "üK 4: Steuerungen programmieren". */
        public readonly string $bezeichnung,
        /** @var string Stand, von der Quelle formuliert. */
        public readonly string $stand,
    ) {
    }
}

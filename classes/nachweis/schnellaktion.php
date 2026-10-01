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
 * Wertobjekt fuer eine Schnellaktion auf der geschlossenen Roster-Kachel.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\nachweis;

/**
 * Einzelne readonly-Properties statt einer readonly-Klasse, damit die
 * Klasse auch unter PHP 8.1 (Moodle 4.5) funktioniert - gleiches Vorgehen
 * wie bei `erfassen_aktion`.
 */
class schnellaktion {
    /**
     * Konstruktor.
     *
     * @param string $quellekey Schluessel der liefernden Quelle, z.B. 'bildungsbericht'
     * @param string $label Beschriftung der Schaltflaeche, z.B. "Notiz (3)"
     * @param string $url Ziel-URL, gilt ohne JavaScript und als Ausweg, wenn das AMD-Modul nicht laedt
     * @param string $icon Font-Awesome-Klasse ohne Praefix "fa ", z.B. 'fa-sticky-note'
     * @param string|null $amdmodul AMD-Modul, das den Klick uebernimmt, z.B. 'local_bildungsbericht/notiz'
     */
    public function __construct(
        /** @var string Schluessel der liefernden Quelle, z.B. 'bildungsbericht'. */
        public readonly string $quellekey,
        /** @var string Beschriftung der Schaltflaeche, z.B. "Notiz (3)". */
        public readonly string $label,
        /** @var string Ziel-URL, gilt ohne JavaScript. */
        public readonly string $url,
        /** @var string Font-Awesome-Klasse ohne Praefix "fa ", z.B. 'fa-sticky-note'. */
        public readonly string $icon,
        /** @var string|null AMD-Modul mit init(), das den Klick uebernimmt. */
        public readonly ?string $amdmodul = null,
    ) {
    }
}

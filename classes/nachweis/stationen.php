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
 * Die Stationen einer Quelle fuer eine Person, wie sie der Collector liefert.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\nachweis;

/**
 * Was collector::get_stationen() je Quelle liefert: die Stationen samt
 * Anzeigename der Quelle und Link auf ihren ganzen Verlauf.
 *
 * Einzelne readonly-Properties statt einer readonly-Klasse, damit die
 * Klasse auch unter PHP 8.1 (Moodle 4.5) funktioniert.
 */
class stationen {
    /**
     * Konstruktor.
     *
     * @param string $quellekey Schluessel der liefernden Quelle
     * @param string $titel Anzeigename der Quelle
     * @param station[] $eintraege In zeitlicher Reihenfolge
     * @param string|null $verlaufurl Siehe quelle_mit_stationen::get_verlauf_url()
     */
    public function __construct(
        /** @var string Schluessel der liefernden Quelle. */
        public readonly string $quellekey,
        /** @var string Anzeigename der Quelle. */
        public readonly string $titel,
        /** @var station[] In zeitlicher Reihenfolge. */
        public readonly array $eintraege,
        /** @var string|null Ganzer Verlauf in der Quelle. */
        public readonly ?string $verlaufurl = null,
    ) {
    }

    /**
     * Die Station, die gerade zaehlt, oder null.
     *
     * @return station|null
     */
    public function aktuelle(): ?station {
        foreach ($this->eintraege as $eintrag) {
            if ($eintrag->aktuell) {
                return $eintrag;
            }
        }

        return null;
    }
}

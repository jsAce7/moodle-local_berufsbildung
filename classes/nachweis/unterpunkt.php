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

declare(strict_types=1);

namespace local_berufsbildung\nachweis;

/**
 * Was zu einer Station gehoert, ohne selbst eine zu sein - etwa die
 * Arbeitsplatzberichte, die in den Bildungsbericht eines Semesters
 * einfliessen. Die Zeitleiste zeigt Unterpunkte klein unter ihrer Station.
 *
 * Einzelne readonly-Properties statt einer readonly-Klasse, damit die
 * Klasse auch unter PHP 8.1 (Moodle 4.5) funktioniert.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class unterpunkt {
    /**
     * Konstruktor.
     *
     * @param string $bezeichnung z.B. der Name der Abteilung
     * @param string $status Eine der Konstanten station::STATUS_*, nur fuer Symbol und Farbe
     * @param string $statustext Bedeutung des Status in Worten, z.B. "besprochen"
     * @param string|null $url Wohin ein Klick fuehrt, null ohne Ziel
     */
    public function __construct(
        /** @var string z.B. der Name der Abteilung. */
        public readonly string $bezeichnung,
        /** @var string Eine der Konstanten station::STATUS_*. */
        public readonly string $status,
        /** @var string Bedeutung des Status in Worten. */
        public readonly string $statustext,
        /** @var string|null Ziel eines Klicks. */
        public readonly ?string $url = null,
    ) {
    }
}

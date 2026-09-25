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
 * Optionale Zusatzschnittstelle fuer Quellen, die wissen, was noch aussteht.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\nachweis;

/**
 * Zusaetzlich zu `provider` implementierbar von Quellen, die ueber die
 * gelieferten Nachweise hinaus wissen, was fuer die Person noch aussteht -
 * etwa die ueK des Berufs, die sie noch nicht besucht hat. Die
 * Taetigkeitenliste zeigt sie unter den Nachweisen dieser Quelle, damit auf
 * einen Blick ersichtlich ist, was schon da ist und was noch kommt.
 *
 * Was als ausstehend gilt, entscheidet die Quelle allein - sie kennt ihr
 * Soll, das Basis-Plugin nicht. Ein Eintrag, zu dem bereits ein
 * angezeigter Nachweis besteht, gehoert nicht in diese Liste.
 *
 * Aufgerufen wird nur ueber collector::get_ausstehende(), und erst nach
 * der Zustaendigkeitspruefung dort (Architekturregel 7) - die Quelle prueft
 * keine eigene Berechtigung. Wie bei `provider::get_nachweise()` gilt fuer
 * die eigene Sicht dieselbe Sichtbarkeitsregel wie bei den Nachweisen.
 *
 * Getrennt von `provider` gehalten, damit eine Quelle ohne Soll nichts
 * implementieren muss und eine Quelle mit Soll pruefen kann, ob dieses
 * Plugin die Schnittstelle schon kennt.
 */
interface quelle_mit_ausstehenden {
    /**
     * Was fuer die Person noch aussteht, in Anzeigereihenfolge.
     *
     * @param int $lernendeid
     * @return ausstehend[] Leer, wenn nichts aussteht oder die Quelle das Soll nicht kennt
     */
    public function get_ausstehende(int $lernendeid): array;
}

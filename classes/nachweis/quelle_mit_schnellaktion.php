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
 * Optionale Zusatzschnittstelle fuer Quellen mit einer Schnellaktion auf
 * der geschlossenen Kachel von "Meine Lernenden".
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\nachweis;

/**
 * Fuer Handlungen, die die zustaendige Berufsbildner/in jederzeit und
 * nebenbei fuer eine lernende Person ausfuehrt - etwa eine Notiz zum
 * Bildungsbericht. Die Schaltflaeche steht in der Kopfzeile der Kachel,
 * die Person muss dafuer nicht aufgeklappt werden.
 *
 * Bewusst getrennt von `quelle_mit_zustaendigen_aktion`: jene beantwortet
 * "was steht als Naechstes an" und gehoert ins Detail, neben Faelligkeiten
 * und Raster. Eine Schnellaktion steht immer an, unabhaengig vom
 * Ausbildungsstand, und eine Quelle kann beides anbieten.
 *
 * Die Zustaendigkeit prueft der Collector, bevor er die Quelle fragt
 * (Architekturregel 7), die Capability die Quelle selbst.
 */
interface quelle_mit_schnellaktion {
    /**
     * Die Schnellaktion der abrufenden Person fuer die lernende Person,
     * oder null, wenn sie hier nichts tun darf. Den Quellen-Schluessel der
     * Aktion setzt der Collector, nicht die Quelle.
     *
     * Bringt die Aktion ein AMD-Modul mit, laedt "Meine Lernenden" es
     * einmal je Seite mit init() ohne Parameter. Das Modul erkennt seine
     * Schaltflaechen am Attribut
     * `data-local-berufsbildung-schnellaktion="<quellekey>"`, die
     * betroffene Person an `data-lernendeid`. Ohne JavaScript fuehrt der
     * Link auf die URL der Aktion.
     *
     * @param int $abrufendeid Die zustaendige Person, die abruft
     * @param int $lernendeid Fuer wen
     * @return schnellaktion|null
     */
    public function get_schnellaktion(int $abrufendeid, int $lernendeid): ?schnellaktion;
}

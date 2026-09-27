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
 * Optionale Zusatzschnittstelle fuer Quellen, in denen die zustaendige
 * Berufsbildner/in fuer eine lernende Person erfasst.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\nachweis;

/**
 * Das Gegenstueck zu `erfassbare_quelle`: dort erfasst die lernende Person
 * fuer sich selbst, hier die zustaendige Berufsbildner/in fuer sie - etwa
 * den Bildungsbericht. "Meine Lernenden" zeigt die Aktion im Detail der
 * Person als Schaltflaeche.
 *
 * Bewusst ein eigenes Interface und keine Erweiterung von
 * `erfassbare_quelle`: deren Vertrag ist fest auf "nur die eigene Person"
 * ausgelegt (collector::get_erfassen_aktionen() prueft Identitaet). Hier
 * prueft der Collector stattdessen die Zustaendigkeit, bevor die Quelle
 * gefragt wird (Architekturregel 7).
 *
 * Eine Aktion statt URL und Beschriftung getrennt: was als Naechstes zu tun
 * ist ("3. Semester anlegen" oder "Probezeitbericht erfassen"), bestimmt
 * Ziel und Beschriftung zugleich, und die Quelle muss es nur einmal
 * ermitteln.
 */
interface quelle_mit_zustaendigen_aktion {
    /**
     * Was die abrufende Person fuer die lernende Person als Naechstes
     * erfassen kann, oder null, wenn gerade nichts ansteht oder sie in
     * dieser Quelle nicht erfassen darf. Die fachliche Berechtigung
     * (Capability) prueft die Quelle selbst - der Collector kennt nur die
     * Zustaendigkeit.
     *
     * Den Quellen-Schluessel der Aktion setzt der Collector, nicht die
     * Quelle.
     *
     * @param int $abrufendeid Die zustaendige Person, die abruft
     * @param int $lernendeid Fuer wen
     * @return erfassen_aktion|null
     */
    public function get_zustaendigen_aktion(int $abrufendeid, int $lernendeid): ?erfassen_aktion;
}

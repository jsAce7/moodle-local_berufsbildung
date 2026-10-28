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
 * Interface fuer Quellen mit festen Stationen ueber die ganze Lehre.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\nachweis;

/**
 * Zusaetzlich zu `provider` implementierbar von Quellen, deren Schritte
 * ueber die ganze Lehre feststehen - etwa der Bildungsbericht mit
 * Probezeit und je einem Bericht pro Semester. "Meine Lernenden" zeigt die
 * aktuelle Station in einer eigenen Spalte der Kachel und alle Stationen
 * als Zeitleiste zuoberst im Detail; "Meine Lehre" zeigt die Zeitleiste
 * der eigenen Person unter der Kopfkarte und laesst dort die
 * Semesterleiste weg, die dasselbe zeigt.
 *
 * Eine Quelle mit Stationen zeigt dort alles, was sie fuer die Person hat:
 * ihre Faelligkeiten, Nachweise und die Aktion fuer Zustaendige stehen
 * nicht noch einmal in den allgemeinen Listen. Die Erfassen-Aktion fuer
 * die eigene Person bleibt zusaetzlich oben in der Kopfkarte, bei den
 * anderen Aufgaben.
 *
 * Aufgerufen nur ueber collector::get_stationen(), fuer die eigene Person
 * oder eine, fuer die die abrufende Person heute zustaendig ist
 * (Architekturregel 7). Fragt die Person fuer sich selbst
 * ($abrufendeid === $lernendeid), gilt wie bei `provider::get_nachweise()`
 * ihre eigene Sichtbarkeitsregel - die Quelle formuliert dann auch fuer
 * sie. Die fachliche Berechtigung, etwa fuer das Ziel eines Klicks, prueft
 * die Quelle selbst.
 */
interface quelle_mit_stationen {
    /**
     * Alle Stationen der Lehre in zeitlicher Reihenfolge, leer, wenn die
     * Quelle fuer die Person keine kennt.
     *
     * @param int $abrufendeid Die zustaendige Person, die abruft, oder die Person selbst
     * @param int $lernendeid Fuer wen
     * @return station[]
     */
    public function get_stationen(int $abrufendeid, int $lernendeid): array;

    /**
     * Seite mit dem ganzen Verlauf der Person in dieser Quelle, oder null,
     * wenn es keine gibt oder die abrufende Person sie nicht oeffnen darf.
     *
     * @param int $abrufendeid
     * @param int $lernendeid
     * @return string|null
     */
    public function get_verlauf_url(int $abrufendeid, int $lernendeid): ?string;
}

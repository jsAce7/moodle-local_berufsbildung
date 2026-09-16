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
 * Optionale Zusatzschnittstelle fuer Nachweis-Quellen mit eigener Erfassung.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\nachweis;

use moodle_url;

/**
 * Zusaetzlich zu `provider` implementierbar von Quellen, bei denen die
 * lernende Person selbst einen neuen Eintrag erfassen kann - "Meine Lehre"
 * zeigt dafuer pro registrierter Quelle eine Schaltflaeche. Bewusst von
 * `provider` getrennt: nicht jede Quelle kennt eine eigene Erfassung (z.B.
 * üK-Nachweise, die durch das Kursteam freigegeben werden, nicht durch die
 * lernende Person selbst).
 */
interface erfassbare_quelle {
    /**
     * URL zum Erfassen eines neuen Eintrags fuer die lernende Person, oder
     * null, wenn aktuell nichts zu erfassen ist. Wird nur fuer die eigene
     * Person aufgerufen - siehe collector::get_erfassen_aktionen(), das die
     * Identitaetspruefung uebernimmt, nicht der Provider selbst.
     *
     * @param int $lernendeid
     * @return moodle_url|null
     */
    public function get_erfassen_url(int $lernendeid): ?moodle_url;

    /**
     * Beschriftung der Erfassen-Schaltflaeche. Muss die eigene Quelle
     * benennen, z.B. "Neuen Lerndoku-Eintrag erfassen": In "Meine Lehre"
     * stehen die Schaltflaechen mehrerer Quellen nebeneinander, ein blosses
     * "Neuer Eintrag" laesst dort offen, welcher Eintrag gemeint ist.
     */
    public function get_erfassen_label(): string;
}

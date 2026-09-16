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
 * Schnittstelle fuer Plugins, die Nachweise fuer lernende Personen liefern.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\nachweis;

/**
 * Registrierung ueber den Standard-Plugin-Callback
 * "<component>_berufsbildung_nachweis_provider()" in lib.php - siehe
 * docs/plan.md Abschnitt 6. Wird ueber get_plugins_with_function()
 * eingesammelt, das Basis-Plugin kennt keine einzelne Quelle namentlich.
 */
interface provider {
    /**
     * Nachweise fuer eine lernende Person in einem Zeitraum.
     *
     * Wird nur aufgerufen, nachdem local_berufsbildung die Zustaendigkeit
     * geprueft hat - siehe collector::get_nachweise(). Ein Provider prueft
     * selbst nichts.
     *
     * @param int $lernendeid
     * @param int $von Timestamp
     * @param int $bis Timestamp
     * @return nachweis[]
     */
    public function get_nachweise(int $lernendeid, int $von, int $bis): array;

    /**
     * Anzeigename der Quelle, z.B. "Lerndokumentation".
     */
    public function get_quelle_name(): string;

    /**
     * Eindeutiger Schluessel der Quelle, z.B. 'lerndokumentation'.
     */
    public function get_quelle_key(): string;
}

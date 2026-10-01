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
 * Test-Provider mit einer Schnellaktion fuer die Roster-Kachel.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\nachweis;

/**
 * Liefert eine vorgegebene Schnellaktion, wahlweise keine. Die Aktion
 * traegt absichtlich einen fremden Quellen-Schluessel, damit sich pruefen
 * laesst, dass der Collector ihn durch den der Quelle ersetzt.
 */
final class collector_test_schnellaktion_provider implements provider, quelle_mit_schnellaktion {
    /** @var bool Ob get_schnellaktion() aufgerufen wurde. */
    public bool $wurdeaufgerufen = false;

    /**
     * Konstruktor.
     *
     * @param schnellaktion|null $aktion Aktion, null wenn die Person hier nichts tun darf
     */
    public function __construct(
        /** @var schnellaktion|null Aktion, null wenn die Person hier nichts tun darf. */
        private readonly ?schnellaktion $aktion = null
    ) {
    }

    /**
     * Diese Quelle liefert keine Nachweise, nur eine Schnellaktion.
     *
     * @param int $lernendeid
     * @param int $von
     * @param int $bis
     * @return array
     */
    public function get_nachweise(int $lernendeid, int $von, int $bis): array {
        return [];
    }

    /**
     * Anzeigename der Quelle.
     *
     * @return string
     */
    public function get_quelle_name(): string {
        return 'Test-Quelle mit Schnellaktion';
    }

    /**
     * Schluessel der Quelle.
     *
     * @return string
     */
    public function get_quelle_key(): string {
        return 'testschnell';
    }

    /**
     * Die vorgegebene Aktion und Vermerk, dass danach gefragt wurde.
     *
     * @param int $abrufendeid
     * @param int $lernendeid
     * @return schnellaktion|null
     */
    public function get_schnellaktion(int $abrufendeid, int $lernendeid): ?schnellaktion {
        $this->wurdeaufgerufen = true;

        return $this->aktion;
    }
}

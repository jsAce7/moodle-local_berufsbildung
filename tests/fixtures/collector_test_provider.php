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
 * Test-Provider, der jeden Aufruf protokolliert.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\nachweis;

/**
 * Test-Provider, der jeden Aufruf protokolliert - damit sichtbar wird, ob
 * er ueberhaupt gefragt wurde (Architekturregel 7: nie ungeprueft
 * aufrufen).
 */
final class collector_test_provider implements provider {
    /** @var bool Ob get_nachweise() aufgerufen wurde. */
    public bool $wurdeaufgerufen = false;

    /**
     * Konstruktor.
     *
     * @param array $nachweise Nachweise, die dieser Provider liefern soll
     */
    public function __construct(
        /** @var array Nachweise, die dieser Provider liefern soll. */
        private readonly array $nachweise = []
    ) {
    }

    /**
     * Liefert die vorgegebenen Nachweise und vermerkt den Aufruf.
     *
     * @param int $lernendeid
     * @param int $von
     * @param int $bis
     * @return array
     */
    public function get_nachweise(int $lernendeid, int $von, int $bis): array {
        $this->wurdeaufgerufen = true;

        return $this->nachweise;
    }

    /**
     * Anzeigename der Quelle.
     *
     * @return string
     */
    public function get_quelle_name(): string {
        return 'Test-Quelle';
    }

    /**
     * Schluessel der Quelle.
     *
     * @return string
     */
    public function get_quelle_key(): string {
        return 'test';
    }
}

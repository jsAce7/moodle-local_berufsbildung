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
 * Test-Provider mit Ausstehendem.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\nachweis;

defined('MOODLE_INTERNAL') || die();

/**
 * Test-Provider, der festhaelt, ob er nach Ausstehendem gefragt wurde -
 * damit pruefbar ist, dass der Collector vorher die Zustaendigkeit prueft
 * (Architekturregel 7).
 */
final class collector_test_ausstehend_provider implements provider, quelle_mit_ausstehenden {
    /** @var bool Ob get_ausstehende() aufgerufen wurde. */
    public bool $wurdeaufgerufen = false;

    /**
     * Konstruktor.
     *
     * @param ausstehend[] $ausstehende Was get_ausstehende() liefern soll
     */
    public function __construct(
        /** @var ausstehend[] Was get_ausstehende() liefern soll. */
        private readonly array $ausstehende = []
    ) {
    }

    /**
     * Wird hier nicht gebraucht.
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
        return 'Test-Quelle mit Soll';
    }

    /**
     * Schluessel der Quelle.
     *
     * @return string
     */
    public function get_quelle_key(): string {
        return 'soll';
    }

    /**
     * Liefert das vorgegebene Ausstehende und vermerkt den Aufruf.
     *
     * @param int $lernendeid
     * @return ausstehend[]
     */
    public function get_ausstehende(int $lernendeid): array {
        $this->wurdeaufgerufen = true;

        return $this->ausstehende;
    }
}

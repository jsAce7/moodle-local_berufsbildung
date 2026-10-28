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
 * Test-Provider mit Stationen ueber die Lehre.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\nachweis;

/**
 * Liefert vorgegebene Stationen, wahlweise keine. Die Stationen duerfen
 * einen fremden Quellen-Schluessel tragen, damit sich pruefen laesst, dass
 * der Collector ihn durch den der Quelle ersetzt.
 */
final class collector_test_stationen_provider implements provider, quelle_mit_stationen {
    /** @var bool Ob get_stationen() aufgerufen wurde. */
    public bool $wurdeaufgerufen = false;

    /**
     * Konstruktor.
     *
     * @param station[] $stationen
     * @param string|null $verlaufurl
     */
    public function __construct(
        /** @var station[] Die vorgegebenen Stationen. */
        private readonly array $stationen = [],
        /** @var string|null Der vorgegebene Verlauf. */
        private readonly ?string $verlaufurl = null
    ) {
    }

    /**
     * Diese Quelle liefert keine Nachweise, nur Stationen.
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
        return 'Test-Quelle mit Stationen';
    }

    /**
     * Schluessel der Quelle.
     *
     * @return string
     */
    public function get_quelle_key(): string {
        return 'teststationen';
    }

    /**
     * Die vorgegebenen Stationen und Vermerk, dass danach gefragt wurde.
     *
     * @param int $abrufendeid
     * @param int $lernendeid
     * @return station[]
     */
    public function get_stationen(int $abrufendeid, int $lernendeid): array {
        $this->wurdeaufgerufen = true;

        return $this->stationen;
    }

    /**
     * Der vorgegebene Verlauf.
     *
     * @param int $abrufendeid
     * @param int $lernendeid
     * @return string|null
     */
    public function get_verlauf_url(int $abrufendeid, int $lernendeid): ?string {
        return $this->verlaufurl;
    }
}

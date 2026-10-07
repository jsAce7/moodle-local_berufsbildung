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
 * Test-Provider mit Zusammenfassung.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\nachweis;

/**
 * Test-Provider, der festhaelt, welche Nachweise er zum Zusammenfassen
 * bekommen hat - damit pruefbar ist, dass jede Quelle nur ihre eigenen
 * sieht.
 */
final class collector_test_zusammenfassung_provider implements provider, quelle_mit_zusammenfassung {
    /** @var nachweis[]|null Die zuletzt uebergebenen Nachweise, null wenn nie gefragt. */
    public ?array $erhaltene = null;

    /**
     * Konstruktor.
     *
     * @param string $quellekey
     * @param string|null $zusammenfassung Was get_zusammenfassung() liefern soll
     */
    public function __construct(
        /** @var string Schluessel der Quelle. */
        private readonly string $quellekey,
        /** @var string|null Was get_zusammenfassung() liefern soll. */
        private readonly ?string $zusammenfassung
    ) {
    }

    /**
     * Wird hier nicht gebraucht - der Collector bekommt die Nachweise direkt.
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
        return 'Test-Quelle ' . $this->quellekey;
    }

    /**
     * Schluessel der Quelle.
     *
     * @return string
     */
    public function get_quelle_key(): string {
        return $this->quellekey;
    }

    /**
     * Vermerkt die Nachweise und liefert die vorgegebene Zusammenfassung.
     *
     * @param nachweis[] $nachweise
     * @return string|null
     */
    public function get_zusammenfassung(array $nachweise): ?string {
        $this->erhaltene = $nachweise;

        return $this->zusammenfassung;
    }
}

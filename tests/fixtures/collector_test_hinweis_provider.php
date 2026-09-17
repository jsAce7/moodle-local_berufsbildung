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
 * Test-Provider mit eigener Erfassung und Hinweis dazu.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\nachweis;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/collector_test_erfassbarer_provider.php');

/**
 * Wie collector_test_erfassbarer_provider, zusaetzlich mit Hinweis - der
 * Hinweis ist vorgebbar, damit auch die Quelle geprueft werden kann, die
 * `quelle_mit_hinweis` zwar implementiert, aber gerade nichts zu sagen hat.
 */
final class collector_test_hinweis_provider extends collector_test_erfassbarer_provider implements quelle_mit_hinweis {
    /** @var bool Ob get_erfassen_hinweis() aufgerufen wurde. */
    public bool $hinweiswurdeaufgerufen = false;

    /**
     * Konstruktor.
     *
     * @param string|null $url Erfassungs-URL, null wenn nichts anzubieten ist
     * @param string|null $hinweis Hinweis, null wenn es nichts zu sagen gibt
     */
    public function __construct(
        ?string $url = '/local/test/edit.php',
        /** @var string|null Hinweis, null wenn es nichts zu sagen gibt. */
        private readonly ?string $hinweis = 'Faellig bis morgen'
    ) {
        parent::__construct($url);
    }

    /**
     * Schluessel der Quelle.
     *
     * @return string
     */
    public function get_quelle_key(): string {
        return 'testhinweis';
    }

    /**
     * Hinweis und Vermerk, dass danach gefragt wurde.
     *
     * @param int $lernendeid
     * @return string|null
     */
    public function get_erfassen_hinweis(int $lernendeid): ?string {
        $this->hinweiswurdeaufgerufen = true;

        return $this->hinweis;
    }
}

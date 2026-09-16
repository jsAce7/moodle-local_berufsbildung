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
 * Test-Provider mit eigener Erfassung.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\nachweis;

defined('MOODLE_INTERNAL') || die();

/**
 * Test-Provider mit eigener Erfassung, wahlweise ohne Erfassungs-URL - fuer
 * den Fall, dass eine Quelle zwar `erfassbare_quelle` implementiert, aber
 * aktuell nichts anzubieten hat (z.B. fehlende Berechtigung).
 */
final class collector_test_erfassbarer_provider implements erfassbare_quelle, provider {
    /** @var bool Ob get_erfassen_url() aufgerufen wurde. */
    public bool $erfassenwurdeaufgerufen = false;

    /**
     * Konstruktor.
     *
     * @param string|null $url Erfassungs-URL, null wenn nichts anzubieten ist
     */
    public function __construct(
        /** @var string|null Erfassungs-URL, null wenn nichts anzubieten ist. */
        private readonly ?string $url = '/local/test/edit.php'
    ) {
    }

    /**
     * Diese Quelle liefert keine Nachweise, nur eine Erfassung.
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
        return 'Erfassbare Test-Quelle';
    }

    /**
     * Schluessel der Quelle.
     *
     * @return string
     */
    public function get_quelle_key(): string {
        return 'testerfassbar';
    }

    /**
     * Erfassungs-URL und Vermerk, dass danach gefragt wurde.
     *
     * @param int $lernendeid
     * @return moodle_url|null
     */
    public function get_erfassen_url(int $lernendeid): ?\moodle_url {
        $this->erfassenwurdeaufgerufen = true;

        return $this->url === null ? null : new \moodle_url($this->url);
    }

    /**
     * Beschriftung des Erfassungs-Links.
     *
     * @return string
     */
    public function get_erfassen_label(): string {
        return 'Neuer Test-Eintrag';
    }
}

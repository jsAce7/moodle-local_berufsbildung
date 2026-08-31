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
 * Loest die Lehrdauer eines Berufs auf, getrennt von der Konfigurationsablage.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\service;

/**
 * Verschiedene Berufe koennen unterschiedlich lange dauern (z. B. drei statt
 * vier Lehrjahre). Diese Klasse kennt weder Moodle noch get_config() - sie
 * nimmt die Rohkonfiguration als String entgegen, damit sie ohne Admin-
 * Einstellungen testbar ist.
 */
class lehrdauer_resolver {

    /**
     * Lehrdauer in Semestern fuer einen Beruf.
     *
     * @param string $beruf Beruf-Code, z. B. 'AU_EFZ'
     * @param string $konfiguration Eine Zeile je Beruf im Format 'CODE=Semester',
     *                               z. B. "AU_EFZ=8\nPM_EFZ=6". Zeilen ohne
     *                               '=' oder mit nicht-numerischem Wert werden
     *                               uebersprungen.
     * @param int $standard Lehrdauer in Semestern, falls der Beruf in der
     *                       Konfiguration nicht vorkommt.
     * @return int Lehrdauer in Semestern
     */
    public function loese_auf(string $beruf, string $konfiguration, int $standard): int {
        foreach (preg_split('/\r\n|\r|\n/', $konfiguration) as $zeile) {
            $zeile = trim($zeile);
            if ($zeile === '' || !str_contains($zeile, '=')) {
                continue;
            }

            [$code, $semester] = array_map('trim', explode('=', $zeile, 2));
            if ($code === $beruf && $semester !== '' && ctype_digit($semester)
                    && (int) $semester >= 1 && (int) $semester <= 8) {
                return (int) $semester;
            }
        }

        return min(8, max(1, $standard));
    }
}

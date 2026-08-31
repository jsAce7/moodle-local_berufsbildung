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
 * Loest den Kompetenzrahmen eines Berufs auf, getrennt von der
 * Konfigurationsablage.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\service;

/**
 * Verschiedene Berufe werden gegen unterschiedliche core_competency-Rahmen
 * ausgebildet. Diese Klasse kennt weder Moodle noch get_config() - sie
 * nimmt die Rohkonfiguration als String entgegen, damit sie ohne Admin-
 * Einstellungen testbar ist (gleiches Muster wie lehrdauer_resolver).
 */
class rahmen_resolver {

    /**
     * Framework-idnumber fuer einen Beruf, oder null wenn nicht
     * konfiguriert. Absichtlich die idnumber (nicht die id) - core_competency
     * bleibt die geteilte Grundlage, hier wird nur der Zeiger darauf verwaltet
     * (siehe CLAUDE.md "Was nicht in dieses Plugin gehört").
     *
     * @param string $beruf Beruf-Code, z. B. 'AU_EFZ'
     * @param string $konfiguration Eine Zeile je Beruf im Format
     *                               'CODE=framework_idnumber', z. B.
     *                               "AU_EFZ=au-2022\nPM_EFZ=pm-2022". Zeilen
     *                               ohne '=' werden uebersprungen.
     * @return string|null
     */
    public function loese_auf(string $beruf, string $konfiguration): ?string {
        foreach (preg_split('/\r\n|\r|\n/', $konfiguration) as $zeile) {
            $zeile = trim($zeile);
            if ($zeile === '' || !str_contains($zeile, '=')) {
                continue;
            }

            [$code, $idnumber] = array_map('trim', explode('=', $zeile, 2));
            if ($code === $beruf && $idnumber !== '') {
                return $idnumber;
            }
        }

        return null;
    }

    /**
     * Alle in der Konfiguration hinterlegten Beruf-Codes, in der
     * vorkommenden Reihenfolge - fuer Auswahllisten (z.B. beim manuellen
     * Anlegen eines Ausbildungsblocks).
     *
     * @param string $konfiguration Format wie bei loese_auf()
     * @return string[]
     */
    public function alle_codes(string $konfiguration): array {
        $codes = [];

        foreach (preg_split('/\r\n|\r|\n/', $konfiguration) as $zeile) {
            $zeile = trim($zeile);
            if ($zeile === '' || !str_contains($zeile, '=')) {
                continue;
            }

            [$code, $idnumber] = array_map('trim', explode('=', $zeile, 2));
            if ($code !== '' && $idnumber !== '') {
                $codes[$code] = true;
            }
        }

        return array_keys($codes);
    }
}

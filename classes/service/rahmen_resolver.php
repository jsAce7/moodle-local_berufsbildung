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
     * Zerlegt die Konfiguration in ihre Eintraege.
     *
     * @param string $konfiguration Eine Zeile je Beruf im Format
     *                               'CODE=framework_idnumber', z. B.
     *                               "AU_EFZ=au-2022\nPM_EFZ=pm-2022". Zeilen
     *                               ohne '=' werden uebersprungen.
     * @return array<string, string> Beruf-Code => Framework-idnumber, in der
     *                                vorkommenden Reihenfolge
     */
    private function parse(string $konfiguration): array {
        $paare = [];

        foreach (preg_split('/\r\n|\r|\n/', $konfiguration) as $zeile) {
            $zeile = trim($zeile);
            if ($zeile === '' || !str_contains($zeile, '=')) {
                continue;
            }

            [$code, $idnumber] = array_map('trim', explode('=', $zeile, 2));
            if ($code !== '' && $idnumber !== '') {
                $paare[$code] = $idnumber;
            }
        }

        return $paare;
    }

    /**
     * Framework-idnumber fuer einen Beruf, oder null wenn nicht
     * konfiguriert. Absichtlich die idnumber (nicht die id) - core_competency
     * bleibt die geteilte Grundlage, hier wird nur der Zeiger darauf verwaltet
     * (siehe CLAUDE.md "Was nicht in dieses Plugin gehört").
     *
     * @param string $beruf Beruf-Code, z. B. 'AU_EFZ'
     * @param string $konfiguration Format wie bei parse()
     * @return string|null
     */
    public function loese_auf(string $beruf, string $konfiguration): ?string {
        return $this->parse($konfiguration)[$beruf] ?? null;
    }

    /**
     * Alle in der Konfiguration hinterlegten Beruf-Codes, in der
     * vorkommenden Reihenfolge - fuer Auswahllisten (z.B. beim manuellen
     * Anlegen eines Ausbildungsblocks).
     *
     * @param string $konfiguration Format wie bei parse()
     * @return string[]
     */
    public function alle_codes(string $konfiguration): array {
        return array_keys($this->parse($konfiguration));
    }

    /**
     * Die vollstaendige Zuordnung Beruf-Code => Framework-idnumber, fuer
     * die Verwaltungsseite (beruf_rahmen.php).
     *
     * @param string $konfiguration Format wie bei parse()
     * @return array<string, string>
     */
    public function alle_paare(string $konfiguration): array {
        return $this->parse($konfiguration);
    }

    /**
     * Kehrfunktion zu parse()/alle_paare(): baut aus der Zuordnung wieder
     * den Konfigurations-String, damit die Verwaltungsseite ohne eigenes
     * Wissen um das Zeilenformat auskommt.
     *
     * @param array<string, string> $paare Beruf-Code => Framework-idnumber
     * @return string
     */
    public function serialisiere(array $paare): string {
        $zeilen = [];
        foreach ($paare as $code => $idnumber) {
            $zeilen[] = "{$code}={$idnumber}";
        }

        return implode("\n", $zeilen);
    }
}

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
 * Loest die Wahlpflicht-HK eines Berufs aus der Administrationseinstellung auf.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\service;

/**
 * Die Bildungspläne kennzeichnen Handlungskompetenzen als P oder W. Die
 * Kennzeichnung bleibt ausserhalb von core_competency: Der Rahmen beschreibt
 * die Kompetenz selbst, die Pflicht hängt dagegen vom Beruf ab.
 */
class wahlpflicht_resolver {
    /**
     * Loest die konfigurierte Angabe auf.
     *
     * @param string $beruf Beruf-Code, z. B. AU_EFZ
     * @param string $konfiguration Zeilen im Format
     *     CODE=hk-idnumber,hk-idnumber, z. B. AU_EFZ=7777 a.04,7777 a.05
     * @return string[] Idnumbers der Wahlpflicht-HK
     */
    public function loese_auf(string $beruf, string $konfiguration): array {
        foreach (preg_split('/\r\n|\r|\n/', $konfiguration) as $zeile) {
            $zeile = trim($zeile);
            if ($zeile === '' || !str_contains($zeile, '=')) {
                continue;
            }

            [$code, $idnumbers] = array_map('trim', explode('=', $zeile, 2));
            if ($code !== $beruf || $idnumbers === '') {
                continue;
            }

            return array_values(array_filter(array_map('trim', explode(',', $idnumbers))));
        }

        return [];
    }

    /**
     * Wie viele Wahlpflicht-HK der Bildungsplan eines Berufs verlangt.
     *
     * @param string $beruf Beruf-Code, z. B. AU_EFZ
     * @param string $konfiguration Zeilen im Format CODE=Anzahl, z. B. AU_EFZ=3.
     *     Zeilen ohne '=' oder mit einem Wert, der keine positive ganze Zahl
     *     ist, werden uebersprungen.
     * @return int|null Null, wenn fuer den Beruf nichts hinterlegt ist
     */
    public function anzahl(string $beruf, string $konfiguration): ?int {
        foreach (preg_split('/\r\n|\r|\n/', $konfiguration) as $zeile) {
            $zeile = trim($zeile);
            if ($zeile === '' || !str_contains($zeile, '=')) {
                continue;
            }

            [$code, $anzahl] = array_map('trim', explode('=', $zeile, 2));
            if ($code === $beruf && $anzahl !== '' && ctype_digit($anzahl) && (int) $anzahl > 0) {
                return (int) $anzahl;
            }
        }

        return null;
    }
}

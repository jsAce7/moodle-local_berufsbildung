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

use local_berufsbildung\wahlpflicht_gruppe;
use local_berufsbildung\wahlpflicht_vorgabe;

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
     * Format je Beruf: Gruppen durch Semikolon getrennt, je Gruppe die
     * Kuerzel der Bereiche mit Komma, ein Doppelpunkt und die Anzahl.
     * "AU_EFZ=a,b,c:1; d:1" verlangt eine aus a, b und c zusammen und eine
     * aus d. Ohne Bereiche gilt die Anzahl fuer den ganzen Beruf:
     * "AU_EFZ=3". Ein Kuerzel mit Punkt meint eine einzelne HK ("d.06");
     * ein Rahmen-Praefix davor ("5555 d.06") stoert nicht.
     *
     * Verlangt der Bildungsplan das eine oder das andere, trennt ein
     * senkrechter Strich die Wege: "PM_EFZ=d.06:1 | b,c,d:2; d:1" heisst
     * d6, oder zwei aus b, c und d, davon eine aus d.
     *
     * Gruppen ohne positive ganze Zahl werden uebersprungen, Wege ohne
     * gueltige Gruppe ebenso.
     *
     * @param string $beruf Beruf-Code, z. B. AU_EFZ
     * @param string $konfiguration Eine Zeile je Beruf, siehe oben
     * @return wahlpflicht_vorgabe|null Null, wenn fuer den Beruf nichts hinterlegt ist
     */
    public function vorgabe(string $beruf, string $konfiguration): ?wahlpflicht_vorgabe {
        foreach (preg_split('/\r\n|\r|\n/', $konfiguration) as $zeile) {
            $zeile = trim($zeile);
            if ($zeile === '' || !str_contains($zeile, '=')) {
                continue;
            }

            [$code, $wert] = array_map('trim', explode('=', $zeile, 2));
            if ($code !== $beruf) {
                continue;
            }

            $wege = [];
            foreach (explode('|', $wert) as $weg) {
                $gruppen = $this->gruppen($weg);
                if ($gruppen !== []) {
                    $wege[] = $gruppen;
                }
            }

            return $wege !== [] ? new wahlpflicht_vorgabe($wege) : null;
        }

        return null;
    }

    /**
     * Die Gruppen eines Wegs, z. B. aus "b,c,d:2; d:1".
     *
     * @param string $weg
     * @return wahlpflicht_gruppe[]
     */
    private function gruppen(string $weg): array {
        $gruppen = [];
        foreach (explode(';', $weg) as $teil) {
            $teil = trim($teil);
            $bereiche = [];
            if (str_contains($teil, ':')) {
                [$liste, $teil] = array_map('trim', explode(':', $teil, 2));
                $bereiche = array_values(array_filter(array_map(
                    static fn (string $kuerzel): string =>
                        \core_text::strtolower(kompetenz_baum::kuerzel_aus_idnumber($kuerzel)),
                    explode(',', $liste)
                ), static fn (string $kuerzel): bool => $kuerzel !== ''));
            }
            if ($teil === '' || !ctype_digit($teil) || (int) $teil < 1) {
                continue;
            }
            $gruppen[] = new wahlpflicht_gruppe($bereiche, (int) $teil);
        }

        return $gruppen;
    }
}

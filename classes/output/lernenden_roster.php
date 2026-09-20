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
 * Sortierung und Filterung des Lernenden-Rosters fuer meine_lernenden.php.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\output;

use core_text;
use local_berufsbildung\ausbildungsstand;

/**
 * Reine Array-Operationen auf dem Roster (id, name, Ausbildungsstand) -
 * keine DB-Zugriffe, deshalb ohne Weiteres unit-testbar.
 *
 * Eintraege haben die Form array{id: int, name: string, stand: ?ausbildungsstand,
 * istextern?: bool}.
 */
class lernenden_roster {
    /**
     * Sortiert reguläre Lernende nach laufendem Semester (aufsteigend),
     * dann nach Namen. Externe üK-Teilnehmende stehen immer zuletzt.
     *
     * Erwartet die Eintraege bereits alphabetisch nach Namen sortiert (siehe
     * core_collator::asort() in meine_lernenden.php) - PHP-usort ist seit
     * PHP 8 stabil, deshalb bleibt diese Reihenfolge innerhalb eines
     * Semesters unangetastet, ohne selbst eine locale-bewusste
     * Zeichenkettenvergleichsfunktion nachbauen zu muessen.
     *
     * Personen ohne Ausbildungsstand (Lehre noch nicht begonnen oder
     * bereits beendet - api::get_ausbildungsstand() liefert fuer beide
     * null, siehe docs/schnitt1.md) stehen zuletzt, untereinander
     * weiterhin alphabetisch.
     *
     * @param array $eintraege Struktur: array<int, array{id: int, name: string, stand: ?ausbildungsstand, istextern?: bool}>
     * @return array<int, array{id: int, name: string, stand: ?ausbildungsstand, istextern?: bool}>
     */
    public static function sortiere(array $eintraege): array {
        usort($eintraege, static function (array $a, array $b): int {
            $externa = !empty($a['istextern']);
            $externb = !empty($b['istextern']);
            if ($externa !== $externb) {
                return $externa <=> $externb;
            }

            $semestera = $a['stand']?->semester ?? PHP_INT_MAX;
            $semesterb = $b['stand']?->semester ?? PHP_INT_MAX;

            return $semestera <=> $semesterb;
        });

        return $eintraege;
    }

    /**
     * Filtert nach Namens-Teilstring (Gross-/Kleinschreibung egal) und
     * optional exakt nach Beruf-Code. Leere Filter aendern nichts.
     *
     * @param array $eintraege Struktur: array<int, array{id: int, name: string, stand: ?ausbildungsstand}>
     * @param string $suchbegriff
     * @param string $beruf Exakter Beruf-Code, z.B. 'AU_EFZ' - leer = alle Berufe
     * @return array<int, array{id: int, name: string, stand: ?ausbildungsstand}>
     */
    public static function filtere(array $eintraege, string $suchbegriff, string $beruf): array {
        $suchbegriff = trim($suchbegriff);

        if ($suchbegriff === '' && $beruf === '') {
            return $eintraege;
        }

        $suchbegriffklein = core_text::strtolower($suchbegriff);

        return array_values(array_filter(
            $eintraege,
            static function (array $eintrag) use ($suchbegriffklein, $beruf): bool {
                if ($beruf !== '' && $eintrag['stand']?->beruf !== $beruf) {
                    return false;
                }

                if ($suchbegriffklein !== '' && !str_contains(core_text::strtolower($eintrag['name']), $suchbegriffklein)) {
                    return false;
                }

                return true;
            }
        ));
    }

    /**
     * Alle im Roster vorkommenden Beruf-Codes, eindeutig und sortiert -
     * fuer die Beruf-Auswahl der Toolbar. Personen ohne Ausbildungsstand
     * liefern keinen Beruf und tauchen hier nicht auf.
     *
     * @param array $eintraege Struktur: array<int, array{id: int, name: string, stand: ?ausbildungsstand}>
     * @return string[]
     */
    public static function berufe(array $eintraege): array {
        $berufe = [];
        foreach ($eintraege as $eintrag) {
            if ($eintrag['stand'] !== null) {
                $berufe[$eintrag['stand']->beruf] = true;
            }
        }

        $berufe = array_keys($berufe);
        sort($berufe);

        return $berufe;
    }
}

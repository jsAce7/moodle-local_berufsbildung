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
 * Rendert Nachweise gruppiert nach Quelle, gemeinsam genutzt von
 * meine_lehre.php und meine_lernenden.php.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\output;

use local_berufsbildung\nachweis\nachweis;

/**
 * Liste der eingesammelten Nachweise.
 */
class nachweis_liste {
    /**
     * Baut die Daten fuer das Template zusammen.
     *
     * @param nachweis[] $nachweise Bereits nach Datum sortiert (siehe
     *                               collector::get_nachweise()) - die
     *                               Reihenfolge je Gruppe bleibt erhalten,
     *                               es wird nicht neu sortiert.
     * @param array $quellennamen Quelle-Key => Anzeigename, Struktur: array<string, string>
     *                                              siehe collector::get_quelle_namen()
     * @param array $semestergrenzen Semesternummer => [von, bis], Struktur: array<int, array{0: int, 1: int}>
     *        siehe api::get_semester_grenzen(). Leer (Standard) gruppiert nach
     *        Quelle; gefuellt gruppiert nach Semester, weil eine lernende
     *        Person ihre Ausbildung in Semestern denkt und nicht in
     *        liefernden Plugins.
     */
    public static function render(array $nachweise, array $quellennamen, array $semestergrenzen = []): string {
        global $OUTPUT;

        $gruppen = empty($semestergrenzen)
            ? self::gruppiere_nach_quelle($nachweise, $quellennamen)
            : self::gruppiere_nach_semester($nachweise, $quellennamen, $semestergrenzen);

        return $OUTPUT->render_from_template('local_berufsbildung/nachweis_liste', [
            'gruppen' => $gruppen,
            'hasgruppen' => !empty($gruppen),
            'leertext' => get_string('form:keine_taetigkeiten', 'local_berufsbildung'),
        ]);
    }

    /**
     * Gruppiert die Nachweise nach liefernder Quelle.
     *
     * @param nachweis[] $nachweise
     * @param array $quellennamen Struktur: array<string, string>
     * @return array<int, array{name: string, nachweise: array}>
     */
    private static function gruppiere_nach_quelle(array $nachweise, array $quellennamen): array {
        $nachweisenachquelle = [];
        foreach ($nachweise as $einzelnachweis) {
            $nachweisenachquelle[$einzelnachweis->quellekey][] = $einzelnachweis;
        }

        $gruppen = [];
        foreach ($nachweisenachquelle as $quellekey => $einzelnachweise) {
            $gruppen[] = [
                'name' => format_string($quellennamen[$quellekey] ?? $quellekey),
                // Die Quelle steht bereits als Ueberschrift der Gruppe.
                'nachweise' => self::zu_zeilen($einzelnachweise, $quellennamen, false),
            ];
        }

        return $gruppen;
    }

    /**
     * Neuestes Semester zuerst - die aktuelle Ausbildungsphase steht oben,
     * nicht der Lehrbeginn. Nachweise ausserhalb jedes Semesters (etwa aus
     * der Zeit vor Lehrbeginn) gehen nicht verloren, sondern sammeln sich
     * in einer eigenen Gruppe am Ende.
     *
     * @param nachweis[] $nachweise
     * @param array $quellennamen Struktur: array<string, string>
     * @param array $semestergrenzen Struktur: array<int, array{0: int, 1: int}>
     * @return array<int, array{name: string, nachweise: array}>
     */
    private static function gruppiere_nach_semester(
        array $nachweise,
        array $quellennamen,
        array $semestergrenzen
    ): array {
        $nachsemester = [];
        $ohnesemester = [];

        foreach ($nachweise as $einzelnachweis) {
            $semester = self::finde_semester($einzelnachweis->datum, $semestergrenzen);
            if ($semester === null) {
                $ohnesemester[] = $einzelnachweis;
                continue;
            }
            $nachsemester[$semester][] = $einzelnachweis;
        }

        krsort($nachsemester);

        $gruppen = [];
        foreach ($nachsemester as $semester => $einzelnachweise) {
            $gruppen[] = [
                'name' => get_string('nachweis:semester', 'local_berufsbildung', $semester),
                'nachweise' => self::zu_zeilen($einzelnachweise, $quellennamen, true),
            ];
        }

        if (!empty($ohnesemester)) {
            $gruppen[] = [
                'name' => get_string('nachweis:ohne_semester', 'local_berufsbildung'),
                'nachweise' => self::zu_zeilen($ohnesemester, $quellennamen, true),
            ];
        }

        return $gruppen;
    }

    /**
     * Findet das Semester, in das ein Datum faellt.
     *
     * @param int $datum Timestamp
     * @param array $semestergrenzen Struktur: array<int, array{0: int, 1: int}>
     * @return int|null Semesternummer, null ausserhalb der Lehrzeit
     */
    private static function finde_semester(int $datum, array $semestergrenzen): ?int {
        foreach ($semestergrenzen as $semester => [$von, $bis]) {
            if ($datum >= $von && $datum <= $bis) {
                return (int) $semester;
            }
        }

        return null;
    }

    /**
     * Formt die Nachweise in Tabellenzeilen um.
     *
     * @param nachweis[] $nachweise
     * @param array $quellennamen Struktur: array<string, string>
     * @param bool $mitquelle Quelle je Zeile ausweisen - noetig, sobald die
     *                         Gruppe nicht selbst die Quelle ist
     * @return array<int, array>
     */
    private static function zu_zeilen(array $nachweise, array $quellennamen, bool $mitquelle): array {
        return array_map(static function (nachweis $einzelnachweis) use ($quellennamen, $mitquelle): array {
            $ergebnis = $einzelnachweis->ergebnis;

            return [
                'bezeichnung' => format_string($einzelnachweis->bezeichnung),
                'datum' => userdate($einzelnachweis->datum, get_string('strftimedate', 'langconfig')),
                'hasergebnis' => $ergebnis !== null && $ergebnis !== '',
                'ergebnis' => $ergebnis !== null ? s($ergebnis) : '',
                'hasurl' => $einzelnachweis->url !== null,
                'url' => $einzelnachweis->url ?? '',
                'hasquelle' => $mitquelle,
                'quelle' => $mitquelle
                    ? format_string($quellennamen[$einzelnachweis->quellekey] ?? $einzelnachweis->quellekey)
                    : '',
            ];
        }, $nachweise);
    }
}

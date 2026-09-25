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
 * Liste der eingesammelten Nachweise, eine Gruppe je Quelle.
 *
 * Nach Quelle statt nach Semester: Lerndoku-Eintraege, ueK-Nachweise und
 * spaeter der Bildungsbericht sind verschiedene Arten von Nachweisen, und
 * jede Art hat ihre eigene Zusammenfassung - den Schnitt der ueK-Noten
 * etwa, der ueber Lerndoku-Eintraege nichts aussagt. Das Semester steht
 * stattdessen in jeder Zeile.
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
     *        siehe collector::get_quelle_namen(). Legt zugleich die
     *        Reihenfolge der Gruppen fest - die der Registrierung, damit
     *        eine Quelle nicht mit jedem neuen Nachweis den Platz wechselt.
     * @param array $semestergrenzen Semesternummer => [von, bis], Struktur: array<int, array{0: int, 1: int}>
     *        siehe api::get_semester_grenzen(). Gefuellt nennt jede Zeile ihr
     *        Semester; leer (Standard) nur das Datum.
     * @param string|null $titel Ueberschrift ueber der ganzen Liste. Null, wo
     *        die Liste bereits in einem beschrifteten Bereich steht - etwa im
     *        aufgeklappten Teil einer Roster-Kachel. Ohne Ueberschrift haengt
     *        der Leertext sonst ohne Bezug auf der Seite.
     * @param array $zusammenfassungen Quelle-Key => Text fuer den Gruppenkopf, Struktur: array<string, string>
     *        siehe collector::get_zusammenfassungen()
     */
    public static function render(
        array $nachweise,
        array $quellennamen,
        array $semestergrenzen = [],
        ?string $titel = null,
        array $zusammenfassungen = []
    ): string {
        global $OUTPUT;

        $gruppen = self::gruppiere_nach_quelle(
            self::eindeutige($nachweise),
            $quellennamen,
            $semestergrenzen,
            $zusammenfassungen
        );

        return $OUTPUT->render_from_template('local_berufsbildung/nachweis_liste', [
            'gruppen' => $gruppen,
            'hasgruppen' => !empty($gruppen),
            'hastitel' => $titel !== null,
            'titel' => $titel ?? '',
            'leertext' => get_string('form:keine_taetigkeiten', 'local_berufsbildung'),
        ]);
    }

    /**
     * Die Nachweise ohne Dubletten, fuer die Liste und fuer jede Anzahl, die
     * zu ihr passen muss (etwa "X Taetigkeiten" in meine_lernenden.php).
     *
     * Eine Quelle darf denselben Nachweis mehrfach liefern, einmal je
     * Kompetenz: die Lerndokumentation gibt einen Eintrag mit drei
     * Handlungskompetenzen als drei Nachweise ab, die sich nur in
     * competencyid unterscheiden. Fuer die Kompetenzzuordnung ist das
     * richtig, in einer Liste fuer Menschen stuende derselbe Eintrag dreimal.
     * Gleich ist, was in Quelle, Bezeichnung, Datum, Ergebnis und Link
     * uebereinstimmt - also in allem, was die Liste zeigt. Der erste
     * gewinnt, die Reihenfolge bleibt erhalten.
     *
     * @param nachweis[] $nachweise
     * @return nachweis[]
     */
    public static function eindeutige(array $nachweise): array {
        $gesehen = [];
        $ergebnis = [];

        foreach ($nachweise as $einzelnachweis) {
            $schluessel = json_encode([
                $einzelnachweis->quellekey,
                $einzelnachweis->bezeichnung,
                $einzelnachweis->datum,
                $einzelnachweis->ergebnis,
                $einzelnachweis->url,
            ]);
            if (isset($gesehen[$schluessel])) {
                continue;
            }
            $gesehen[$schluessel] = true;
            $ergebnis[] = $einzelnachweis;
        }

        return $ergebnis;
    }

    /**
     * Gruppiert die Nachweise nach liefernder Quelle, in der Reihenfolge der
     * registrierten Quellen. Eine Quelle ohne Anzeigenamen - etwa ein
     * inzwischen deaktivierter Provider - steht danach unter ihrem Key.
     *
     * @param nachweis[] $nachweise
     * @param array $quellennamen Struktur: array<string, string>
     * @param array $semestergrenzen Struktur: array<int, array{0: int, 1: int}>
     * @param array $zusammenfassungen Struktur: array<string, string>
     * @return array<int, array>
     */
    private static function gruppiere_nach_quelle(
        array $nachweise,
        array $quellennamen,
        array $semestergrenzen,
        array $zusammenfassungen
    ): array {
        $jequelle = array_fill_keys(array_keys($quellennamen), []);
        foreach ($nachweise as $einzelnachweis) {
            $jequelle[$einzelnachweis->quellekey][] = $einzelnachweis;
        }

        $gruppen = [];
        foreach ($jequelle as $quellekey => $einzelnachweise) {
            if (empty($einzelnachweise)) {
                continue;
            }

            $anzahl = count($einzelnachweise);
            $zusammenfassung = $zusammenfassungen[$quellekey] ?? '';

            $gruppen[] = [
                'name' => format_string($quellennamen[$quellekey] ?? (string) $quellekey),
                'anzahl' => $anzahl === 1
                    ? get_string('nachweis:anzahl_eins', 'local_berufsbildung')
                    : get_string('nachweis:anzahl', 'local_berufsbildung', $anzahl),
                'haszusammenfassung' => $zusammenfassung !== '',
                'zusammenfassung' => $zusammenfassung,
                'nachweise' => self::zu_zeilen($einzelnachweise, $semestergrenzen),
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
     * Formt die Nachweise in Listenzeilen um.
     *
     * @param nachweis[] $nachweise
     * @param array $semestergrenzen Struktur: array<int, array{0: int, 1: int}>
     * @return array<int, array>
     */
    private static function zu_zeilen(array $nachweise, array $semestergrenzen): array {
        return array_map(static function (nachweis $einzelnachweis) use ($semestergrenzen): array {
            $ergebnis = $einzelnachweis->ergebnis;

            $semester = '';
            if (!empty($semestergrenzen)) {
                $nummer = self::finde_semester($einzelnachweis->datum, $semestergrenzen);
                // Ein Nachweis ausserhalb jedes Semesters - etwa aus der Zeit
                // vor Lehrbeginn - bleibt in der Liste und sagt das.
                $semester = $nummer !== null
                    ? get_string('nachweis:semester', 'local_berufsbildung', $nummer)
                    : get_string('nachweis:ohne_semester', 'local_berufsbildung');
            }

            return [
                'bezeichnung' => format_string($einzelnachweis->bezeichnung),
                'datum' => userdate($einzelnachweis->datum, get_string('strftimedate', 'langconfig')),
                'hasergebnis' => $ergebnis !== null && $ergebnis !== '',
                // Beschriftet ("Ergebnis 5.5") und roh - die Maskierung macht
                // das Template, s() hier maskierte doppelt.
                'ergebnis' => $ergebnis !== null
                    ? get_string('nachweis:ergebnis', 'local_berufsbildung', $ergebnis)
                    : '',
                'hasurl' => $einzelnachweis->url !== null,
                'url' => $einzelnachweis->url ?? '',
                'hassemester' => $semester !== '',
                'semester' => $semester,
            ];
        }, $nachweise);
    }
}

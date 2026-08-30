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
 * Zeilenweise Pruefung und Verarbeitung des Zuordnungs-CSV-Imports.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\import;

use core_user;
use local_berufsbildung\api;

/**
 * Kennt weder csv_import_reader noch die Seite - nimmt eine bereits in
 * Spalten zerlegte Zeile entgegen. So ist die eigentliche Pruef- und
 * Verarbeitungslogik ohne eine echte Datei testbar.
 *
 * Eine fehlerhafte Zeile wirft nie - der Fehler kommt im Rueckgabewert
 * zurueck, damit sie den restlichen Import nicht abbricht (siehe
 * docs/plan.md Abschnitt 8: "Zeilen mit Fehlern werden einzeln gemeldet
 * statt den ganzen Import abzubrechen").
 */
class zuordnung_csv_importer {

    /** @var string[] Erwartete Spaltennamen, kleingeschrieben. */
    public const ERWARTETE_SPALTEN = ['berufsbildner', 'lernende', 'beruf', 'gueltig_von'];

    /**
     * Ordnet eine rohe, indizierte CSV-Zeile den erwarteten Spalten zu.
     *
     * @param array $rohzeile Indiziertes Array, wie von csv_import_reader::next() geliefert
     * @param array<string,int> $spaltenindex Kleingeschriebener Spaltenname => Index
     * @return array{berufsbildner: string, lernende: string, beruf: string, gueltig_von: string}
     */
    public function zeile_zuordnen(array $rohzeile, array $spaltenindex): array {
        $wert = static function (string $spalte) use ($rohzeile, $spaltenindex): string {
            $index = $spaltenindex[$spalte] ?? null;

            return $index !== null ? trim((string) ($rohzeile[$index] ?? '')) : '';
        };

        return [
            'berufsbildner' => $wert('berufsbildner'),
            'lernende' => $wert('lernende'),
            'beruf' => $wert('beruf'),
            'gueltig_von' => $wert('gueltig_von'),
        ];
    }

    /**
     * Prueft eine zugeordnete Zeile und legt bei $anlegen = true die
     * Zuordnung tatsaechlich an.
     *
     * @param array{berufsbildner: string, lernende: string, beruf: string, gueltig_von: string} $zeile
     * @param bool $anlegen true = tatsaechlich anlegen, false = nur pruefen (Vorschau)
     * @return array{berufsbildner: string, lernende: string, beruf: string, gueltig_von: string, fehler: ?string}
     */
    public function verarbeite_zeile(array $zeile, bool $anlegen): array {
        $ergebnis = $zeile + ['fehler' => null];

        if ($zeile['berufsbildner'] === '') {
            $ergebnis['fehler'] = get_string('import:fehler_berufsbildner_fehlt', 'local_berufsbildung');
            return $ergebnis;
        }

        if ($zeile['lernende'] === '') {
            $ergebnis['fehler'] = get_string('import:fehler_lernende_fehlt', 'local_berufsbildung');
            return $ergebnis;
        }

        $berufsbildner = core_user::get_user_by_username($zeile['berufsbildner']);
        if (!$berufsbildner) {
            $ergebnis['fehler'] = get_string('import:fehler_person_nicht_gefunden', 'local_berufsbildung', $zeile['berufsbildner']);
            return $ergebnis;
        }

        $lernende = core_user::get_user_by_username($zeile['lernende']);
        if (!$lernende) {
            $ergebnis['fehler'] = get_string('import:fehler_person_nicht_gefunden', 'local_berufsbildung', $zeile['lernende']);
            return $ergebnis;
        }

        if ((int) $berufsbildner->id === (int) $lernende->id) {
            $ergebnis['fehler'] = get_string('zuordnung:fehler_gleiche_person', 'local_berufsbildung');
            return $ergebnis;
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $zeile['gueltig_von'])) {
            $ergebnis['fehler'] = get_string('import:fehler_datum', 'local_berufsbildung', $zeile['gueltig_von']);
            return $ergebnis;
        }
        $gueltigvon = strtotime($zeile['gueltig_von'] . ' 00:00:00');

        $beruf = clean_param($zeile['beruf'], PARAM_TEXT);

        if ($anlegen) {
            api::set_zuordnung((int) $berufsbildner->id, (int) $lernende->id, $beruf, $gueltigvon);
        }

        return $ergebnis;
    }
}

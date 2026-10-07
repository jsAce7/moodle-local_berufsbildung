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
 * Liste der real vorkommenden Beruf-Codes, fuer Auswahllisten in der
 * Verwaltung.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\service;

/**
 * Der Beruf einer lernenden Person kommt aus einem benutzerdefinierten
 * Profilfeld (Einstellung 'profilefield_beruf', siehe
 * api::get_ausbildungsstand()). Damit ist dieses Feld auch die einzige
 * sinnvolle Quelle fuer eine Auswahlliste: ein hier frei eingetippter Code
 * wuerde nur dann greifen, wenn er zeichengenau dem Profilwert entspricht.
 *
 * Die Klasse nimmt den Feld-Kurznamen als Parameter entgegen statt ihn
 * selbst aus der Konfiguration zu lesen - gleiches Muster wie
 * rahmen_resolver und lehrdauer_resolver.
 */
class beruf_katalog {
    /**
     * Alle Beruf-Codes, die im Profilfeld zur Auswahl stehen oder dort
     * tatsaechlich hinterlegt sind.
     *
     * Bei einem Auswahlfeld ('menu') sind das die konfigurierten Optionen -
     * so laesst sich ein Rahmen auch fuer einen Beruf zuordnen, den noch
     * keine lernende Person hat. Zusaetzlich immer die gespeicherten Werte,
     * damit Textfelder ueberhaupt eine Liste liefern und ein Beruf, dessen
     * Menu-Option inzwischen entfernt wurde, nicht aus der Verwaltung
     * verschwindet, solange noch jemand darauf steht.
     *
     * @param string $profilfeld Kurzname des Profilfelds, z. B. 'beruf'
     * @return string[] Eindeutig, natuerlich sortiert; leer, wenn das Feld
     *                  nicht existiert oder nirgends gefuellt ist
     */
    public function alle_codes(string $profilfeld): array {
        global $DB;

        $profilfeld = trim($profilfeld);
        if ($profilfeld === '') {
            return [];
        }

        $feld = $DB->get_record('user_info_field', ['shortname' => $profilfeld], 'id, datatype, param1');
        if (!$feld) {
            return [];
        }

        $codes = [];
        if ($feld->datatype === 'menu') {
            $codes = preg_split('/\r\n|\r|\n/', (string) $feld->param1) ?: [];
        }

        // Mit sql_compare_text(), weil user_info_data.data ein Textfeld ist und
        // DISTINCT darauf nicht auf jeder Datenbank erlaubt ist.
        $gespeicherte = $DB->get_fieldset_sql(
            'SELECT DISTINCT ' . $DB->sql_compare_text('data', 255) . '
               FROM {user_info_data}
              WHERE fieldid = :fieldid',
            ['fieldid' => $feld->id]
        );
        $codes = array_merge($codes, $gespeicherte ?: []);

        $codes = array_filter(array_map('trim', $codes), static function (string $code): bool {
            return $code !== '';
        });
        $codes = array_values(array_unique($codes));
        sort($codes, SORT_NATURAL | SORT_FLAG_CASE);

        return $codes;
    }
}

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
 * Webservice-Funktion fuer den Versetzungsplan-Import - der Regelweg aus
 * docs/plan.md §5.4. Der manuelle Upload unter import_plan.php nutzt
 * denselben import_service und kennt den Weg nicht.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\external;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_berufsbildung\versetzungsplan\import_service;

/**
 * Webservice zum Import des Versetzungsplans.
 */
class import_versetzungsplan extends external_api {
    /**
     * Beschreibt die erwarteten Parameter.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'csvdaten' => new external_value(PARAM_RAW, 'CSV-Inhalt, Base64-kodiert'),
            'quelle' => new external_value(PARAM_TEXT, 'Freitext zur Herkunft, z.B. Name des liefernden Skripts', VALUE_DEFAULT, 'webservice'),
            'testlauf' => new external_value(PARAM_BOOL, 'true = nur pruefen, nichts schreiben', VALUE_DEFAULT, false),
            'rueckgang_bestaetigt' => new external_value(
                PARAM_BOOL,
                'true = ein deutlicher Rueckgang der verarbeiteten Personen ist beabsichtigt',
                VALUE_DEFAULT,
                false
            ),
        ]);
    }

    /**
     * Nimmt eine Versetzungsplan-Lieferung entgegen.
     *
     * @param string $csvdaten
     * @param string $quelle
     * @param bool $testlauf
     * @param bool $rueckgangbestaetigt
     * @return array
     */
    public static function execute(
        string $csvdaten,
        string $quelle = 'webservice',
        bool $testlauf = false,
        bool $rueckgangbestaetigt = false
    ): array {
        [
            'csvdaten' => $csvdaten,
            'quelle' => $quelle,
            'testlauf' => $testlauf,
            'rueckgang_bestaetigt' => $rueckgangbestaetigt,
        ] = self::validate_parameters(self::execute_parameters(), [
            'csvdaten' => $csvdaten,
            'quelle' => $quelle,
            'testlauf' => $testlauf,
            'rueckgang_bestaetigt' => $rueckgangbestaetigt,
        ]);

        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/berufsbildung:importplan', $context);

        $inhalt = base64_decode($csvdaten, true);
        if ($inhalt === false) {
            throw new \invalid_parameter_exception('csvdaten ist nicht gueltig Base64-kodiert.');
        }

        global $USER;

        return (new import_service())->verarbeiten($inhalt, $quelle, (int) $USER->id, $testlauf, $rueckgangbestaetigt);
    }

    /**
     * Beschreibt den Rueckgabewert.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_ALPHAEXT, "'ok' | 'mit_warnungen' | 'abgewiesen' | 'fehlgeschlagen'"),
            'unveraendert' => new external_value(PARAM_BOOL, 'true = identisch zum letzten erfolgreichen Import, nichts geschrieben'),
            'zeilen_gelesen' => new external_value(PARAM_INT, 'Anzahl gelesener Datenzeilen'),
            'personen_verarbeitet' => new external_value(PARAM_INT, 'Anzahl Personen mit aktiver Zuordnung'),
            'zeilen_ausserhalb_geltungsbereich' => new external_value(PARAM_INT, 'Anzahl Zeilen ohne aktive Zuordnung'),
            'einsaetze_erzeugt' => new external_value(PARAM_INT, 'Anzahl erzeugter (bzw. bei Testlauf vorhergesagter) Einsaetze'),
            'protokoll' => new external_multiple_structure(
                new external_value(PARAM_TEXT, 'Protokollzeile'),
                'Was nicht zugeordnet werden konnte',
                VALUE_DEFAULT,
                []
            ),
        ]);
    }
}

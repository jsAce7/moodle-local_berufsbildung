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
 * Persistent-Klasse fuer das Protokoll eines Versetzungsplan-Imports.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\persistent;

use core\persistent;

/**
 * Ein Datensatz je tatsaechlich verarbeitetem Importlauf. Ein Testlauf
 * (nur pruefen, nichts schreiben) erzeugt bewusst keinen Datensatz.
 */
class plan_import extends persistent {

    /** Tabellenname. */
    const TABLE = 'local_berufsbildung_plan_import';

    /**
     * @return array
     */
    protected static function define_properties(): array {
        return [
            'quelle' => [
                'type' => PARAM_TEXT,
            ],
            'daten_hash' => [
                'type' => PARAM_ALPHANUM,
            ],
            'zeitpunkt' => [
                'type' => PARAM_INT,
            ],
            'ausgefuehrt_von' => [
                'type' => PARAM_INT,
            ],
            'zeilen_gelesen' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'personen_verarbeitet' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'zeilen_ausserhalb_geltungsbereich' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'einsaetze_erzeugt' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'status' => [
                'type' => PARAM_ALPHAEXT,
                'choices' => ['ok', 'mit_warnungen', 'abgewiesen', 'fehlgeschlagen'],
                'default' => 'ok',
            ],
            'protokoll' => [
                'type' => PARAM_RAW,
                'default' => '',
                'null' => NULL_ALLOWED,
            ],
        ];
    }
}

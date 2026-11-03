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
 * Persistent-Klasse fuer das Aenderungsprotokoll der Einsaetze.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\persistent;

use core\persistent;

/**
 * Ein Einsatz, den ein Importlauf neu angelegt oder ersetzt hat - so, wie
 * import_service::vergleiche_einsaetze() ihn sieht. Gespeichert werden
 * Blocknummer und Kalenderwochen als Text, nicht die IDs: der Einsatz selbst
 * ist nach dem Ersetzen geloescht, und ein Block kann spaeter wegfallen.
 *
 * Kein Ausbildungsnachweis wie der Einsatz selbst: das Protokoll wird
 * hoechstens ein Jahr alt (task\bereinige_planverlauf) und verschwindet
 * frueher mit den Zuordnungen der Person
 * (zuordnung_retention_service::loesche_fuer_lernende()).
 */
class einsatz_aenderung extends persistent {
    /** Tabellenname. */
    const TABLE = 'local_berufsbildung_einsatz_aenderung';

    /** Der Einsatz kam mit dieser Lieferung dazu. */
    public const NEU = 'neu';

    /** Der Einsatz wurde durch diese Lieferung ersetzt. */
    public const ENTFAELLT = 'entfaellt';

    /**
     * Feldliste dieses Persistent inklusive Validierung.
     *
     * @return array
     */
    protected static function define_properties(): array {
        return [
            'importid' => [
                'type' => PARAM_INT,
            ],
            'userid' => [
                'type' => PARAM_INT,
            ],
            'art' => [
                'type' => PARAM_ALPHA,
                'choices' => [self::NEU, self::ENTFAELLT],
            ],
            'block' => [
                'type' => PARAM_TEXT,
            ],
            'kw_von' => [
                'type' => PARAM_ALPHANUMEXT,
            ],
            'kw_bis' => [
                'type' => PARAM_ALPHANUMEXT,
            ],
            'von' => [
                'type' => PARAM_INT,
            ],
        ];
    }
}

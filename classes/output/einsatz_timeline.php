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
 * Alle Einsaetze aus dem Versetzungsplan als Zeitstrahl.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\output;

use local_berufsbildung\api;
use local_berufsbildung\persistent\einsatz;

/**
 * Beantwortet "wo war ich, wo bin ich, wo komme ich hin" auf einen Blick.
 *
 * Der Versetzungsplan ist eine Verbesserung, keine Voraussetzung (siehe
 * docs/plan.md): ohne importierte Einsaetze rendert diese Klasse bewusst
 * gar nichts, statt eine leere Ueberschrift stehen zu lassen.
 */
class einsatz_timeline {
    /**
     * Der Zeitstrahl einer lernenden Person, mit den Bezeichnungen ihrer
     * Ausbildungsbloecke - die uebliche Variante fuer Seiten, die den Plan
     * einer einzelnen Person zeigen.
     *
     * @param int $lernendeid
     * @param int|null $jetzt Timestamp, null bedeutet "jetzt"
     * @return string Leerer String, wenn kein Versetzungsplan vorliegt
     */
    public static function render_fuer_lernende(int $lernendeid, ?int $jetzt = null): string {
        $einsaetze = api::get_einsaetze($lernendeid);

        $blocknamen = [];
        foreach ($einsaetze as $einzeleinsatz) {
            $blockid = (int) $einzeleinsatz->get('blockid');
            if (array_key_exists($blockid, $blocknamen)) {
                continue;
            }
            $name = api::get_block_name($blockid);
            if ($name !== null) {
                $blocknamen[$blockid] = $name;
            }
        }

        return self::render($einsaetze, $blocknamen, $jetzt);
    }

    /**
     * Baut die Daten fuer das Template zusammen.
     *
     * @param einsatz[] $einsaetze Nach 'von' aufsteigend, siehe api::get_einsaetze()
     * @param array $blocknamen blockid => Bezeichnung, siehe api::get_block_name() Struktur: array<int, string>
     * @param int|null $jetzt Timestamp fuer die Einordnung vergangen/aktuell/kommend,
     *                        null bedeutet "jetzt"
     * @return string Leerer String, wenn keine Einsaetze vorliegen
     */
    public static function render(array $einsaetze, array $blocknamen, ?int $jetzt = null): string {
        global $OUTPUT;

        if (empty($einsaetze)) {
            return '';
        }

        $jetzt ??= time();

        $eintraege = [];
        foreach ($einsaetze as $einzeleinsatz) {
            $blockid = (int) $einzeleinsatz->get('blockid');
            $von = (int) $einzeleinsatz->get('von');
            $bis = (int) $einzeleinsatz->get('bis');

            // Ein Block, dessen Nummer beim Import unbekannt war, hat hier
            // keinen Namen - der Einsatz selbst bleibt trotzdem sichtbar.
            $name = $blocknamen[$blockid] ?? get_string('einsatz:unbekannter_block', 'local_berufsbildung');

            $eintraege[] = einsatz_darstellung::zu_kontext($einzeleinsatz, $name) + [
                'istvergangen' => $bis < $jetzt,
                'istaktuell' => $von <= $jetzt && $bis >= $jetzt,
                'istkommend' => $von > $jetzt,
            ];
        }

        return $OUTPUT->render_from_template('local_berufsbildung/einsatz_timeline', [
            'eintraege' => $eintraege,
            'titel' => get_string('einsatz:timeline_titel', 'local_berufsbildung'),
        ]);
    }
}

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
 * Darstellung der Leistungskriterien eines Berufs mit ihren Bloecken.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\output;

use core_competency\competency;
use local_berufsbildung\service\kompetenz_baum;
use moodle_url;

/**
 * Je Handlungskompetenzbereich eine Tabelle: die Leistungskriterien unter
 * ihrer Handlungskompetenz und daneben die Bloecke, die sie vermitteln.
 * Ein LK ohne zaehlenden Block ist als "nicht zugeordnet" markiert; mit
 * $nuroffen bleiben nur diese stehen.
 */
class lk_abdeckung {
    /**
     * Baut die Seite aus dem Ergebnis von lk_abdeckung_service::fuer_beruf().
     *
     * @param array $abdeckung Ergebnis von lk_abdeckung_service::fuer_beruf()
     * @param bool $nuroffen Nur Leistungskriterien ohne zaehlenden Block zeigen
     * @param moodle_url $seitenurl Adresse der Seite, fuer den Filter
     */
    public static function render(array $abdeckung, bool $nuroffen, moodle_url $seitenurl): string {
        global $OUTPUT;

        $bereiche = [];
        foreach ($abdeckung['bereiche'] as $bereich) {
            $handlungskompetenzen = [];
            foreach ($bereich['handlungskompetenzen'] as $hk) {
                $zeilen = [];
                foreach ($hk['leistungskriterien'] as $lk) {
                    if ($nuroffen && $lk['zugeordnet']) {
                        continue;
                    }
                    $zeilen[] = self::zeile($lk);
                }

                if (!empty($zeilen)) {
                    $handlungskompetenzen[] = [
                        'name' => self::name($hk['kompetenz']),
                        'leistungskriterien' => $zeilen,
                    ];
                }
            }

            if (empty($handlungskompetenzen)) {
                continue;
            }

            $bereiche[] = [
                // Wie im Kompetenzraster: der Bereich ohne Kuerzel, sein
                // Kurzname beginnt schon mit dem Buchstaben.
                'name' => format_string((string) $bereich['bereich']->get('shortname')),
                'stand' => get_string('lkabdeckung:bereich_stand', 'local_berufsbildung', (object) [
                    'zugeordnet' => $bereich['anzahl'] - $bereich['offen'],
                    'anzahl' => $bereich['anzahl'],
                ]),
                'hatoffene' => $bereich['offen'] > 0,
                'handlungskompetenzen' => $handlungskompetenzen,
            ];
        }

        $alleurl = new moodle_url($seitenurl);
        $alleurl->remove_params('nuroffen');
        $offenurl = new moodle_url($seitenurl, ['nuroffen' => 1]);

        return $OUTPUT->render_from_template('local_berufsbildung/lk_abdeckung', [
            'zusammenfassung' => get_string('lkabdeckung:zusammenfassung', 'local_berufsbildung', (object) [
                'anzahl' => $abdeckung['anzahl'],
                'zugeordnet' => $abdeckung['anzahl'] - $abdeckung['offen'],
                'offen' => $abdeckung['offen'],
            ]),
            'hatoffene' => $abdeckung['offen'] > 0,
            'nuroffen' => $nuroffen,
            'alleurl' => $alleurl->out(false),
            'offenurl' => $offenurl->out(false),
            'bereiche' => $bereiche,
            'hatbereiche' => !empty($bereiche),
        ]);
    }

    /**
     * Ein Leistungskriterium mit seinen Bloecken.
     *
     * @param array $lk Eintrag aus lk_abdeckung_service::fuer_beruf()
     * @return array
     */
    private static function zeile(array $lk): array {
        $bloecke = [];
        foreach ($lk['bloecke'] as $eintrag) {
            $block = $eintrag['block'];

            $hinweise = [];
            if ($eintrag['ueberhk']) {
                $hinweise[] = get_string('lkabdeckung:ueber_hk', 'local_berufsbildung');
            }
            if (!$block->get('aktiv')) {
                $hinweise[] = get_string('lkabdeckung:inaktiv', 'local_berufsbildung');
            } else if (!$block->get('ist_betrieb')) {
                $hinweise[] = get_string('lkabdeckung:kein_betrieb', 'local_berufsbildung');
            }

            $bloecke[] = [
                'nummer' => format_string((string) $block->get('nummer')),
                'name' => format_string((string) $block->get('name')),
                'url' => (new moodle_url('/local/berufsbildung/block_kompetenzen.php', [
                    'id' => (int) $block->get('id'),
                ]))->out(false),
                'zaehlt' => $eintrag['zaehlt'],
                'hinweis' => implode(', ', $hinweise),
            ];
        }

        return [
            // LK tragen ihren Code schon im Kurznamen ("AU a1 01"), wie im
            // Kompetenzraster.
            'name' => format_string((string) $lk['kompetenz']->get('shortname')),
            'zugeordnet' => $lk['zugeordnet'],
            'bloecke' => $bloecke,
        ];
    }

    /**
     * Kuerzel und Bezeichnung einer Handlungskompetenz,
     * z. B. "a.01 Fertigungsunterlagen erstellen".
     *
     * @param competency $kompetenz
     */
    private static function name(competency $kompetenz): string {
        return format_string(trim(kompetenz_baum::kuerzel($kompetenz) . ' ' . $kompetenz->get('shortname')));
    }
}

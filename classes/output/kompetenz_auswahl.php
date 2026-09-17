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
 * Auswahl der Kompetenzen eines Ausbildungsblocks entlang der Gliederung
 * des Bildungsplans.
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
 * Die Auswahl folgt dem Rahmen statt einer flachen Liste: Bereich,
 * darunter die Handlungskompetenzen, darunter deren Leistungskriterien.
 *
 * Der Grund ist nicht Kosmetik. Dieselbe LK-Bezeichnung ("AU b1 01 1-2")
 * haengt unter einem Dutzend Handlungskompetenzen; in einer flachen Liste
 * steht sie entsprechend oft da und ist nur am vorangestellten HK-Satz zu
 * unterscheiden. Unter ihrer Handlungskompetenz ist sie eindeutig, und der
 * Rahmen laesst sich der Reihe nach durcharbeiten.
 *
 * Auswaehlbar ist beides: die Handlungskompetenz selbst, wenn ein Block
 * sie als Ganzes abdeckt, oder einzelne Leistungskriterien darunter. Fuer
 * Lueckenanalyse und Raster ist das gleichwertig, weil LK ohnehin auf ihre
 * HK hochgerechnet werden (plan_service::get_ausgebildete_kompetenzen()).
 *
 * Aufklappbar ueber das native <details>-Element, ohne JavaScript - wie
 * die Roster-Karten in meine_lernenden.php.
 */
class kompetenz_auswahl {
    /**
     * Baut die Daten fuer das Template zusammen.
     *
     * @param array $baum Ergebnis von service\kompetenz_baum::baum()
     * @param array $zugeordnet competencyid => beliebiger Wert, geprueft wird nur der Schluessel
     * @param moodle_url $actionurl Ziel des Formulars
     * @param int $blockid
     */
    public static function render(array $baum, array $zugeordnet, moodle_url $actionurl, int $blockid): string {
        global $OUTPUT;

        $bereiche = [];
        foreach ($baum as $zweig) {
            $handlungskompetenzen = [];

            foreach ($zweig['handlungskompetenzen'] as $eintrag) {
                $leistungskriterien = [];
                foreach ($eintrag['leistungskriterien'] as $lk) {
                    // Ohne Kuerzel: die ID-Nummer eines LK ist eine
                    // generierte Eindeutigkeitsnummer ("7777BE_AU b1 01
                    // 1-2#4") und sagt nichts, was nicht schon im
                    // shortname ("AU b1 01 1-2") steht.
                    $leistungskriterien[] = self::eintrag($lk, $zugeordnet, false);
                }

                // Nach Bezeichnung sortiert statt in Rahmenreihenfolge: die
                // sortorder der LK folgt der Reihenfolge, in der sie beim
                // Rahmenimport angelegt wurden, und wirft "MEM 02 04" vor
                // "AU a1 01". Natuerliche Sortierung haelt die Nummern
                // einer Serie beieinander.
                usort(
                    $leistungskriterien,
                    static fn (array $links, array $rechts): int => strnatcasecmp($links['name'], $rechts['name'])
                );

                $handlungskompetenzen[] = self::eintrag($eintrag['kompetenz'], $zugeordnet, true) + [
                    'leistungskriterien' => $leistungskriterien,
                    'hatlk' => !empty($leistungskriterien),
                    'lktext' => get_string(
                        'blocklk:lk_aufklappen',
                        'local_berufsbildung',
                        count($leistungskriterien)
                    ),
                ];
            }

            // Aufgeklappt starten, wo noch etwas zu tun ist. Der Bereich
            // zeigt dann seine Handlungskompetenzen als kurze Liste; die
            // Leistungskriterien darunter bleiben zugeklappt, sonst waeren
            // es ueber alle Bereiche mehrere hundert Zeilen und die
            // HK-Namen gingen darin unter.
            $alle = [];
            foreach ($zweig['handlungskompetenzen'] as $eintrag) {
                $alle[] = $eintrag['kompetenz'];
                $alle = array_merge($alle, $eintrag['leistungskriterien']);
            }

            $bereiche[] = [
                'code' => format_string(kompetenz_baum::kuerzel($zweig['bereich'])),
                'name' => format_string($zweig['bereich']->get('shortname')),
                'handlungskompetenzen' => $handlungskompetenzen,
                'offen' => self::hat_offene($alle, $zugeordnet),
            ];
        }

        return $OUTPUT->render_from_template('local_berufsbildung/kompetenz_auswahl', [
            'action' => $actionurl->out(false),
            'sesskey' => sesskey(),
            'blockid' => $blockid,
            'bereiche' => $bereiche,
            'intensitaeten' => [
                [
                    'wert' => 'schwerpunkt',
                    'text' => get_string('blocklk:intensitaet_schwerpunkt', 'local_berufsbildung'),
                    'gewaehlt' => true,
                ],
                [
                    'wert' => 'teilweise',
                    'text' => get_string('blocklk:intensitaet_teilweise', 'local_berufsbildung'),
                    'gewaehlt' => false,
                ],
            ],
            'intensitaetlabel' => get_string('blocklk:intensitaet', 'local_berufsbildung'),
            'intensitaethilfe' => get_string('blocklk:intensitaet_hinweis', 'local_berufsbildung'),
            'absenden' => get_string('blocklk:hinzufuegen', 'local_berufsbildung'),
            'zugeordnettext' => get_string('blocklk:bereits_zugeordnet', 'local_berufsbildung'),
        ]);
    }

    /**
     * Ein auswaehlbarer Eintrag - Handlungskompetenz oder
     * Leistungskriterium, die Struktur ist dieselbe.
     *
     * @param competency $kompetenz
     * @param array $zugeordnet competencyid => beliebiger Wert, geprueft wird nur der Schluessel
     * @param bool $mitcode Kuerzel aus der ID-Nummer voranstellen
     * @return array Template-Kontext
     */
    private static function eintrag(competency $kompetenz, array $zugeordnet, bool $mitcode): array {
        $id = (int) $kompetenz->get('id');
        $code = $mitcode ? format_string(kompetenz_baum::kuerzel($kompetenz)) : '';

        // Die eigentliche Beschreibung der Kompetenz steht im Rahmen im
        // description-Feld, nicht im shortname - bei einem LK ist der
        // shortname nur ein Code ("AU a1 01 1-2"). Als Titel, damit die
        // Liste kurz bleibt und der Text trotzdem erreichbar ist.
        $beschreibung = shorten_text(
            content_to_text((string) $kompetenz->get('description'), (int) $kompetenz->get('descriptionformat')),
            300
        );

        return [
            'id' => $id,
            'code' => $code,
            'hatcode' => $code !== '',
            'name' => format_string($kompetenz->get('shortname')),
            'beschreibung' => $beschreibung,
            'istzugeordnet' => isset($zugeordnet[$id]),
        ];
    }

    /**
     * Gibt es unter den uebergebenen Kompetenzen noch nicht zugeordnete?
     *
     * @param competency[] $kompetenzen
     * @param array $zugeordnet competencyid => beliebiger Wert, geprueft wird nur der Schluessel
     */
    private static function hat_offene(array $kompetenzen, array $zugeordnet): bool {
        foreach ($kompetenzen as $kompetenz) {
            if (!isset($zugeordnet[(int) $kompetenz->get('id')])) {
                return true;
            }
        }

        return false;
    }
}

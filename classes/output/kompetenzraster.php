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
 * Rendert das Kompetenzraster in der Gliederung des offiziellen
 * Bildungsplans.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\output;

use core_competency\competency;
use local_berufsbildung\raster_bereich;
use local_berufsbildung\raster_kompetenz;
use local_berufsbildung\service\kompetenz_baum;

/**
 * Handlungskompetenzbereiche als Zeilen, Handlungskompetenzen als Spalten -
 * dieselbe Anordnung wie im gedruckten Bildungsplan, damit die Darstellung
 * in Moodle und auf Papier dieselbe Gestalt hat.
 *
 * Die Farbe sagt dasselbe wie im Bildungsplan: Pflicht oder Wahlpflicht.
 * Der Ausbildungsstand ist bewusst *nicht* an der Farbe abzulesen, sondern
 * an Symbol, Klartext und Flaechendeckung - sonst haetten dieselben Farben
 * hier eine andere Bedeutung als im Dokument daneben. Zugleich erfuellt das
 * die Anforderung, dass Farbe nie der einzige Traeger einer Information ist.
 *
 * Kein Beurteilungsstatus: gezeigt wird ausschliesslich, was im
 * importierten Versetzungsplan vorkommt (Architekturregel 6). Ob eine
 * Kompetenz erreicht wurde, entscheiden die aufsetzenden Plugins.
 */
class kompetenzraster {
    /**
     * Baut die Daten fuer das Template zusammen.
     *
     * @param raster_bereich[] $raster Ergebnis von api::get_kompetenzraster()
     * @param int|null $horizont Ende des vorliegenden Plans, siehe api::get_planungshorizont()
     * @param bool $kompakt Nur Kuerzel und Symbol statt der vollen Bezeichnung,
     *                      fuer die Roster-Karten in meine_lernenden.php
     */
    public static function render(array $raster, ?int $horizont = null, bool $kompakt = false): string {
        global $OUTPUT;

        if (empty($raster)) {
            return '';
        }

        $kompetenzen = self::lade_kompetenzen($raster);

        // Alle Zeilen auf dieselbe Spaltenzahl bringen. Im Bildungsplan hat
        // nicht jeder Bereich gleich viele Handlungskompetenzen; die
        // kuerzeren Zeilen enden dort mit leeren Feldern, und genau so
        // bleiben die Spalten untereinander buendig.
        $spalten = 0;
        foreach ($raster as $bereich) {
            $spalten = max($spalten, count($bereich->kompetenzen));
        }

        $bereiche = [];
        foreach ($raster as $bereich) {
            $zellen = [];
            foreach ($bereich->kompetenzen as $kompetenz) {
                $zellen[] = self::zelle($kompetenz, $kompetenzen[$kompetenz->competencyid] ?? null);
            }
            for ($leer = count($zellen); $leer < $spalten; $leer++) {
                $zellen[] = ['istleer' => true];
            }

            $bereiche[] = [
                'name' => $kompetenzen[$bereich->bereichid]['shortname'] ?? '',
                'abdeckung' => get_string('raster:bereich_abdeckung', 'local_berufsbildung', (object) [
                    'abgedeckt' => $bereich->anzahl_abgedeckt(),
                    'soll' => $bereich->soll(),
                ]),
                'hatsoll' => $bereich->soll() > 0,
                'zellen' => $zellen,
            ];
        }

        return $OUTPUT->render_from_template('local_berufsbildung/kompetenzraster', [
            'titel' => get_string('raster:titel', 'local_berufsbildung'),
            'beschreibung' => get_string('raster:beschreibung', 'local_berufsbildung'),
            'bereiche' => $bereiche,
            'kompakt' => $kompakt,
            'hathorizont' => $horizont !== null,
            'horizont' => $horizont !== null
                ? get_string(
                    'raster:horizont',
                    'local_berufsbildung',
                    userdate($horizont, get_string('strftimedaydate', 'langconfig'))
                )
                : '',
            'legende' => self::legende(),
        ]);
    }

    /**
     * Eine Rasterzelle.
     *
     * @param raster_kompetenz $kompetenz
     * @param array|null $bezeichnung shortname/idnumber aus core_competency
     * @return array Template-Kontext
     */
    private static function zelle(raster_kompetenz $kompetenz, ?array $bezeichnung): array {
        $darstellung = self::darstellung($kompetenz->status);
        $name = $bezeichnung['shortname'] ?? ('#' . $kompetenz->competencyid);
        $idnumber = $bezeichnung['idnumber'] ?? '';

        $art = $kompetenz->istwahlpflicht
            ? get_string('raster:wahlpflicht', 'local_berufsbildung')
            : get_string('raster:pflicht', 'local_berufsbildung');

        return [
            'istleer' => false,
            'name' => $name,
            'hascode' => $idnumber !== '',
            'code' => $idnumber,
            // Die Flaechenfarbe kommt ueber Bootstrap-Klassen, nie aus
            // styles.css - siehe Kopfkommentar dort.
            'farbklasse' => $kompetenz->istwahlpflicht ? 'bg-success' : 'bg-warning',
            'statusklasse' => 'local-berufsbildung-raster-' . $kompetenz->status,
            'icon' => $darstellung['icon'],
            'statustext' => $darstellung['text'],
            // Im kompakten Raster steht nur das Kuerzel in der Zelle; der
            // Titel traegt dort die ganze Information nach.
            'titel' => $name . ' · ' . $art . ' · ' . $darstellung['text'],
        ];
    }

    /**
     * Symbol und Klartext je Stand.
     *
     * @param string $status Eine der raster_kompetenz::STATUS_*-Konstanten
     * @return array{icon: string, text: string}
     */
    private static function darstellung(string $status): array {
        // Bewusst Symbole, die es in FontAwesome 4 und 6 gleichermassen
        // gibt - Moodle 4.5 und 5.x liefern nicht dieselbe Fassung aus.
        $symbole = [
            raster_kompetenz::STATUS_ABGEDECKT => 'fa-check',
            raster_kompetenz::STATUS_EINGEPLANT => 'fa-calendar',
            raster_kompetenz::STATUS_OFFEN => 'fa-minus',
        ];

        return [
            'icon' => $symbole[$status] ?? 'fa-minus',
            'text' => get_string('raster:status_' . $status, 'local_berufsbildung'),
        ];
    }

    /**
     * Legende: was Farbe und Symbol bedeuten.
     *
     * @return array Template-Kontext
     */
    private static function legende(): array {
        $eintraege = [];

        foreach (['pflicht' => 'bg-warning', 'wahlpflicht' => 'bg-success'] as $art => $farbklasse) {
            $eintraege[] = [
                'istfarbe' => true,
                'farbklasse' => $farbklasse,
                'text' => get_string('raster:legende_' . $art, 'local_berufsbildung'),
            ];
        }

        foreach ([
            raster_kompetenz::STATUS_ABGEDECKT,
            raster_kompetenz::STATUS_EINGEPLANT,
            raster_kompetenz::STATUS_OFFEN,
        ] as $status) {
            $darstellung = self::darstellung($status);
            $eintraege[] = [
                'istfarbe' => false,
                'icon' => $darstellung['icon'],
                'text' => get_string('raster:legende_' . $status, 'local_berufsbildung'),
            ];
        }

        return $eintraege;
    }

    /**
     * Bezeichnungen aller angezeigten Kompetenzen - Bereiche und
     * Handlungskompetenzen - in einer Abfrage statt einer je Zelle.
     *
     * @param raster_bereich[] $raster
     * @return array<int, array{shortname: string, idnumber: string}>
     */
    private static function lade_kompetenzen(array $raster): array {
        $ids = [];
        foreach ($raster as $bereich) {
            $ids[] = $bereich->bereichid;
            foreach ($bereich->kompetenzen as $kompetenz) {
                $ids[] = $kompetenz->competencyid;
            }
        }

        if (empty($ids)) {
            return [];
        }

        // Ausschliesslich Integer aus der API, deshalb direkt einsetzbar.
        $idliste = implode(',', array_map('intval', array_unique($ids)));

        $bezeichnungen = [];
        foreach (competency::get_records_select("id IN ({$idliste})") as $kompetenz) {
            $bezeichnungen[(int) $kompetenz->get('id')] = [
                'shortname' => format_string($kompetenz->get('shortname')),
                // Nur das Kuerzel, nicht die volle ID-Nummer: in der
                // kompakten Variante ist es das Einzige, was in der Zelle
                // Platz hat, und "b.07" ist dort der Anker zum gedruckten
                // Bildungsplan - "7777BE b.07" waere zur Haelfte der in
                // jeder Zelle gleiche Rahmen-Praefix.
                'idnumber' => format_string(kompetenz_baum::kuerzel($kompetenz)),
            ];
        }

        return $bezeichnungen;
    }
}

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

        // Welche Staende und Arten im Raster ueberhaupt vorkommen. Die
        // Legende erklaert nur diese: eine Zeile "spaeter eingeplant" unter
        // einem Raster ohne einen einzigen eingeplanten Eintrag kostet
        // Lesezeit und erklaert nichts.
        $vorhandenestaende = [];
        $vorhandenearten = [];
        $abgedeckt = 0;
        $soll = 0;

        $bereiche = [];
        foreach ($raster as $bereich) {
            $abgedeckt += $bereich->anzahl_abgedeckt();
            $soll += $bereich->soll();

            $zellen = [];
            foreach ($bereich->kompetenzen as $kompetenz) {
                $vorhandenestaende[$kompetenz->status] = true;
                $vorhandenearten[$kompetenz->istwahlpflicht ? 'wahlpflicht' : 'pflicht'] = true;
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
            'bereiche' => $bereiche,
            'kompakt' => $kompakt,
            // Die Gesamtzahl beantwortet dasselbe wie eine Lueckenliste
            // daneben, nur ohne dieselben Namen ein zweites Mal aufzuzaehlen
            // - welche Kompetenzen gemeint sind, steht im Raster darunter.
            // In der kompakten Variante traegt die Roster-Kachel diese Zahl
            // bereits als Badge.
            'haszusammenfassung' => !$kompakt && $soll > 0,
            'zusammenfassung' => get_string('raster:zusammenfassung', 'local_berufsbildung', (object) [
                'abgedeckt' => $abgedeckt,
                'soll' => $soll,
            ]),
            'hathorizont' => $horizont !== null,
            'horizont' => $horizont !== null
                ? get_string(
                    'raster:horizont',
                    'local_berufsbildung',
                    // Ohne Wochentag: bis wann der Plan reicht, ist eine
                    // Datumsfrage - ob das ein Sonntag ist, sagt nichts.
                    userdate($horizont, get_string('strftimedate', 'langconfig'))
                )
                : '',
            'legende' => self::legende($vorhandenestaende, $vorhandenearten),
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
            // Der haeufigste Stand bekommt das leiseste Zeichen: "nicht im
            // Plan" trifft auf die grosse Mehrheit der Zellen zu und traegt
            // damit den geringsten Informationswert. Sichtbares Symbol und
            // Klartext bekommen nur die beiden Staende, die etwas aussagen;
            // im Dokument bleibt der Klartext in jeder Zelle stehen, damit
            // Screenreader und der Titel beim Darueberfahren vollstaendig
            // bleiben - und Farbe nie der einzige Traeger der Information
            // ist.
            'hatzeichen' => $kompetenz->status !== raster_kompetenz::STATUS_OFFEN,
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
     * Legende: was Farbe und Symbol bedeuten - aber nur fuer das, was im
     * gezeigten Raster auch vorkommt. Eine Legende, die mehr erklaert als
     * dasteht, laesst den Blick nach etwas suchen, das es nicht gibt.
     *
     * @param array $vorhandenestaende Status => true, Struktur: array<string, bool>
     * @param array $vorhandenearten 'pflicht'/'wahlpflicht' => true, Struktur: array<string, bool>
     * @return array Template-Kontext
     */
    private static function legende(array $vorhandenestaende, array $vorhandenearten): array {
        $eintraege = [];

        foreach (['pflicht' => 'bg-warning', 'wahlpflicht' => 'bg-success'] as $art => $farbklasse) {
            if (empty($vorhandenearten[$art])) {
                continue;
            }

            $eintraege[] = [
                'istfarbe' => true,
                'hatzeichen' => false,
                'farbklasse' => $farbklasse,
                'text' => get_string('raster:legende_' . $art, 'local_berufsbildung'),
            ];
        }

        foreach ([
            raster_kompetenz::STATUS_ABGEDECKT,
            raster_kompetenz::STATUS_EINGEPLANT,
            raster_kompetenz::STATUS_OFFEN,
        ] as $status) {
            if (empty($vorhandenestaende[$status])) {
                continue;
            }

            $darstellung = self::darstellung($status);
            $eintraege[] = [
                'istfarbe' => false,
                // "nicht im Plan" zeigt sich in der Zelle durch das Fehlen
                // eines Zeichens - die Legende zeigt deshalb auch hier
                // keins, sonst erklaert sie ein Symbol, das im Raster
                // nirgends steht.
                'hatzeichen' => $status !== raster_kompetenz::STATUS_OFFEN,
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

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
use local_berufsbildung\wahlpflicht_gruppe;
use local_berufsbildung\service\kompetenz_baum;

/**
 * Handlungskompetenzbereiche als Zeilen, Handlungskompetenzen als Spalten -
 * dieselbe Anordnung wie im gedruckten Bildungsplan, damit die Darstellung
 * in Moodle und auf Papier dieselbe Gestalt hat.
 *
 * Die Flaeche einer Zelle zeigt den Ausbildungsstand - er ist das, wofuer
 * man das Raster aufschlaegt. Pflicht oder Wahlpflicht steht als schmaler
 * Streifen oben in der Zelle, in den Farben des Bildungsplans; so behaelt
 * das Raster den Bezug zum Dokument, ohne dass dessen Farben die ganze
 * Flaeche belegen. Der Stand steht zusaetzlich als Symbol und Klartext da,
 * damit Farbe nie der einzige Traeger einer Information ist.
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
     * @param wahlpflicht_gruppe[] $wahlpflichtgruppen Wie viele Wahlpflicht-HK der Bildungsplan
     *                      aus welchen Bereichen verlangt, siehe api::get_wahlpflicht_gruppen_for_beruf();
     *                      leer blendet die Angabe aus
     */
    public static function render(
        array $raster,
        ?int $horizont = null,
        bool $kompakt = false,
        array $wahlpflichtgruppen = []
    ): string {
        global $OUTPUT, $PAGE;

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
        $soll = 0;

        // Die Pflicht-HK je Stand, fuer die Zusammenfassung: "im Plan" allein
        // liesse offen, ob schon vorgekommen oder erst eingeplant.
        $pflichtstaende = [
            raster_kompetenz::STATUS_ABGEDECKT => 0,
            raster_kompetenz::STATUS_EINGEPLANT => 0,
            raster_kompetenz::STATUS_OFFEN => 0,
        ];

        $bereiche = [];
        foreach ($raster as $bereich) {
            $soll += $bereich->soll();

            $zellen = [];
            foreach ($bereich->kompetenzen as $kompetenz) {
                $vorhandenestaende[$kompetenz->status] = true;
                $vorhandenearten[$kompetenz->istwahlpflicht ? 'wahlpflicht' : 'pflicht'] = true;
                if (!$kompetenz->istwahlpflicht) {
                    $pflichtstaende[$kompetenz->status]++;
                }
                $zellen[] = self::zelle($kompetenz, $kompetenzen, $kompakt);
            }
            for ($leer = count($zellen); $leer < $spalten; $leer++) {
                $zellen[] = ['istleer' => true];
            }

            $bereiche[] = [
                'name' => $kompetenzen[$bereich->bereichid]['shortname'] ?? '',
                // Mit "Pflicht" beschriftet: ohne das liest man "0 von 3" in
                // einer Zeile mit sechs Zellen als Fehler. Kompakt fehlt der
                // Platz dafuer, dort erklaert es das Badge der Kachel.
                'abdeckung' => get_string(
                    $kompakt ? 'raster:bereich_abdeckung' : 'raster:bereich_abdeckung_pflicht',
                    'local_berufsbildung',
                    (object) [
                        'abgedeckt' => $bereich->anzahl_abgedeckt(),
                        'soll' => $bereich->soll(),
                    ]
                ),
                'hatsoll' => $bereich->soll() > 0,
                'zellen' => $zellen,
            ];
        }

        // Kommt gar nichts im Plan vor, ist "0 von 14" keine Aussage ueber die
        // Ausbildung, sondern ueber fehlende Daten - und so soll es auch
        // dastehen. Ohne Plan fehlt der Versetzungsplan selbst; mit Plan
        // fehlt fast immer die Kompetenzzuordnung der Ausbildungsbloecke.
        $nichtsimplan = empty($vorhandenestaende[raster_kompetenz::STATUS_ABGEDECKT])
            && empty($vorhandenestaende[raster_kompetenz::STATUS_EINGEPLANT]);
        $hinweis = '';
        if (!$kompakt && $nichtsimplan) {
            $hinweis = get_string(
                $horizont === null ? 'raster:hinweis_kein_plan' : 'raster:hinweis_nichts_im_plan',
                'local_berufsbildung'
            );
        }
        $bloeckelink = $hinweis !== '' && $horizont !== null
            && has_capability('local/berufsbildung:manageblocks', \context_system::instance());

        // Die LK-Liste einer Zelle oeffnet sich in einem Dialog statt in der
        // Zelle: bei zehn und mehr LK wuerde die Zeile sonst unuebersichtlich.
        if (!$kompakt) {
            $PAGE->requires->js_call_amd('local_berufsbildung/kompetenzraster', 'init');
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
            'haszusammenfassung' => !$kompakt && $soll > 0 && $hinweis === '',
            'zusammenfassung' => get_string('raster:zusammenfassung', 'local_berufsbildung', (object) [
                'soll' => $soll,
                'abgedeckt' => $pflichtstaende[raster_kompetenz::STATUS_ABGEDECKT],
                'eingeplant' => $pflichtstaende[raster_kompetenz::STATUS_EINGEPLANT],
                'offen' => $pflichtstaende[raster_kompetenz::STATUS_OFFEN],
            ]),
            'wahlpflichtstaende' => (!$kompakt && $hinweis === '')
                ? self::wahlpflichtstaende($wahlpflichtgruppen, self::mit_kuerzeln($raster, $kompetenzen))
                : [],
            'hathinweis' => $hinweis !== '',
            'hinweis' => $hinweis,
            'hatbloeckelink' => $bloeckelink,
            'bloeckeurl' => (new \moodle_url('/local/berufsbildung/bloecke.php'))->out(false),
            'bloeckelinktext' => get_string('raster:hinweis_bloecke', 'local_berufsbildung'),
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
     * Je Gruppe von Bereichen eine Zeile: verlangt, bereits vorgekommen,
     * spaeter eingeplant - und ob das Verlangte schon vorkam. Gezaehlt wird
     * mit wahlpflicht_gruppe::stand(), wie auf der Kachel in
     * meine_lernenden.php.
     *
     * @param wahlpflicht_gruppe[] $gruppen
     * @param raster_bereich[] $raster Mit Kuerzeln, siehe mit_kuerzeln()
     * @return array Template-Kontext: je Zeile text, erfuellt
     */
    private static function wahlpflichtstaende(array $gruppen, array $raster): array {
        $zeilen = [];
        foreach ($gruppen as $gruppe) {
            $stand = $gruppe->stand($raster);
            $daten = (object) [
                'bereiche' => implode(', ', $gruppe->bereiche),
                'soll' => $gruppe->anzahl,
                'abgedeckt' => $stand['abgedeckt'],
                'eingeplant' => $stand['eingeplant'],
            ];
            $zeilen[] = [
                'text' => get_string(
                    $gruppe->bereiche === [] ? 'raster:wahlpflicht_stand' : 'raster:wahlpflicht_stand_bereiche',
                    'local_berufsbildung',
                    $daten
                ),
                'erfuellt' => $daten->abgedeckt >= $gruppe->anzahl,
            ];
        }

        return $zeilen;
    }

    /**
     * Das Raster mit dem Kuerzel je Bereich. api::get_kompetenzraster()
     * liefert es bereits mit; ein von aussen gebautes Raster bekommt es aus
     * der ID-Nummer des Bereichs.
     *
     * @param raster_bereich[] $raster
     * @param array $kompetenzen Bezeichnungen aus lade_kompetenzen(), nach competencyid
     * @return raster_bereich[]
     */
    private static function mit_kuerzeln(array $raster, array $kompetenzen): array {
        return array_map(
            static fn (raster_bereich $bereich): raster_bereich => $bereich->kuerzel !== ''
                ? $bereich
                : new raster_bereich(
                    $bereich->bereichid,
                    $bereich->kompetenzen,
                    (string) ($kompetenzen[$bereich->bereichid]['idnumber'] ?? '')
                ),
            $raster
        );
    }

    /**
     * Eine Rasterzelle.
     *
     * @param raster_kompetenz $kompetenz
     * @param array $kompetenzen Bezeichnungen aus lade_kompetenzen(), nach competencyid
     * @param bool $kompakt Ohne LK-Liste - in der Roster-Kachel fehlt der Platz
     * @return array Template-Kontext
     */
    private static function zelle(raster_kompetenz $kompetenz, array $kompetenzen, bool $kompakt): array {
        $bezeichnung = $kompetenzen[$kompetenz->competencyid] ?? null;
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
            // Farbe des Streifens, wie im Bildungsplan. Sie kommt ueber
            // Bootstrap-Klassen, nie aus styles.css - siehe Kopfkommentar dort.
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
            'dialogtitel' => trim($idnumber . ' ' . $name),
        ] + self::leistungskriterien($kompetenz, $kompetenzen, $kompakt);
    }

    /**
     * Zahl und Liste der Leistungskriterien einer Zelle: eine HK gilt schon
     * als vorgekommen, sobald eines ihrer LK in einem Einsatz vorkommt -
     * abgeschlossen ist sie meist erst am Ende der Lehre. Die Zahl zeigt,
     * wie weit sie ist; ein Klick darauf zeigt in einem Dialog, welche LK
     * noch fehlen (amd/src/kompetenzraster.js). Ohne JavaScript klappt die
     * Liste in der Zelle auf.
     *
     * @param raster_kompetenz $kompetenz
     * @param array $kompetenzen Bezeichnungen aus lade_kompetenzen(), nach competencyid
     * @param bool $kompakt
     * @return array Template-Kontext: haslk, lktext, lkgruppen
     */
    private static function leistungskriterien(raster_kompetenz $kompetenz, array $kompetenzen, bool $kompakt): array {
        $anzahl = count($kompetenz->leistungskriterien);
        if ($kompakt || $anzahl === 0) {
            return ['haslk' => false, 'lktext' => '', 'lkgruppen' => []];
        }

        $eingeplant = $kompetenz->anzahl_lk(raster_kompetenz::STATUS_EINGEPLANT);
        $lktext = get_string(
            $eingeplant > 0 ? 'raster:lk_stand_eingeplant' : 'raster:lk_stand',
            'local_berufsbildung',
            (object) [
                'abgedeckt' => $kompetenz->anzahl_lk(raster_kompetenz::STATUS_ABGEDECKT),
                'eingeplant' => $eingeplant,
                'anzahl' => $anzahl,
            ]
        );

        // Nach Stand gruppiert, das Fehlende zuerst: dafuer oeffnet man die
        // Liste. Innerhalb einer Gruppe natuerlich sortiert wie in der
        // Kompetenzauswahl - die sortorder der LK folgt dem Rahmenimport,
        // nicht der Nummerierung.
        $zeichen = [
            raster_kompetenz::STATUS_OFFEN => 'fa-times',
            raster_kompetenz::STATUS_EINGEPLANT => 'fa-calendar',
            raster_kompetenz::STATUS_ABGEDECKT => 'fa-check',
        ];
        $gruppen = [];
        foreach ($zeichen as $status => $icon) {
            $eintraege = [];
            foreach ($kompetenz->leistungskriterien as $lkid => $lkstatus) {
                if ($lkstatus !== $status) {
                    continue;
                }
                $beschreibung = $kompetenzen[$lkid]['beschreibung'] ?? '';
                $eintraege[] = [
                    'name' => $kompetenzen[$lkid]['shortname'] ?? ('#' . $lkid),
                    'beschreibung' => $beschreibung,
                    'hatbeschreibung' => $beschreibung !== '',
                ];
            }
            if (empty($eintraege)) {
                continue;
            }

            usort(
                $eintraege,
                static fn (array $links, array $rechts): int => strnatcasecmp($links['name'], $rechts['name'])
            );

            $gruppen[] = [
                'titel' => get_string('raster:lk_gruppe_' . $status, 'local_berufsbildung', count($eintraege)),
                'statusklasse' => 'local-berufsbildung-raster-lk-' . $status,
                'icon' => $icon,
                'leistungskriterien' => $eintraege,
            ];
        }

        return ['haslk' => true, 'lktext' => $lktext, 'lkgruppen' => $gruppen];
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

        // Zuerst der Stand - er ist das, wofuer man das Raster liest.
        foreach (
            [
            raster_kompetenz::STATUS_ABGEDECKT,
            raster_kompetenz::STATUS_EINGEPLANT,
            raster_kompetenz::STATUS_OFFEN,
            ] as $status
        ) {
            if (empty($vorhandenestaende[$status])) {
                continue;
            }

            $darstellung = self::darstellung($status);
            $eintraege[] = [
                'istfarbe' => false,
                // Das Feld der Legende sieht aus wie eine Zelle mit diesem
                // Stand: gleiche Flaeche, gleiches Zeichen.
                'statusklasse' => 'local-berufsbildung-raster-' . $status,
                // Der Stand "nicht im Plan" zeigt sich in der Zelle durch das Fehlen
                // eines Zeichens - die Legende zeigt deshalb auch hier
                // keins, sonst erklaert sie ein Symbol, das im Raster
                // nirgends steht.
                'hatzeichen' => $status !== raster_kompetenz::STATUS_OFFEN,
                'icon' => $darstellung['icon'],
                'text' => get_string('raster:legende_' . $status, 'local_berufsbildung'),
            ];
        }

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
                foreach (array_keys($kompetenz->leistungskriterien) as $lkid) {
                    $ids[] = $lkid;
                }
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
                // Bei einem Leistungskriterium ist der Kurzname nur ein Code;
                // was es bedeutet, steht in der Beschreibung - als Titel der
                // Zeile in der LK-Liste.
                'beschreibung' => trim(html_to_text(format_text(
                    (string) $kompetenz->get('description'),
                    (int) $kompetenz->get('descriptionformat')
                ), 0, false)),
            ];
        }

        return $bezeichnungen;
    }
}

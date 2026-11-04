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
use local_berufsbildung\persistent\block;
use local_berufsbildung\persistent\einsatz;
use local_berufsbildung\raster_bereich;
use local_berufsbildung\raster_kompetenz;
use local_berufsbildung\wahlpflicht_vorgabe;
use local_berufsbildung\service\kompetenz_baum;

/**
 * Handlungskompetenzbereiche als Zeilen, Handlungskompetenzen als Spalten -
 * dieselbe Anordnung wie im gedruckten Bildungsplan, damit die Darstellung
 * in Moodle und auf Papier dieselbe Gestalt hat.
 *
 * Ein Balken je Zelle zeigt den Ausbildungsstand als Menge: welcher Anteil
 * der Leistungskriterien in Einsaetzen vorkam und welcher eingeplant ist.
 * Vier Farbstufen ueber die ganze Flaeche liessen sich kaum unterscheiden,
 * und "teilweise" sagte nicht, ob 1 oder 9 von 10. Die Zahl steht daneben
 * als Text, das Haekchen nur, wenn alle LK vorkamen - Farbe ist nie der
 * einzige Traeger einer Information. Wahlpflicht hat einen gestrichelten
 * Rahmen; Pflicht ist der Normalfall und braucht kein Zeichen.
 *
 * Kein Beurteilungsstatus: gezeigt wird ausschliesslich, was im
 * importierten Versetzungsplan vorkommt (Architekturregel 6). Ob eine
 * Kompetenz erreicht wurde, entscheiden die aufsetzenden Plugins.
 */
class kompetenzraster {
    /** Die Anzeigestaende einer HK in der Reihenfolge von Legende und Zusammenfassung. */
    private const ANZEIGESTAENDE = [
        raster_kompetenz::ANZEIGE_VOLLSTAENDIG,
        raster_kompetenz::ANZEIGE_TEILWEISE,
        raster_kompetenz::ANZEIGE_EINGEPLANT,
        raster_kompetenz::ANZEIGE_OFFEN,
    ];

    /**
     * Baut die Daten fuer das Template zusammen.
     *
     * @param raster_bereich[] $raster Ergebnis von api::get_kompetenzraster()
     * @param int|null $horizont Ende des vorliegenden Plans, siehe api::get_planungshorizont()
     * @param bool $kompakt Nur Kuerzel und Symbol statt der vollen Bezeichnung,
     *                      fuer die Roster-Karten in meine_lernenden.php
     * @param wahlpflicht_vorgabe|null $wahlpflicht Wie viele Wahlpflicht-HK der Bildungsplan
     *                      aus welchen Bereichen verlangt, siehe api::get_wahlpflicht_vorgabe_for_beruf();
     *                      null blendet die Angabe aus
     * @param array $vorschlaege Planungshilfe: je Kompetenz die Bloecke, in denen sie vorkaeme,
     *                      siehe api::get_bloecke_je_kompetenz(); genannt bei LK, die im Plan
     *                      fehlen. Leer blendet die Angabe aus
     * @param bool $planvollstaendig Plan bis ins letzte Semester, siehe api::ist_plan_vollstaendig():
     *                      dann ist eine HK ohne Einsatz eine Luecke, vorher nur noch nicht geplant
     * @param bool $zugeklappt Zuerst nur eine Punktreihe je Bereich, das volle Raster auf Klick -
     *                      fuer die Kacheln auf meine_lernenden.php, wo es je Person dasteht
     */
    public static function render(
        array $raster,
        ?int $horizont = null,
        bool $kompakt = false,
        ?wahlpflicht_vorgabe $wahlpflicht = null,
        array $vorschlaege = [],
        bool $planvollstaendig = false,
        bool $zugeklappt = false
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
        $mitgeplantem = false;
        $soll = 0;
        // Kuerzel der Pflicht-HK ohne jeden Einsatz, fuer die Zeile unter
        // der Zusammenfassung.
        $ohneeinsatz = [];

        // Die Pflicht-HK je Stand, fuer die Zusammenfassung: "im Plan" allein
        // liesse offen, ob vollstaendig, teilweise oder erst eingeplant.
        $pflichtstaende = array_fill_keys(self::ANZEIGESTAENDE, 0);

        // Die Einsaetze, in denen die LK vorkamen oder vorkommen werden -
        // einmal fuer das ganze Raster geladen, nicht je Zelle.
        $einsaetze = self::lade_einsaetze($raster);

        // Auf schmalen Bildschirmen oeffnet die Liste den LK-Dialog der
        // Tabellenzelle, statt die LK-Liste ein zweites Mal mitzubringen.
        // Eindeutig je Raster, weil auf meine_lernenden.php mehrere stehen.
        $rasterid = \html_writer::random_id('local-berufsbildung-raster');

        $bereiche = [];
        foreach ($raster as $bereich) {
            $soll += $bereich->soll();

            $zellen = [];
            $hatwahlpflicht = false;
            foreach ($bereich->kompetenzen as $kompetenz) {
                $hatwahlpflicht = $hatwahlpflicht || $kompetenz->istwahlpflicht;
                $anzeige = $kompetenz->anzeigestand();
                $vorhandenestaende[$anzeige] = true;
                $vorhandenearten[$kompetenz->istwahlpflicht ? 'wahlpflicht' : 'pflicht'] = true;
                if (!$kompetenz->istwahlpflicht) {
                    $pflichtstaende[$anzeige]++;
                    if ($anzeige === raster_kompetenz::ANZEIGE_OFFEN) {
                        $kuerzel = $kompetenzen[$kompetenz->competencyid]['idnumber'] ?? '';
                        $ohneeinsatz[] = $kuerzel !== '' ? $kuerzel : '#' . $kompetenz->competencyid;
                    }
                }
                $zelle = self::zelle($kompetenz, $kompetenzen, $einsaetze, $kompakt, $vorschlaege, $planvollstaendig)
                    + ['zellid' => $rasterid . '-' . $kompetenz->competencyid];
                // Ohne Kuerzel die Nummer in der Zeile: das Badge der
                // Uebersicht braucht eine Beschriftung.
                $zelle['marke'] = $zelle['hascode'] ? $zelle['code'] : (string) (count($zellen) + 1);
                $mitgeplantem = $mitgeplantem || $zelle['anteileingeplant'] > 0;
                $zellen[] = $zelle;
            }
            for ($leer = count($zellen); $leer < $spalten; $leer++) {
                $zellen[] = ['istleer' => true];
            }

            $kuerzel = $bereich->kuerzel !== ''
                ? $bereich->kuerzel
                : (string) ($kompetenzen[$bereich->bereichid]['idnumber'] ?? '');
            $bereiche[] = [
                'name' => $kompetenzen[$bereich->bereichid]['shortname'] ?? '',
                // Die Wahlpflicht-Zeilen und "noch nicht geplant" nennen
                // die Bereiche beim Buchstaben.
                'haskuerzel' => $kuerzel !== '',
                'kuerzel' => $kuerzel,
                // Mit "Pflicht" beschriftet: ohne das liest man "0 von 3" in
                // einer Zeile mit sechs Zellen als Fehler. Kompakt fehlt der
                // Platz dafuer, dort erklaert es das Badge der Kachel.
                // Begonnen statt vollstaendig: alle LK einer HK kommen meist
                // erst gegen Ende der Lehre vor, "0 von 3" stuende sonst bis
                // dahin in fast jeder Zeile.
                'abdeckung' => get_string(
                    $kompakt ? 'raster:bereich_abdeckung' : 'raster:bereich_abdeckung_pflicht',
                    'local_berufsbildung',
                    (object) [
                        'abgedeckt' => $bereich->anzahl_abgedeckt(),
                        'soll' => $bereich->soll(),
                    ]
                ),
                'hatsoll' => $bereich->soll() > 0,
                'hatwahlpflicht' => $hatwahlpflicht,
                'zellen' => $zellen,
            ];
        }

        // Kommt gar nichts im Plan vor, ist "0 von 14" keine Aussage ueber die
        // Ausbildung, sondern ueber fehlende Daten - und so soll es auch
        // dastehen. Ohne Plan fehlt der Versetzungsplan selbst; mit Plan
        // fehlt fast immer die Kompetenzzuordnung der Ausbildungsbloecke.
        $nichtsimplan = empty($vorhandenestaende[raster_kompetenz::ANZEIGE_VOLLSTAENDIG])
            && empty($vorhandenestaende[raster_kompetenz::ANZEIGE_TEILWEISE])
            && empty($vorhandenestaende[raster_kompetenz::ANZEIGE_EINGEPLANT]);
        $hinweis = '';
        if (!$kompakt && $nichtsimplan) {
            $hinweis = get_string(
                $horizont === null ? 'raster:hinweis_kein_plan' : 'raster:hinweis_nichts_im_plan',
                'local_berufsbildung'
            );
        }
        $bloeckelink = $hinweis !== '' && $horizont !== null
            && has_capability('local/berufsbildung:manageblocks', \context_system::instance());
        $mitwahlpflicht = !$kompakt && $hinweis === '' && $wahlpflicht !== null;

        // Die LK-Liste einer Zelle oeffnet sich in einem Dialog statt in der
        // Zelle: bei zehn und mehr LK wuerde die Zeile sonst unuebersichtlich.
        if (!$kompakt) {
            $PAGE->requires->js_call_amd('local_berufsbildung/kompetenzraster', 'init');
        }

        return $OUTPUT->render_from_template('local_berufsbildung/kompetenzraster', [
            'titel' => get_string('raster:titel', 'local_berufsbildung'),
            'bereiche' => $bereiche,
            'kompakt' => $kompakt,
            'zugeklappt' => $zugeklappt,
            // Die Gesamtzahl beantwortet dasselbe wie eine Lueckenliste
            // daneben, nur ohne dieselben Namen ein zweites Mal aufzuzaehlen
            // - welche Kompetenzen gemeint sind, steht im Raster darunter.
            // In der kompakten Variante traegt die Roster-Kachel diese Zahl
            // bereits als Badge.
            'haszusammenfassung' => !$kompakt && $soll > 0 && $hinweis === '',
            // Zugeklappt nur die Zahl neben dem Titel, wie die Anzahl neben
            // den uebrigen Ueberschriften der Kachel; wie viele alle LK
            // haben, zeigen dort die Haekchen der Badges.
            'kurzzusammenfassung' => get_string('raster:bereich_abdeckung_pflicht', 'local_berufsbildung', (object) [
                'abgedeckt' => $pflichtstaende[raster_kompetenz::ANZEIGE_VOLLSTAENDIG]
                    + $pflichtstaende[raster_kompetenz::ANZEIGE_TEILWEISE],
                'soll' => $soll,
            ]),
            'zusammenfassung' => get_string('raster:zusammenfassung', 'local_berufsbildung', (object) [
                'soll' => $soll,
                'begonnen' => $pflichtstaende[raster_kompetenz::ANZEIGE_VOLLSTAENDIG]
                    + $pflichtstaende[raster_kompetenz::ANZEIGE_TEILWEISE],
                'vollstaendig' => $pflichtstaende[raster_kompetenz::ANZEIGE_VOLLSTAENDIG],
            ]),
            // Was der Plan nicht vorsieht, mit Kuerzeln statt nur als Zahl:
            // vor dem letzten Lehrjahr ruhig als "noch nicht geplant", mit
            // dem Plan bis zum Lehrende als Warnung.
            'hatohneeinsatz' => !$kompakt && $hinweis === '' && $ohneeinsatz !== [],
            'ohneeinsatzwarnung' => $planvollstaendig,
            'ohneeinsatz' => get_string(
                $planvollstaendig ? 'raster:nicht_im_plan' : 'raster:noch_nicht_geplant',
                'local_berufsbildung',
                implode(', ', $ohneeinsatz)
            ),
            'wahlpflichtwege' => $mitwahlpflicht
                ? self::wahlpflichtwege($wahlpflicht, self::mit_kuerzeln($raster, $kompetenzen))
                : [],
            // Nur bei mehreren Wegen: dass einer genuegt, steht sonst nirgends.
            'hatwahlpflichtwahl' => $mitwahlpflicht && count($wahlpflicht->wege) > 1,
            'wahlpflichtwahl' => get_string('raster:wahlpflicht_wege', 'local_berufsbildung'),
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
            'legende' => self::legende($vorhandenestaende, $vorhandenearten, $mitgeplantem, $planvollstaendig),
            'markenlegende' => self::markenlegende($vorhandenestaende, $planvollstaendig),
        ]);
    }

    /**
     * Je Weg die Gruppen, je Gruppe eine Zeile: verlangt, vollstaendig,
     * teilweise, spaeter eingeplant - und ob genug HK vollstaendig vorkamen.
     * Gezaehlt wird mit wahlpflicht_gruppe::stand() und
     * wahlpflicht_vorgabe::offen_je_weg(), wie auf der Kachel in
     * meine_lernenden.php.
     *
     * Bei einem einzigen Weg stehen nur die Gruppen da, wie vor den Wegen;
     * bei mehreren je Weg eine Ueberschrift mit eigenem Haekchen, die
     * Gruppen darunter eingerueckt.
     *
     * @param wahlpflicht_vorgabe $vorgabe
     * @param raster_bereich[] $raster Mit Kuerzeln, siehe mit_kuerzeln()
     * @return array Template-Kontext: je Weg hattitel, titel, erfuellt, staende
     */
    private static function wahlpflichtwege(wahlpflicht_vorgabe $vorgabe, array $raster): array {
        $mehrere = count($vorgabe->wege) > 1;
        $offen = $vorgabe->offen_je_weg($raster);

        $wege = [];
        foreach (array_values($vorgabe->wege) as $nummer => $gruppen) {
            $staende = [];
            foreach ($gruppen as $gruppe) {
                $stand = $gruppe->stand($raster);
                $daten = (object) [
                    'bereiche' => implode(', ', $gruppe->bereiche),
                    'soll' => $gruppe->anzahl,
                    'begonnen' => $stand[raster_kompetenz::ANZEIGE_VOLLSTAENDIG] + $stand[raster_kompetenz::ANZEIGE_TEILWEISE],
                    'vollstaendig' => $stand[raster_kompetenz::ANZEIGE_VOLLSTAENDIG],
                    'eingeplant' => $stand[raster_kompetenz::ANZEIGE_EINGEPLANT],
                ];
                $staende[] = [
                    'text' => get_string(
                        $gruppe->bereiche === [] ? 'raster:wahlpflicht_stand' : 'raster:wahlpflicht_stand_bereiche',
                        'local_berufsbildung',
                        $daten
                    ),
                    'erfuellt' => $daten->vollstaendig >= $gruppe->anzahl,
                    'eingerueckt' => $mehrere,
                ];
            }
            $wege[] = [
                'hattitel' => $mehrere,
                'titel' => get_string('raster:wahlpflicht_weg', 'local_berufsbildung', $nummer + 1),
                'erfuellt' => $offen[$nummer] === 0,
                'staende' => $staende,
            ];
        }

        return $wege;
    }

    /**
     * Das Raster mit dem Kuerzel je Bereich und je HK.
     * api::get_kompetenzraster() liefert beide bereits mit; ein von aussen
     * gebautes Raster bekommt sie aus der ID-Nummer.
     *
     * @param raster_bereich[] $raster
     * @param array $kompetenzen Bezeichnungen aus lade_kompetenzen(), nach competencyid
     * @return raster_bereich[]
     */
    private static function mit_kuerzeln(array $raster, array $kompetenzen): array {
        return array_map(
            static fn (raster_bereich $bereich): raster_bereich => new raster_bereich(
                $bereich->bereichid,
                array_map(
                    static fn (raster_kompetenz $kompetenz): raster_kompetenz => $kompetenz->kuerzel !== ''
                        ? $kompetenz
                        : new raster_kompetenz(
                            competencyid: $kompetenz->competencyid,
                            status: $kompetenz->status,
                            istwahlpflicht: $kompetenz->istwahlpflicht,
                            leistungskriterien: $kompetenz->leistungskriterien,
                            lkeinsaetze: $kompetenz->lkeinsaetze,
                            kuerzel: (string) ($kompetenzen[$kompetenz->competencyid]['idnumber'] ?? ''),
                        ),
                    $bereich->kompetenzen
                ),
                $bereich->kuerzel !== ''
                    ? $bereich->kuerzel
                    : (string) ($kompetenzen[$bereich->bereichid]['idnumber'] ?? '')
            ),
            $raster
        );
    }

    /**
     * Eine Rasterzelle.
     *
     * @param raster_kompetenz $kompetenz
     * @param array $kompetenzen Bezeichnungen aus lade_kompetenzen(), nach competencyid
     * @param array $einsaetze Einsaetze aus lade_einsaetze(), nach einsatz-id
     * @param bool $kompakt Ohne LK-Liste - in der Roster-Kachel fehlt der Platz
     * @param array $vorschlaege Bloecke je Kompetenz, siehe render()
     * @param bool $planvollstaendig Siehe render()
     * @return array Template-Kontext
     */
    private static function zelle(
        raster_kompetenz $kompetenz,
        array $kompetenzen,
        array $einsaetze,
        bool $kompakt,
        array $vorschlaege = [],
        bool $planvollstaendig = false
    ): array {
        $bezeichnung = $kompetenzen[$kompetenz->competencyid] ?? null;
        $anzeige = $kompetenz->anzeigestand();
        $darstellung = self::darstellung($anzeige, $planvollstaendig);
        $name = $bezeichnung['shortname'] ?? ('#' . $kompetenz->competencyid);
        $idnumber = $bezeichnung['idnumber'] ?? '';

        $art = $kompetenz->istwahlpflicht
            ? get_string('raster:wahlpflicht', 'local_berufsbildung')
            : get_string('raster:pflicht', 'local_berufsbildung');

        // Anteile fuer den Balken. Eine HK ohne LK fuellt ihn ganz oder gar
        // nicht, je nach ihrem eigenen Stand.
        $anzahllk = count($kompetenz->leistungskriterien);
        if ($anzahllk > 0) {
            $vorgekommen = $kompetenz->anzahl_lk(raster_kompetenz::STATUS_ABGEDECKT) / $anzahllk;
            $eingeplant = $kompetenz->anzahl_lk(raster_kompetenz::STATUS_EINGEPLANT) / $anzahllk;
        } else {
            $vorgekommen = $kompetenz->status === raster_kompetenz::STATUS_ABGEDECKT ? 1 : 0;
            $eingeplant = $kompetenz->status === raster_kompetenz::STATUS_EINGEPLANT ? 1 : 0;
        }

        return [
            'istleer' => false,
            'name' => $name,
            'hascode' => $idnumber !== '',
            'code' => $idnumber,
            'istwahlpflicht' => $kompetenz->istwahlpflicht,
            'statusklasse' => 'local-berufsbildung-raster-' . $anzeige,
            // Das Haekchen nur, wenn alle LK vorkamen; die uebrigen Staende
            // zeigt der Balken. Der Klartext bleibt in jeder Zelle im
            // Dokument, fuer Screenreader und den Titel beim Darueberfahren.
            'istvollstaendig' => $anzeige === raster_kompetenz::ANZEIGE_VOLLSTAENDIG,
            'statustext' => $darstellung['text'],
            'anteilvorgekommen' => (int) round($vorgekommen * 100),
            'anteileingeplant' => (int) round($eingeplant * 100),
            'markenklasse' => self::markenklasse($anzeige, $planvollstaendig && !$kompetenz->istwahlpflicht),
            // Im kompakten Raster steht nur das Kuerzel in der Zelle; der
            // Titel traegt dort die ganze Information nach.
            'titel' => $name . ' · ' . $art . ' · ' . $darstellung['text'],
            'dialogtitel' => trim($idnumber . ' ' . $name),
            // Eine HK ohne LK hat keinen Dialog; fehlt sie im Plan, steht
            // der Vorschlag direkt in der Zelle.
            'vorschlag' => !$kompakt && empty($kompetenz->leistungskriterien)
                && $anzeige === raster_kompetenz::ANZEIGE_OFFEN
                ? self::vorschlag($vorschlaege[$kompetenz->competencyid] ?? [])
                : '',
        ] + self::leistungskriterien($kompetenz, $kompetenzen, $einsaetze, $kompakt, $vorschlaege);
    }

    /**
     * Planungshilfe: die Bloecke, in denen eine fehlende Kompetenz vorkaeme,
     * z. B. "Kommt vor in: B4 Werkstatt, B7 Montage".
     *
     * @param block[] $bloecke
     * @return string Leer ohne Bloecke
     */
    private static function vorschlag(array $bloecke): string {
        if (empty($bloecke)) {
            return '';
        }

        $namen = array_map(
            static fn (block $block): string => format_string(trim($block->get('nummer') . ' ' . $block->get('name'))),
            $bloecke
        );

        return get_string('raster:vorschlag', 'local_berufsbildung', implode(', ', $namen));
    }

    /**
     * Zahl und Liste der Leistungskriterien einer Zelle: vollstaendig ist
     * eine HK erst, wenn alle ihre LK vorkamen - meist gegen Ende der Lehre.
     * Die Zahl zeigt, wie weit sie ist; ein Klick darauf zeigt in einem
     * Dialog, welche LK noch fehlen und in welchen Einsaetzen die uebrigen
     * vorkamen oder geplant sind (amd/src/kompetenzraster.js). Ohne
     * JavaScript klappt die Liste in der Zelle auf.
     *
     * @param raster_kompetenz $kompetenz
     * @param array $kompetenzen Bezeichnungen aus lade_kompetenzen(), nach competencyid
     * @param array $einsaetze Einsaetze aus lade_einsaetze(), nach einsatz-id
     * @param bool $kompakt
     * @param array $vorschlaege Bloecke je Kompetenz, siehe render()
     * @return array Template-Kontext: haslk, lktext, lkgruppen
     */
    private static function leistungskriterien(
        raster_kompetenz $kompetenz,
        array $kompetenzen,
        array $einsaetze,
        bool $kompakt,
        array $vorschlaege = []
    ): array {
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
                // Wo das LK vorkam oder geplant ist: Block und Zeitraum je
                // Einsatz, in der Reihenfolge des Plans.
                $orte = [];
                foreach ($kompetenz->lkeinsaetze[$lkid] ?? [] as $einsatzid) {
                    if (isset($einsaetze[$einsatzid])) {
                        $orte[] = $einsaetze[$einsatzid];
                    }
                }
                $eintraege[] = [
                    'name' => $kompetenzen[$lkid]['shortname'] ?? ('#' . $lkid),
                    'beschreibung' => $beschreibung,
                    'hatbeschreibung' => $beschreibung !== '',
                    'hatorte' => !empty($orte),
                    'orte' => implode(' · ', $orte),
                    // Fehlt das LK im Plan, die Bloecke, in denen es vorkaeme.
                    'vorschlag' => $status === raster_kompetenz::STATUS_OFFEN
                        ? self::vorschlag($vorschlaege[$lkid] ?? [])
                        : '',
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
     * Klartext je Stand.
     *
     * @param string $status Eine der raster_kompetenz::ANZEIGE_*-Konstanten
     * @param bool $planvollstaendig Siehe render()
     * @return array{text: string}
     */
    private static function darstellung(string $status, bool $planvollstaendig = false): array {
        $schluessel = 'raster:status_' . $status;
        if ($planvollstaendig && $status === raster_kompetenz::ANZEIGE_OFFEN) {
            $schluessel = 'raster:status_offen_vollstaendig';
        }

        return ['text' => get_string($schluessel, 'local_berufsbildung')];
    }

    /**
     * Bootstrap-Klassen des Badges einer HK in der Uebersicht: gruen mit
     * Haekchen, wenn alle LK vorkamen, Info-Farbe, wenn einige vorkamen,
     * mit Rand in Info-Farbe, wenn sie eingeplant ist, und grau, wenn
     * nichts geplant ist. Reicht der Plan bis zum Lehrende, ist eine
     * Pflicht-HK ohne Einsatz eine Luecke und steht in der Farbe des
     * Luecken-Badges der Kachel.
     *
     * @param string $anzeige Anzeigestand, siehe raster_kompetenz::anzeigestand()
     * @param bool $istluecke Pflicht-HK und Plan bis zum Lehrende
     * @return string
     */
    private static function markenklasse(string $anzeige, bool $istluecke = false): string {
        return match ($anzeige) {
            raster_kompetenz::ANZEIGE_VOLLSTAENDIG => 'bg-success text-white',
            raster_kompetenz::ANZEIGE_TEILWEISE => 'bg-info text-white',
            raster_kompetenz::ANZEIGE_EINGEPLANT => 'bg-white text-info border border-info',
            default => $istluecke ? 'bg-warning text-dark' : 'text-muted border',
        };
    }

    /**
     * Legende der Badges in der Uebersicht: je vorkommender Stand ein Badge,
     * das mit dem Stand selbst beschriftet ist.
     *
     * @param array $vorhandenestaende Anzeigestand => true, Struktur: array<string, bool>
     * @param bool $planvollstaendig Siehe render()
     * @return array Template-Kontext: je Eintrag markenklasse, istvollstaendig, text
     */
    private static function markenlegende(array $vorhandenestaende, bool $planvollstaendig): array {
        $eintraege = [];
        foreach (self::ANZEIGESTAENDE as $anzeige) {
            if (empty($vorhandenestaende[$anzeige])) {
                continue;
            }
            $eintraege[] = [
                'markenklasse' => self::markenklasse($anzeige, $planvollstaendig),
                'istvollstaendig' => $anzeige === raster_kompetenz::ANZEIGE_VOLLSTAENDIG,
                'text' => self::darstellung($anzeige, $planvollstaendig)['text'],
            ];
        }
        return $eintraege;
    }

    /**
     * Legende: was Balken, Haekchen und Rahmen bedeuten - aber nur fuer das,
     * was im gezeigten Raster auch vorkommt. Eine Legende, die mehr erklaert
     * als dasteht, laesst den Blick nach etwas suchen, das es nicht gibt.
     *
     * @param array $vorhandenestaende Anzeigestand => true, Struktur: array<string, bool>
     * @param array $vorhandenearten 'pflicht'/'wahlpflicht' => true, Struktur: array<string, bool>
     * @param bool $mitgeplantem Mindestens ein Balken hat einen eingeplanten Teil
     * @param bool $planvollstaendig Siehe render()
     * @return array Template-Kontext
     */
    private static function legende(
        array $vorhandenestaende,
        array $vorhandenearten,
        bool $mitgeplantem,
        bool $planvollstaendig
    ): array {
        $balken = static fn (string $klasse, string $text): array => [
            'istbalken' => true, 'istalle' => false, 'istwahlpflicht' => false,
            'balkenklasse' => $klasse, 'text' => $text,
        ];

        $eintraege = [];
        if (
            !empty($vorhandenestaende[raster_kompetenz::ANZEIGE_VOLLSTAENDIG])
            || !empty($vorhandenestaende[raster_kompetenz::ANZEIGE_TEILWEISE])
        ) {
            $eintraege[] = $balken(
                'local-berufsbildung-raster-legende-vorgekommen',
                get_string('raster:legende_vorgekommen', 'local_berufsbildung')
            );
        }
        if ($mitgeplantem) {
            $eintraege[] = $balken(
                'local-berufsbildung-raster-legende-eingeplant',
                get_string('raster:legende_eingeplant', 'local_berufsbildung')
            );
        }
        if (!empty($vorhandenestaende[raster_kompetenz::ANZEIGE_OFFEN])) {
            $eintraege[] = $balken(
                'local-berufsbildung-raster-legende-leer',
                get_string($planvollstaendig ? 'raster:legende_offen_vollstaendig' : 'raster:legende_offen', 'local_berufsbildung')
            );
        }
        if (!empty($vorhandenestaende[raster_kompetenz::ANZEIGE_VOLLSTAENDIG])) {
            $eintraege[] = [
                'istbalken' => false, 'istalle' => true, 'istwahlpflicht' => false,
                'text' => get_string('raster:legende_vollstaendig', 'local_berufsbildung'),
            ];
        }
        if (!empty($vorhandenearten['wahlpflicht'])) {
            $eintraege[] = [
                'istbalken' => false, 'istalle' => false, 'istwahlpflicht' => true,
                'text' => get_string('raster:legende_wahlpflicht', 'local_berufsbildung'),
            ];
        }

        return $eintraege;
    }

    /**
     * Die Einsaetze, in denen LK des Rasters vorkamen oder vorkommen werden,
     * als kurzer Text "Block, KW 32-37" - Bloecke und Einsaetze je in einer
     * Abfrage fuer das ganze Raster.
     *
     * @param raster_bereich[] $raster
     * @return array<int, string> einsatz-id => Block und Zeitraum
     */
    private static function lade_einsaetze(array $raster): array {
        $ids = [];
        foreach ($raster as $bereich) {
            foreach ($bereich->kompetenzen as $kompetenz) {
                foreach ($kompetenz->lkeinsaetze as $einsatzids) {
                    foreach ($einsatzids as $einsatzid) {
                        $ids[(int) $einsatzid] = true;
                    }
                }
            }
        }
        if (empty($ids)) {
            return [];
        }

        // Ausschliesslich Integer aus der API, deshalb direkt einsetzbar.
        $einsaetze = einsatz::get_records_select('id IN (' . implode(',', array_keys($ids)) . ')');
        $blockids = array_unique(array_map(static fn (einsatz $einsatz): int => (int) $einsatz->get('blockid'), $einsaetze));
        $bloecke = [];
        foreach (block::get_records_select('id IN (' . implode(',', array_map('intval', $blockids)) . ')') as $block) {
            $bloecke[(int) $block->get('id')] = (string) $block->get('name');
        }

        $texte = [];
        foreach ($einsaetze as $einsatz) {
            $kontext = einsatz_darstellung::zu_kontext($einsatz, $bloecke[(int) $einsatz->get('blockid')] ?? '');
            $texte[(int) $einsatz->get('id')] = get_string('raster:lk_einsatz', 'local_berufsbildung', (object) [
                // Unformatiert: das Template escaped selbst, format_string()
                // davor ergaebe doppelt kodierte Sonderzeichen.
                'block' => $bloecke[(int) $einsatz->get('blockid')] ?? '',
                'zeit' => $kontext['haskw'] ? $kontext['kw'] : $kontext['zeitraum'],
            ]);
        }

        return $texte;
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

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
     * Zeichen, nach denen die angezeigte Beschreibung abgeschnitten wird.
     * Genug, um ein Leistungskriterium zu erkennen, ohne dass eine Zeile
     * zum Absatz wird - shorten_text() schneidet an der Wortgrenze.
     */
    private const BESCHREIBUNG_ZEICHEN = 120;

    /** Zeichen, nach denen der volle Text im Titel abgeschnitten wird. */
    private const TITEL_ZEICHEN = 500;

    /**
     * Baut die Daten fuer das Template zusammen.
     *
     * Der Stand (was ist schon zugeordnet, was teilweise) wird am ganzen
     * Baum ermittelt und erst danach gefiltert: "2 von 5 LK" soll bei einer
     * Suche nicht zu "1 von 1 LK" werden, nur weil vier ausgeblendet sind.
     *
     * @param array $baum Ergebnis von service\kompetenz_baum::baum(), ungefiltert
     * @param array $zugeordnet competencyid => beliebiger Wert, geprueft wird nur der Schluessel
     * @param moodle_url $actionurl Ziel des Formulars
     * @param int $blockid
     * @param string $suchbegriff Aktiver Filter, leer wenn keiner gesetzt ist
     */
    public static function render(
        array $baum,
        array $zugeordnet,
        moodle_url $actionurl,
        int $blockid,
        string $suchbegriff = ''
    ): string {
        global $OUTPUT;

        $suchaktiv = self::suchbegriffe($suchbegriff) !== [];
        $stand = self::stand($baum, $zugeordnet);
        $nichtgefunden = $suchaktiv ? self::nicht_gefunden($baum, $suchbegriff) : [];

        $bereiche = [];
        foreach (self::filtere($baum, $suchbegriff) as $zweig) {
            $handlungskompetenzen = [];

            foreach ($zweig['handlungskompetenzen'] as $eintrag) {
                $hk = self::eintrag($eintrag['kompetenz'], $zugeordnet, true);
                $hkstand = $stand['handlungskompetenzen'][$hk['id']];

                // Ist die Handlungskompetenz als Ganzes zugeordnet, sind
                // ihre Leistungskriterien damit abgedeckt - eine Checkbox
                // daneben wuerde nur eine doppelte Zeile anlegen.
                $abgedecktdurch = $hk['hatcode']
                    ? get_string('blocklk:abgedeckt_durch_hk', 'local_berufsbildung', $hk['code'])
                    : get_string('blocklk:abgedeckt_durch_hk_allgemein', 'local_berufsbildung');

                $leistungskriterien = [];
                $sichtbarzugeordnet = 0;
                foreach ($eintrag['leistungskriterien'] as $lk) {
                    // Ohne Kuerzel: die ID-Nummer eines LK ist eine
                    // generierte Eindeutigkeitsnummer ("7777BE_AU b1 01
                    // 1-2#4") und sagt nichts, was nicht schon im
                    // shortname ("AU b1 01 1-2") steht.
                    $lkeintrag = self::eintrag($lk, $zugeordnet, false);
                    $lkeintrag['istabgedeckt'] = !$lkeintrag['istzugeordnet'] && $hkstand['direkt'];
                    $lkeintrag['waehlbar'] = !$lkeintrag['istzugeordnet'] && !$lkeintrag['istabgedeckt'];
                    $lkeintrag['erledigt'] = !$lkeintrag['waehlbar'];
                    $lkeintrag['abgedeckttext'] = $abgedecktdurch;
                    $leistungskriterien[] = $lkeintrag;
                    if ($lkeintrag['istzugeordnet']) {
                        $sichtbarzugeordnet++;
                    }
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

                // Teilweise heisst: nicht als Ganzes zugeordnet, aber schon
                // einzelne Leistungskriterien. Fuer Lueckenanalyse und Raster
                // gilt die HK damit bereits als abgedeckt - die Auswahl zeigt
                // trotzdem ehrlich, wie viel davon tatsaechlich gewaehlt ist.
                $teilstand = !$hkstand['direkt'] && $hkstand['lkzugeordnet'] > 0;

                $handlungskompetenzen[] = $hk + [
                    'waehlbar' => !$hkstand['ganz'],
                    'erledigt' => $hkstand['ganz'],
                    'hatteilstand' => $teilstand,
                    'teilstandtext' => $teilstand ? get_string('blocklk:lk_stand', 'local_berufsbildung', (object) [
                        'zugeordnet' => $hkstand['lkzugeordnet'],
                        'anzahl' => $hkstand['lkanzahl'],
                    ]) : '',
                    'leistungskriterien' => $leistungskriterien,
                    'hatlk' => !empty($leistungskriterien),
                    // Bei aktiver Suche aufgeklappt: der Treffer kann in
                    // einem Leistungskriterium liegen, und den hinter einem
                    // zugeklappten Aufklapper zu verstecken waere das
                    // Gegenteil dessen, wofuer man sucht.
                    'lkoffen' => $suchaktiv,
                    'lktext' => self::lktext(count($leistungskriterien), $sichtbarzugeordnet, $hkstand['direkt']),
                ];
            }

            $bereichstand = $stand['bereiche'][(int) $zweig['bereich']->get('id')];

            $bereiche[] = [
                'code' => format_string(kompetenz_baum::kuerzel($zweig['bereich'])),
                'name' => format_string($zweig['bereich']->get('shortname')),
                'suchtext' => self::suchtext($zweig['bereich']),
                'handlungskompetenzen' => $handlungskompetenzen,
                'hatstand' => $bereichstand['anzahl'] > 0,
                'standtext' => get_string(
                    $bereichstand['teilweise'] > 0 ? 'blocklk:bereich_stand_teilweise' : 'blocklk:bereich_stand',
                    'local_berufsbildung',
                    (object) $bereichstand
                ),
                'standvollstaendig' => $bereichstand['anzahl'] > 0 && $bereichstand['ganz'] === $bereichstand['anzahl'],
                // Aufgeklappt starten, wo noch etwas zu tun ist. Der Bereich
                // zeigt dann seine Handlungskompetenzen als kurze Liste; die
                // Leistungskriterien darunter bleiben zugeklappt, sonst waeren
                // es ueber alle Bereiche mehrere hundert Zeilen und die
                // HK-Namen gingen darin unter.
                'offen' => $suchaktiv || $bereichstand['ganz'] < $bereichstand['anzahl'],
            ];
        }

        $seite = new moodle_url('/local/berufsbildung/block_kompetenzen.php');

        return $OUTPUT->render_from_template('local_berufsbildung/kompetenz_auswahl', [
            'action' => $actionurl->out(false),
            'sesskey' => sesskey(),
            'blockid' => $blockid,
            'bereiche' => $bereiche,
            'hatbereiche' => !empty($bereiche),
            'sucheaction' => $seite->out(false),
            'zuruecksetzenurl' => (new moodle_url($seite, ['id' => $blockid]))->out(false),
            'suchbegriff' => $suchbegriff,
            'suchaktiv' => $suchaktiv,
            'suchelabel' => get_string('blocklk:suche', 'local_berufsbildung'),
            'sucheplaceholder' => get_string('blocklk:suche_placeholder', 'local_berufsbildung'),
            'suchen' => get_string('blocklk:suchen', 'local_berufsbildung'),
            'suchezuruecksetzen' => get_string('blocklk:suche_zuruecksetzen', 'local_berufsbildung'),
            // Ohne den Suchbegriff im Text: die Live-Suche blendet dieselbe
            // Meldung ein, ohne die Seite neu zu laden, und wuerde sonst
            // den Begriff des letzten Seitenaufbaus nennen.
            'keinetreffer' => get_string('blocklk:suche_keine_treffer', 'local_berufsbildung'),
            // Nur neben anderen Treffern - findet gar nichts, sagt das
            // bereits keinetreffer.
            'hatnichtgefunden' => !empty($nichtgefunden) && !empty($bereiche),
            'nichtgefunden' => implode(', ', $nichtgefunden),
            'nichtgefundenlabel' => get_string('blocklk:suche_nicht_gefunden', 'local_berufsbildung'),
            'absenden' => get_string('blocklk:hinzufuegen', 'local_berufsbildung'),
            'zugeordnettext' => get_string('blocklk:bereits_zugeordnet', 'local_berufsbildung'),
        ]);
    }

    /**
     * Zuordnungsstand des ganzen Baums, ohne Datenbankzugriff.
     *
     * Eine Handlungskompetenz ist "ganz" zugeordnet, wenn sie selbst
     * zugeordnet ist oder alle ihre Leistungskriterien einzeln. Sind nur
     * einige ihrer Leistungskriterien zugeordnet, zaehlt sie im Bereich als
     * "teilweise".
     *
     * Waehlbar ist, was eine neue Zuordnung noch etwas aendern wuerde: keine
     * bereits zugeordnete Kompetenz, kein Leistungskriterium unter einer
     * ganz zugeordneten Handlungskompetenz und keine Handlungskompetenz,
     * deren Leistungskriterien schon alle zugeordnet sind. Dieselbe Menge
     * prueft block_kompetenzen.php beim Speichern.
     *
     * @param array $baum Ergebnis von service\kompetenz_baum::baum(), ungefiltert
     * @param array $zugeordnet competencyid => beliebiger Wert, geprueft wird nur der Schluessel
     * @return array{handlungskompetenzen: array<int, array{direkt: bool, ganz: bool, lkanzahl: int,
     *               lkzugeordnet: int}>, bereiche: array<int, array{anzahl: int, ganz: int, teilweise: int}>,
     *               waehlbar: array<int, true>}
     */
    public static function stand(array $baum, array $zugeordnet): array {
        $handlungskompetenzen = [];
        $bereiche = [];
        $waehlbar = [];

        foreach ($baum as $zweig) {
            $bereich = ['anzahl' => 0, 'ganz' => 0, 'teilweise' => 0];

            foreach ($zweig['handlungskompetenzen'] as $eintrag) {
                $hkid = (int) $eintrag['kompetenz']->get('id');
                $direkt = isset($zugeordnet[$hkid]);

                $lkanzahl = count($eintrag['leistungskriterien']);
                $lkzugeordnet = 0;
                foreach ($eintrag['leistungskriterien'] as $lk) {
                    $lkid = (int) $lk->get('id');
                    if (isset($zugeordnet[$lkid])) {
                        $lkzugeordnet++;
                    } else if (!$direkt) {
                        $waehlbar[$lkid] = true;
                    }
                }

                $ganz = $direkt || ($lkanzahl > 0 && $lkzugeordnet === $lkanzahl);
                if (!$ganz) {
                    $waehlbar[$hkid] = true;
                }

                $handlungskompetenzen[$hkid] = [
                    'direkt' => $direkt,
                    'ganz' => $ganz,
                    'lkanzahl' => $lkanzahl,
                    'lkzugeordnet' => $lkzugeordnet,
                ];

                $bereich['anzahl']++;
                if ($ganz) {
                    $bereich['ganz']++;
                } else if ($lkzugeordnet > 0) {
                    $bereich['teilweise']++;
                }
            }

            $bereiche[(int) $zweig['bereich']->get('id')] = $bereich;
        }

        return [
            'handlungskompetenzen' => $handlungskompetenzen,
            'bereiche' => $bereiche,
            'waehlbar' => $waehlbar,
        ];
    }

    /**
     * Beschriftung des zugeklappten Aufklappers der Leistungskriterien -
     * sie verraet den Stand, ohne dass man jede HK aufklappen muss.
     *
     * @param int $anzahl Angezeigte Leistungskriterien
     * @param int $zugeordnet Davon einzeln zugeordnet
     * @param bool $hkdirekt Die Handlungskompetenz ist als Ganzes zugeordnet
     */
    private static function lktext(int $anzahl, int $zugeordnet, bool $hkdirekt): string {
        if ($hkdirekt) {
            return get_string('blocklk:lk_aufklappen_abgedeckt', 'local_berufsbildung', $anzahl);
        }

        if ($zugeordnet > 0) {
            return get_string('blocklk:lk_aufklappen_teilweise', 'local_berufsbildung', (object) [
                'anzahl' => $anzahl,
                'zugeordnet' => $zugeordnet,
            ]);
        }

        return get_string('blocklk:lk_aufklappen', 'local_berufsbildung', $anzahl);
    }

    /**
     * Schraenkt den Baum auf einen Suchbegriff ein, ohne seine Gliederung
     * aufzugeben - gesucht wird in Bezeichnung, ID-Nummer und Beschreibung.
     *
     * Wer den Code eines Leistungskriteriums kennt, soll ihn nicht ueber
     * vier Bereiche und zwei Dutzend Handlungskompetenzen suchen muessen.
     * Bereich und Handlungskompetenz bleiben aber stehen, damit ein Treffer
     * weiterhin seinen Platz im Bildungsplan zeigt.
     *
     * Trifft der Bereich oder die Handlungskompetenz selbst, gehoert alles
     * darunter dazu: wer "Instandhalten" sucht, meint den ganzen Bereich.
     * Trifft nur ein Leistungskriterium, bleibt von seiner
     * Handlungskompetenz nur dieses uebrig.
     *
     * Mehrere Begriffe, durch Komma, Semikolon oder Zeilenumbruch getrennt,
     * gelten als "oder" (siehe suchbegriffe()): so laesst sich die
     * LK-Liste eines Arbeitsplatzes aus einer Tabelle einfuegen und dann
     * von Hand ankreuzen.
     *
     * Rein lesend und ohne Datenbankzugriff - die Kompetenzen sind bereits
     * geladen, und ein Volltextindex waere fuer einige hundert Zeilen
     * unverhaeltnismaessig.
     *
     * @param array $baum Ergebnis von service\kompetenz_baum::baum()
     * @param string $suchbegriff Leer = unveraendert zurueck
     * @return array Baum in derselben Struktur
     */
    public static function filtere(array $baum, string $suchbegriff): array {
        $nadel = self::nadeln($suchbegriff);
        if ($nadel === []) {
            return $baum;
        }

        $gefiltert = [];
        foreach ($baum as $zweig) {
            if (self::trifft($zweig['bereich'], $nadel)) {
                $gefiltert[] = $zweig;
                continue;
            }

            $handlungskompetenzen = [];
            foreach ($zweig['handlungskompetenzen'] as $eintrag) {
                if (self::trifft($eintrag['kompetenz'], $nadel)) {
                    $handlungskompetenzen[] = $eintrag;
                    continue;
                }

                $treffer = array_values(array_filter(
                    $eintrag['leistungskriterien'],
                    static fn (competency $lk): bool => self::trifft($lk, $nadel)
                ));

                if (!empty($treffer)) {
                    $handlungskompetenzen[] = [
                        'kompetenz' => $eintrag['kompetenz'],
                        'leistungskriterien' => $treffer,
                    ];
                }
            }

            if (!empty($handlungskompetenzen)) {
                $gefiltert[] = [
                    'bereich' => $zweig['bereich'],
                    'handlungskompetenzen' => $handlungskompetenzen,
                ];
            }
        }

        return $gefiltert;
    }

    /**
     * Zerlegt die Eingabe des Suchfelds in einzelne Begriffe.
     *
     * Getrennt wird an Komma, Semikolon, Tabulator und Zeilenumbruch, nicht
     * am Leerzeichen: ein LK-Code wie "MEM 11 05 1-2" enthaelt selbst
     * welche. Doppelte Begriffe zaehlen einmal, ohne Ruecksicht auf
     * Gross- und Kleinschreibung.
     *
     * Dieselbe Regel wendet amd/src/kompetenz_suche.js im Browser an.
     *
     * @param string $suchbegriff
     * @return string[] Begriffe in der eingegebenen Schreibweise
     */
    public static function suchbegriffe(string $suchbegriff): array {
        $begriffe = [];
        foreach (preg_split('/[,;\t\r\n]+/u', $suchbegriff) as $teil) {
            $teil = trim($teil);
            $schluessel = \core_text::strtolower($teil);
            if ($teil !== '' && !isset($begriffe[$schluessel])) {
                $begriffe[$schluessel] = $teil;
            }
        }

        return array_values($begriffe);
    }

    /**
     * Die Begriffe der Eingabe, die nirgends im Baum vorkommen - etwa ein
     * vertippter Code oder ein LK, das zum Rahmen eines anderen Berufs
     * gehoert. Bei einer eingefuegten Liste faellt sonst nicht auf, dass
     * einer von sieben Codes nichts gefunden hat.
     *
     * @param array $baum Ergebnis von service\kompetenz_baum::baum(), ungefiltert
     * @param string $suchbegriff
     * @return string[] Begriffe in der eingegebenen Schreibweise
     */
    public static function nicht_gefunden(array $baum, string $suchbegriff): array {
        $suchtexte = [];
        foreach ($baum as $zweig) {
            $suchtexte[] = self::suchtext($zweig['bereich']);
            foreach ($zweig['handlungskompetenzen'] as $eintrag) {
                $suchtexte[] = self::suchtext($eintrag['kompetenz']);
                foreach ($eintrag['leistungskriterien'] as $lk) {
                    $suchtexte[] = self::suchtext($lk);
                }
            }
        }

        return array_values(array_filter(
            self::suchbegriffe($suchbegriff),
            static function (string $begriff) use ($suchtexte): bool {
                $nadel = \core_text::strtolower($begriff);
                foreach ($suchtexte as $suchtext) {
                    if (str_contains($suchtext, $nadel)) {
                        return false;
                    }
                }

                return true;
            }
        ));
    }

    /**
     * Die Suchbegriffe klein geschrieben, wie sie verglichen werden.
     *
     * @param string $suchbegriff
     * @return string[]
     */
    private static function nadeln(string $suchbegriff): array {
        return array_map(
            static fn (string $begriff): string => \core_text::strtolower($begriff),
            self::suchbegriffe($suchbegriff)
        );
    }

    /**
     * Passt eine Kompetenz auf einen der Suchbegriffe?
     *
     * @param competency $kompetenz
     * @param string[] $nadeln Bereits klein geschriebene Suchbegriffe
     */
    private static function trifft(competency $kompetenz, array $nadeln): bool {
        $suchtext = self::suchtext($kompetenz);
        foreach ($nadeln as $nadel) {
            if (str_contains($suchtext, $nadel)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Der Text, in dem gesucht wird: Bezeichnung, ID-Nummer und
     * Beschreibung, klein geschrieben.
     *
     * Steht auch als data-Attribut im Markup, damit die Live-Suche im
     * Browser dieselbe Grundlage hat wie filtere() auf dem Server. Aus dem
     * sichtbaren Text liesse sich das nicht gewinnen: die Beschreibung ist
     * dort auf 120 Zeichen gekuerzt.
     *
     * @param competency $kompetenz
     */
    private static function suchtext(competency $kompetenz): string {
        return \core_text::strtolower(implode(' ', [
            (string) $kompetenz->get('shortname'),
            (string) $kompetenz->get('idnumber'),
            content_to_text(
                (string) $kompetenz->get('description'),
                (int) $kompetenz->get('descriptionformat')
            ),
        ]));
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
        // shortname nur ein Code ("AU a1 01 1-2"), der fuer sich genommen
        // nichts aussagt. Angezeigt wird sie gekuerzt, damit eine Zeile
        // eine Zeile bleibt; der volle Text haengt im Titel.
        $beschreibung = content_to_text(
            (string) $kompetenz->get('description'),
            (int) $kompetenz->get('descriptionformat')
        );

        return [
            'id' => $id,
            'code' => $code,
            'hatcode' => $code !== '',
            'name' => format_string($kompetenz->get('shortname')),
            'suchtext' => self::suchtext($kompetenz),
            'hatbeschreibung' => $beschreibung !== '',
            'kurzbeschreibung' => shorten_text($beschreibung, self::BESCHREIBUNG_ZEICHEN),
            'beschreibung' => shorten_text($beschreibung, self::TITEL_ZEICHEN),
            'istzugeordnet' => isset($zugeordnet[$id]),
        ];
    }
}

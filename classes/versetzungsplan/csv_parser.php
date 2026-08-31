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
 * Parst die Versetzungsplan-CSV in beiden Formaten.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\versetzungsplan;

use coding_exception;

/**
 * Kennt weder $DB noch Moodle-Nutzer - liefert reine Zeitraeume je
 * (E-Mail, Block). Die Aufloesung von E-Mail zu Nutzer/in und Blocknummer
 * zu block-Datensatz passiert erst im import_service.
 *
 * Wochenformat (Spalte 'kw') wird zu zusammenhaengenden Zeitraeumen
 * gruppiert; Blockformat (Spalten 'kw_von'/'kw_bis') wird zeilenweise
 * uebernommen. Beide Formate in derselben Datei sind nicht zulaessig - die
 * Kopfzeile entscheidet.
 *
 * Ueberschneidende Zeitraeume derselben Person (unabhaengig vom Block)
 * werden abgewiesen und protokolliert, nicht stillschweigend zusammengefuehrt.
 */
class csv_parser {

    public function __construct(
        private readonly kw_converter $kwconverter = new kw_converter()
    ) {
    }

    /**
     * @param string $inhalt Roher CSV-Text, Semikolon-getrennt, mit Kopfzeile
     * @return array{eintraege: array, fehler: string[], zeilen_gelesen: int}
     */
    public function parsen(string $inhalt): array {
        $zeilen = preg_split('/\r\n|\r|\n/', trim($inhalt));
        $zeilen = array_values(array_filter($zeilen, static fn (string $z): bool => trim($z) !== ''));

        if (empty($zeilen)) {
            return ['eintraege' => [], 'fehler' => [], 'zeilen_gelesen' => 0];
        }

        $kopf = str_getcsv((string) array_shift($zeilen), ';');
        $spaltenindex = [];
        foreach ($kopf as $i => $name) {
            $spaltenindex[trim(mb_strtolower((string) $name))] = $i;
        }

        $formatfehler = $this->kopfzeile_pruefen($spaltenindex);
        if ($formatfehler !== null) {
            return ['eintraege' => [], 'fehler' => [$formatfehler], 'zeilen_gelesen' => 0];
        }

        $istwochenformat = isset($spaltenindex['kw']);
        $fehler = [];
        $rohzeilen = [];

        foreach ($zeilen as $index => $roh) {
            $zeilennummer = $index + 2; // Zeile 1 ist die Kopfzeile.
            $werte = str_getcsv($roh, ';');

            $email = trim((string) ($werte[$spaltenindex['email']] ?? ''));
            $block = trim((string) ($werte[$spaltenindex['block']] ?? ''));
            $bemerkung = isset($spaltenindex['bemerkung']) ? trim((string) ($werte[$spaltenindex['bemerkung']] ?? '')) : '';

            if ($email === '' || $block === '') {
                $fehler[] = "Zeile {$zeilennummer}: E-Mail oder Block fehlt - übersprungen.";
                continue;
            }

            if ($istwochenformat) {
                $kw = trim((string) ($werte[$spaltenindex['kw']] ?? ''));
                try {
                    [$von, $bis] = $this->kwconverter->zu_zeitraum($kw);
                } catch (coding_exception $e) {
                    $fehler[] = "Zeile {$zeilennummer}: ungültige Kalenderwoche '{$kw}' - übersprungen.";
                    continue;
                }
                $rohzeilen[] = [
                    'email' => $email, 'block' => $block, 'bemerkung' => $bemerkung,
                    'kw_von' => $kw, 'kw_bis' => $kw, 'von' => $von, 'bis' => $bis,
                ];
            } else {
                $kwvon = trim((string) ($werte[$spaltenindex['kw_von']] ?? ''));
                $kwbis = trim((string) ($werte[$spaltenindex['kw_bis']] ?? ''));
                try {
                    [$von] = $this->kwconverter->zu_zeitraum($kwvon);
                    [, $bis] = $this->kwconverter->zu_zeitraum($kwbis);
                } catch (coding_exception $e) {
                    $fehler[] = "Zeile {$zeilennummer}: ungültige Kalenderwoche - übersprungen.";
                    continue;
                }
                if ($bis < $von) {
                    $fehler[] = "Zeile {$zeilennummer}: kw_bis liegt vor kw_von - übersprungen.";
                    continue;
                }
                $rohzeilen[] = [
                    'email' => $email, 'block' => $block, 'bemerkung' => $bemerkung,
                    'kw_von' => $kwvon, 'kw_bis' => $kwbis, 'von' => $von, 'bis' => $bis,
                ];
            }
        }

        $eintraege = $istwochenformat ? $this->wochen_zusammenfassen($rohzeilen) : $rohzeilen;

        $ueberschneidungen = $this->ueberschneidungen_pruefen($eintraege);

        return [
            'eintraege' => $ueberschneidungen['eintraege'],
            'fehler' => array_merge($fehler, $ueberschneidungen['fehler']),
            'zeilen_gelesen' => count($zeilen),
        ];
    }

    /**
     * @param array<string,int> $spaltenindex
     * @return string|null Fehlermeldung, oder null wenn die Kopfzeile gueltig ist
     */
    private function kopfzeile_pruefen(array $spaltenindex): ?string {
        $hatwoche = isset($spaltenindex['kw']);
        $hatblock = isset($spaltenindex['kw_von']) && isset($spaltenindex['kw_bis']);

        if (!$hatwoche && !$hatblock) {
            return 'Kopfzeile: weder kw_von/kw_bis noch kw gefunden.';
        }
        if ($hatwoche && $hatblock) {
            return 'Kopfzeile: kw_von/kw_bis und kw dürfen nicht gemischt werden.';
        }
        if (!isset($spaltenindex['email']) || !isset($spaltenindex['block'])) {
            return 'Kopfzeile: Spalte "email" oder "block" fehlt.';
        }

        return null;
    }

    /**
     * Fasst aufeinanderfolgende Kalenderwochen mit derselben E-Mail und
     * demselben Block zu einem Eintrag zusammen. "Aufeinanderfolgend" wird
     * ueber die tatsaechliche Zeit geprueft (Ende der einen Woche + 1
     * Sekunde = Anfang der naechsten), nicht ueber Wochennummern-Arithmetik
     * - das behandelt Jahres- und Woche-53-Uebergaenge automatisch richtig.
     *
     * @param array $rohzeilen
     * @return array
     */
    private function wochen_zusammenfassen(array $rohzeilen): array {
        $gruppen = [];
        foreach ($rohzeilen as $zeile) {
            $schluessel = $zeile['email'] . "\x00" . $zeile['block'];
            $gruppen[$schluessel][] = $zeile;
        }

        $eintraege = [];
        foreach ($gruppen as $zeilen) {
            usort($zeilen, static fn (array $a, array $b): int => $a['von'] <=> $b['von']);

            $aktuell = null;
            foreach ($zeilen as $zeile) {
                if ($aktuell !== null && $zeile['von'] === $aktuell['bis'] + 1) {
                    $aktuell['bis'] = $zeile['bis'];
                    $aktuell['kw_bis'] = $zeile['kw_bis'];
                    continue;
                }
                if ($aktuell !== null) {
                    $eintraege[] = $aktuell;
                }
                $aktuell = $zeile;
            }
            if ($aktuell !== null) {
                $eintraege[] = $aktuell;
            }
        }

        return $eintraege;
    }

    /**
     * Zeitraeume derselben Person duerfen sich nicht ueberschneiden -
     * unabhaengig vom Block, eine Person kann nicht an zwei Orten
     * gleichzeitig sein. Beide beteiligten Eintraege werden abgewiesen,
     * nicht stillschweigend zusammengefuehrt.
     *
     * @param array $eintraege
     * @return array{eintraege: array, fehler: string[]}
     */
    private function ueberschneidungen_pruefen(array $eintraege): array {
        $nachemail = [];
        foreach ($eintraege as $i => $eintrag) {
            $nachemail[$eintrag['email']][] = $i;
        }

        $abgewiesen = [];
        $fehler = [];

        foreach ($nachemail as $email => $indizes) {
            usort($indizes, static fn (int $a, int $b): int => $eintraege[$a]['von'] <=> $eintraege[$b]['von']);

            $laengster = null;
            foreach ($indizes as $index) {
                $jetzt = $eintraege[$index];
                if ($laengster !== null && $jetzt['von'] <= $eintraege[$laengster]['bis']) {
                    $vorher = $eintraege[$laengster];
                    $fehler[] = "Überschneidung für {$email}: {$vorher['kw_von']}-{$vorher['kw_bis']} und "
                        . "{$jetzt['kw_von']}-{$jetzt['kw_bis']} - beide übersprungen.";
                    $abgewiesen[$laengster] = true;
                    $abgewiesen[$index] = true;
                }
                if ($laengster === null || $jetzt['bis'] > $eintraege[$laengster]['bis']) {
                    $laengster = $index;
                }
            }
        }

        $uebrig = [];
        foreach ($eintraege as $i => $eintrag) {
            if (!isset($abgewiesen[$i])) {
                $uebrig[] = $eintrag;
            }
        }

        return ['eintraege' => $uebrig, 'fehler' => $fehler];
    }
}

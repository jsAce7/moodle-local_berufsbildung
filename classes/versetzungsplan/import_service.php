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
 * Verarbeitet eine Versetzungsplan-Lieferung: Vorschau (Testlauf) oder
 * tatsaechliche Uebernahme.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\versetzungsplan;

use local_berufsbildung\api;
use local_berufsbildung\persistent\block;
use local_berufsbildung\persistent\einsatz;
use local_berufsbildung\persistent\plan_import;
use local_berufsbildung\persistent\zuordnung;

/**
 * Reihenfolge (siehe docs/schnittstelle_versetzungsplan.md):
 * 1. Hash aus CSV-Inhalt und aktuellem Zuordnungsstand gegen den letzten
 *    erfolgreichen Import - unveraendert -> nichts tun. Der Zuordnungsstand
 *    fliesst mit ein, damit eine inhaltlich identische Lieferung trotzdem
 *    neu verarbeitet wird, wenn seither eine Zuordnung dazugekommen oder
 *    beendet wurde - sonst wuerde eine neu zugeordnete Person erst mit der
 *    naechsten inhaltlich abweichenden Lieferung erfasst, nicht schon mit
 *    der naechsten (identischen) woechentlichen.
 * 2. CSV parsen - bei Kopfzeilenfehlern: fehlgeschlagen, nichts geschrieben.
 * 3. Jede Zeile aufloesen: E-Mail -> Nutzer/in, nur bei aktiver Zuordnung
 *    verarbeiten (lautlos gezaehlt, nicht protokolliert), unbekannte
 *    Bloecke werden protokolliert.
 * 4. Vollstaendigkeitsschutz: deutlich weniger verarbeitete Personen als
 *    beim letzten erfolgreichen Lauf -> abgewiesen, ausser bestaetigt.
 * 5. Testlauf endet hier, ohne zu schreiben. Sonst: pro verarbeiteter
 *    Person die bestehenden Einsaetze im Zeitraum der Lieferung ersetzen,
 *    in einer Transaktion.
 */
class import_service {
    /**
     * Konstruktor.
     *
     * @param csv_parser $parser
     */
    public function __construct(
        /** @var csv_parser */
        private readonly csv_parser $parser = new csv_parser()
    ) {
    }

    /**
     * Verarbeitet eine Versetzungsplan-Lieferung.
     *
     * @param string $csvinhalt Roher CSV-Text
     * @param string $quelle 'webservice' | 'upload' | Freitext
     * @param int $ausgefuehrtvon userid
     * @param bool $testlauf true = nur pruefen, nichts schreiben
     * @param bool $rueckgangbestaetigt true = ein deutlicher Rueckgang der verarbeiteten Personen ist beabsichtigt
     * @return array{status: string, unveraendert: bool, zeilen_gelesen: int, personen_verarbeitet: int,
     *               zeilen_ausserhalb_geltungsbereich: int, einsaetze_erzeugt: int, protokoll: string[]}
     */
    public function verarbeiten(
        string $csvinhalt,
        string $quelle,
        int $ausgefuehrtvon,
        bool $testlauf = false,
        bool $rueckgangbestaetigt = false
    ): array {
        $hash = hash('sha256', $csvinhalt . "\x00" . $this->zuordnungs_fingerabdruck());
        $letztererfolgreicher = $this->letzter_erfolgreicher_import();

        if ($letztererfolgreicher !== null && $letztererfolgreicher->get('daten_hash') === $hash) {
            return $this->ergebnis('ok', true, 0, 0, 0, 0, []);
        }

        $geparst = $this->parser->parsen($csvinhalt);

        if (empty($geparst['eintraege']) && !empty($geparst['fehler'])) {
            // Kopfzeilenfehler oder eine komplett unlesbare Datei - nichts
            // liess sich extrahieren, damit ist der ganze Lauf gescheitert.
            if (!$testlauf) {
                $this->schreibe_protokoll($quelle, $hash, $ausgefuehrtvon, $geparst['zeilen_gelesen'], 0, 0, 0, 'fehlgeschlagen', $geparst['fehler']);
            }

            return $this->ergebnis('fehlgeschlagen', false, $geparst['zeilen_gelesen'], 0, 0, 0, $geparst['fehler']);
        }

        // Eine syntaktisch oder zeitlich fehlerhafte Lieferung darf nie
        // teilweise uebernommen werden: sonst wuerden beim Ersetzen die
        // gueltigen Restzeilen den bestehenden Plan ausduennen.
        if (!empty($geparst['fehler'])) {
            if (!$testlauf) {
                $this->schreibe_protokoll(
                    $quelle,
                    $hash,
                    $ausgefuehrtvon,
                    $geparst['zeilen_gelesen'],
                    0,
                    0,
                    0,
                    'fehlgeschlagen',
                    $geparst['fehler']
                );
            }
            return $this->ergebnis('fehlgeschlagen', false, $geparst['zeilen_gelesen'], 0, 0, 0, $geparst['fehler']);
        }

        $aufgeloest = $this->zeilen_aufloesen($geparst['eintraege']);
        $protokoll = array_merge($geparst['fehler'], $aufgeloest['protokoll']);
        $personenverarbeitet = count($aufgeloest['nachuserid']);

        if (!$testlauf && $letztererfolgreicher !== null && $letztererfolgreicher->get('personen_verarbeitet') > 0) {
            $schwelle = $this->schwelle_prozent();
            $mindestanzahl = (int) ceil($letztererfolgreicher->get('personen_verarbeitet') * (1 - $schwelle / 100));

            if ($personenverarbeitet < $mindestanzahl && !$rueckgangbestaetigt) {
                $hinweis = sprintf(
                    'Lieferung abgewiesen: nur %d von zuvor %d verarbeiteten Personen (Schwelle %d%%). '
                        . 'Falls beabsichtigt, mit rueckgang_bestaetigt=1 erneut senden.',
                    $personenverarbeitet,
                    $letztererfolgreicher->get('personen_verarbeitet'),
                    $schwelle
                );
                $this->schreibe_protokoll(
                    $quelle,
                    $hash,
                    $ausgefuehrtvon,
                    $geparst['zeilen_gelesen'],
                    $personenverarbeitet,
                    $aufgeloest['ausserhalb_geltungsbereich'],
                    0,
                    'abgewiesen',
                    [$hinweis]
                );

                return $this->ergebnis(
                    'abgewiesen',
                    false,
                    $geparst['zeilen_gelesen'],
                    $personenverarbeitet,
                    $aufgeloest['ausserhalb_geltungsbereich'],
                    0,
                    [$hinweis]
                );
            }
        }

        $status = empty($protokoll) ? 'ok' : 'mit_warnungen';

        if ($testlauf) {
            return $this->ergebnis(
                $status,
                false,
                $geparst['zeilen_gelesen'],
                $personenverarbeitet,
                $aufgeloest['ausserhalb_geltungsbereich'],
                $this->zaehle_eintraege($aufgeloest['nachuserid']),
                $protokoll
            );
        }

        $einsaetzeerzeugt = $this->schreiben(
            $aufgeloest['nachuserid'],
            $quelle,
            $hash,
            $ausgefuehrtvon,
            $geparst['zeilen_gelesen'],
            $personenverarbeitet,
            $aufgeloest['ausserhalb_geltungsbereich'],
            $status,
            $protokoll
        );

        return $this->ergebnis(
            $status,
            false,
            $geparst['zeilen_gelesen'],
            $personenverarbeitet,
            $aufgeloest['ausserhalb_geltungsbereich'],
            $einsaetzeerzeugt,
            $protokoll
        );
    }

    /**
     * Loest jede Eintrags-E-Mail zu einer Person auf und filtert nach
     * aktiver Zuordnung. Unbekannte Bloecke werden protokolliert, aber
     * noch nicht angelegt - das passiert erst in schreiben().
     *
     * @param array $eintraege
     * @return array{nachuserid: array<int,array>, ausserhalb_geltungsbereich: int, protokoll: string[]}
     */
    private function zeilen_aufloesen(array $eintraege): array {
        global $DB;

        $nachuserid = [];
        $ausserhalbgeltungsbereich = 0;
        $protokoll = [];
        $bekanntebloecke = [];

        foreach ($eintraege as $eintrag) {
            $nutzer = $DB->get_records('user', ['email' => $eintrag['email'], 'deleted' => 0], '', 'id');
            if (count($nutzer) !== 1) {
                $protokoll[] = "Mailadresse {$eintrag['email']} keinem Moodle-Konto zugeordnet – übersprungen";
                continue;
            }

            $userid = (int) reset($nutzer)->id;

            if (empty(api::get_berufsbildner_for($userid))) {
                $ausserhalbgeltungsbereich++;
                continue;
            }

            if (!array_key_exists($eintrag['block'], $bekanntebloecke)) {
                $bekanntebloecke[$eintrag['block']] = (bool) block::get_record(['nummer' => $eintrag['block']]);
                if (!$bekanntebloecke[$eintrag['block']]) {
                    $protokoll[] = "Block '{$eintrag['block']}' in Moodle unbekannt – ohne Kompetenzabdeckung angelegt";
                }
            }

            $nachuserid[$userid][] = $eintrag;
        }

        return [
            'nachuserid' => $nachuserid,
            'ausserhalb_geltungsbereich' => $ausserhalbgeltungsbereich,
            'protokoll' => $protokoll,
        ];
    }

    /**
     * Zaehlt die Eintraege einer Lieferung.
     *
     * @param array $nachuserid Struktur: array<int,array>
     * @return int
     */
    private function zaehle_eintraege(array $nachuserid): int {
        $anzahl = 0;
        foreach ($nachuserid as $eintraege) {
            $anzahl += count($eintraege);
        }

        return $anzahl;
    }

    /**
     * Legt fehlende Bloecke an, ersetzt je verarbeiteter Person die
     * bestehenden Einsaetze im Zeitraum der Lieferung und schreibt das
     * Importprotokoll - alles in einer Transaktion, damit ein Fehler
     * mittendrin den bestehenden Datenbestand nicht antastet
     * (Architekturregel 5).
     *
     * @param array $nachuserid Struktur: array<int,array>
     * @param string $quelle
     * @param string $hash
     * @param int $ausgefuehrtvon
     * @param int $zeilengelesen
     * @param int $personenverarbeitet
     * @param int $ausserhalbgeltungsbereich
     * @param string $status
     * @param string[] $protokoll
     * @return int Anzahl erzeugter Einsaetze
     */
    private function schreiben(
        array $nachuserid,
        string $quelle,
        string $hash,
        int $ausgefuehrtvon,
        int $zeilengelesen,
        int $personenverarbeitet,
        int $ausserhalbgeltungsbereich,
        string $status,
        array $protokoll
    ): int {
        global $DB;

        $transaktion = $DB->start_delegated_transaction();

        $blockidsnachnummer = [];
        foreach ($nachuserid as $eintraege) {
            foreach ($eintraege as $eintrag) {
                $nummer = $eintrag['block'];
                if (array_key_exists($nummer, $blockidsnachnummer)) {
                    continue;
                }

                $vorhanden = block::get_record(['nummer' => $nummer]);
                if ($vorhanden) {
                    $blockidsnachnummer[$nummer] = (int) $vorhanden->get('id');
                    continue;
                }

                $neu = new block(0, (object) [
                    'nummer' => $nummer,
                    'name' => $nummer,
                    'ist_betrieb' => true,
                    'aktiv' => true,
                ]);
                $neu->create();
                $blockidsnachnummer[$nummer] = (int) $neu->get('id');
            }
        }

        $import = new plan_import(0, (object) [
            'quelle' => $quelle,
            'daten_hash' => $hash,
            'zeitpunkt' => time(),
            'ausgefuehrt_von' => $ausgefuehrtvon,
            'zeilen_gelesen' => $zeilengelesen,
            'personen_verarbeitet' => $personenverarbeitet,
            'zeilen_ausserhalb_geltungsbereich' => $ausserhalbgeltungsbereich,
            'einsaetze_erzeugt' => 0,
            'status' => $status,
            'protokoll' => implode("\n", $protokoll),
        ]);
        $import->create();
        $importid = (int) $import->get('id');

        $einsaetzeerzeugt = 0;
        foreach ($nachuserid as $userid => $eintraege) {
            // Eine Lieferung deckt einen Planungszeitraum ab, nicht die
            // ganze Lehrzeit. Ersetzt wird deshalb nur, was in diesem
            // Zeitraum liegt: wuerde der gesamte Bestand geloescht, naehme
            // die Lieferung fuers dritte Lehrjahr die Einsaetze der ersten
            // beiden mit - und die Lueckenanalyse rechnete anschliessend
            // mit einem Bruchteil der tatsaechlichen Ausbildung.
            $fenstervon = min(array_column($eintraege, 'von'));
            $fensterbis = max(array_column($eintraege, 'bis'));

            // Dieselbe Ueberschneidungslogik wie plan_service::get_einsaetze():
            // was in den Zeitraum hineinreicht, gehoert dazu. Fuer diesen
            // Zeitraum ist die Lieferung massgebend, auch wenn ein
            // bestehender Einsatz nur teilweise hineinragt - sonst blieben
            // an den Raendern zwei widersprechende Einsaetze nebeneinander
            // stehen.
            foreach (
                einsatz::get_records_select(
                    'userid = :userid AND bis >= :fenstervon AND von <= :fensterbis',
                    ['userid' => $userid, 'fenstervon' => $fenstervon, 'fensterbis' => $fensterbis]
                ) as $bestehend
            ) {
                $bestehend->delete();
            }

            foreach ($eintraege as $eintrag) {
                $neuereinsatz = new einsatz(0, (object) [
                    'userid' => $userid,
                    'blockid' => $blockidsnachnummer[$eintrag['block']],
                    'von' => $eintrag['von'],
                    'bis' => $eintrag['bis'],
                    'kw_von' => $eintrag['kw_von'],
                    'kw_bis' => $eintrag['kw_bis'],
                    'importid' => $importid,
                ]);
                $neuereinsatz->create();
                $einsaetzeerzeugt++;
            }
        }

        $import->set('einsaetze_erzeugt', $einsaetzeerzeugt);
        $import->update();

        $transaktion->allow_commit();

        return $einsaetzeerzeugt;
    }

    /**
     * Fuer die Faelle 'fehlgeschlagen' und 'abgewiesen': ein Protokolleintrag
     * ohne jede Einsatz-/Block-Aenderung, keine Transaktion noetig.
     *
     * @param string $quelle
     * @param string $hash
     * @param int $ausgefuehrtvon
     * @param int $zeilengelesen
     * @param int $personenverarbeitet
     * @param int $ausserhalbgeltungsbereich
     * @param int $einsaetzeerzeugt
     * @param string $status
     * @param string[] $protokoll
     */
    private function schreibe_protokoll(
        string $quelle,
        string $hash,
        int $ausgefuehrtvon,
        int $zeilengelesen,
        int $personenverarbeitet,
        int $ausserhalbgeltungsbereich,
        int $einsaetzeerzeugt,
        string $status,
        array $protokoll
    ): void {
        $eintrag = new plan_import(0, (object) [
            'quelle' => $quelle,
            'daten_hash' => $hash,
            'zeitpunkt' => time(),
            'ausgefuehrt_von' => $ausgefuehrtvon,
            'zeilen_gelesen' => $zeilengelesen,
            'personen_verarbeitet' => $personenverarbeitet,
            'zeilen_ausserhalb_geltungsbereich' => $ausserhalbgeltungsbereich,
            'einsaetze_erzeugt' => $einsaetzeerzeugt,
            'status' => $status,
            'protokoll' => implode("\n", $protokoll),
        ]);
        $eintrag->create();
    }

    /**
     * Anzahl und juengste Aenderung der zum jetzigen Zeitpunkt aktiven
     * Zuordnungen - dieselbe Stichtag-Bedingung wie api::get_berufsbildner_for().
     * Kein voller Abgleich, nur ein billiger Fingerabdruck, der sich aendert,
     * sobald eine Zuordnung dazukommt, endet oder verschoben wird.
     */
    private function zuordnungs_fingerabdruck(): string {
        global $DB;

        $jetzt = time();
        $zeile = $DB->get_record_sql(
            'SELECT COUNT(*) AS anzahl, COALESCE(MAX(timemodified), 0) AS letzteaenderung
               FROM {' . zuordnung::TABLE . '}
              WHERE gueltig_von <= :stichtag1
                AND (gueltig_bis IS NULL OR gueltig_bis >= :stichtag2)',
            ['stichtag1' => $jetzt, 'stichtag2' => $jetzt]
        );

        return $zeile->anzahl . ':' . $zeile->letzteaenderung;
    }

    /**
     * Letzter Import, der durchgelaufen ist.
     *
     * @return plan_import|null
     */
    private function letzter_erfolgreicher_import(): ?plan_import {
        $treffer = plan_import::get_records_select(
            "status IN ('ok', 'mit_warnungen')",
            [],
            'zeitpunkt DESC',
            '*',
            0,
            1
        );

        return !empty($treffer) ? reset($treffer) : null;
    }

    /**
     * Schwelle, ab der eine Lieferung als zu klein gilt.
     *
     * @return int
     */
    private function schwelle_prozent(): int {
        $wert = get_config('local_berufsbildung', 'versetzungsplan_schwelle_prozent');
        return min(100, max(0, $wert !== false ? (int) $wert : 20));
    }

    /**
     * Baut das Ergebnis eines Importlaufs.
     *
     * @param string $status
     * @param bool $unveraendert
     * @param int $zeilengelesen
     * @param int $personenverarbeitet
     * @param int $ausserhalbgeltungsbereich
     * @param int $einsaetzeerzeugt
     * @param string[] $protokoll
     * @return array
     */
    private function ergebnis(
        string $status,
        bool $unveraendert,
        int $zeilengelesen,
        int $personenverarbeitet,
        int $ausserhalbgeltungsbereich,
        int $einsaetzeerzeugt,
        array $protokoll
    ): array {
        return [
            'status' => $status,
            'unveraendert' => $unveraendert,
            'zeilen_gelesen' => $zeilengelesen,
            'personen_verarbeitet' => $personenverarbeitet,
            'zeilen_ausserhalb_geltungsbereich' => $ausserhalbgeltungsbereich,
            'einsaetze_erzeugt' => $einsaetzeerzeugt,
            'protokoll' => $protokoll,
        ];
    }
}

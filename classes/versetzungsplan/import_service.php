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
use local_berufsbildung\persistent\einsatz_aenderung;
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
 *    beim letzten erfolgreichen Lauf -> zurueckgehalten und der
 *    Administration zur Freigabe gemeldet, ausser bestaetigt. Bis zur
 *    Entscheidung bleibt der Einsatzbestand unangetastet.
 * 5. Testlauf endet hier, ohne zu schreiben. Sonst: pro verarbeiteter
 *    Person die bestehenden Einsaetze im Zeitraum der Lieferung ersetzen,
 *    in einer Transaktion, und festhalten, welche davon entfallen und welche
 *    neu sind (einsatz_aenderung). Die Aenderungen gehen in jedem Fall,
 *    auch beim Testlauf und beim Zurueckhalten, mit dem Ergebnis zurueck.
 *
 * Es wartet hoechstens eine Lieferung auf Freigabe: jede neuere, die
 * verarbeitet oder ihrerseits zurueckgehalten wird, ueberholt sie. Sonst
 * koennte eine spaete Freigabe einen inzwischen uebernommenen, neueren Plan
 * mit einem aelteren ueberschreiben.
 */
class import_service {
    /** Name des Nachrichtentyps aus db/messages.php. */
    public const NACHRICHT_FREIGABE = 'versetzungsplan_freigabe';

    /** Capability fuer die Freigabe zurueckgehaltener Lieferungen. */
    public const CAPABILITY_FREIGABE = 'local/berufsbildung:approveplan';

    /** Wie viele Monate geaenderte Einsaetze und Protokolltext im Verlauf hoechstens alt werden. */
    public const VERLAUF_MONATE = 12;

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
     *               zeilen_ausserhalb_geltungsbereich: int, einsaetze_erzeugt: int, protokoll: string[],
     *               aenderungen: array[]} aenderungen siehe aenderungen_liste()
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
        // Vor dem Schreiben: danach ist der bisherige Bestand ersetzt.
        $aenderungen = $this->aenderungen_liste(
            $this->vergleiche_einsaetze($aufgeloest['nachuserid']),
            $aufgeloest['nachuserid']
        );

        if (!$testlauf && $letztererfolgreicher !== null && $letztererfolgreicher->get('personen_verarbeitet') > 0) {
            $schwelle = $this->schwelle_prozent();
            $mindestanzahl = (int) ceil($letztererfolgreicher->get('personen_verarbeitet') * (1 - $schwelle / 100));

            if ($personenverarbeitet < $mindestanzahl && !$rueckgangbestaetigt) {
                $hinweis = sprintf(
                    'Lieferung zurückgehalten: nur %d von zuvor %d verarbeiteten Personen (Schwelle %d%%). '
                        . 'Sie wartet auf die Freigabe durch die Administration.',
                    $personenverarbeitet,
                    $letztererfolgreicher->get('personen_verarbeitet'),
                    $schwelle
                );
                $protokoll = array_merge([$hinweis], $protokoll);

                // Das Skript liefert woechentlich, auch wenn sich nichts
                // geaendert hat. Dieselbe Lieferung soll die Administration
                // nicht jede Woche erneut melden.
                if (plan_import::get_record(['status' => 'zur_freigabe', 'daten_hash' => $hash]) === false) {
                    $this->ueberhole_wartende();
                    $wartend = $this->schreibe_protokoll(
                        $quelle,
                        $hash,
                        $ausgefuehrtvon,
                        $geparst['zeilen_gelesen'],
                        $personenverarbeitet,
                        $aufgeloest['ausserhalb_geltungsbereich'],
                        0,
                        'zur_freigabe',
                        $protokoll,
                        $csvinhalt
                    );
                    $this->benachrichtige_freigabe(
                        $wartend,
                        $ausgefuehrtvon,
                        (int) $letztererfolgreicher->get('personen_verarbeitet')
                    );
                }

                return $this->ergebnis(
                    'zur_freigabe',
                    false,
                    $geparst['zeilen_gelesen'],
                    $personenverarbeitet,
                    $aufgeloest['ausserhalb_geltungsbereich'],
                    0,
                    $protokoll,
                    $aenderungen
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
                $protokoll,
                $aenderungen
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
            $protokoll,
            $aenderungen
        );

        return $this->ergebnis(
            $status,
            false,
            $geparst['zeilen_gelesen'],
            $personenverarbeitet,
            $aufgeloest['ausserhalb_geltungsbereich'],
            $einsaetzeerzeugt,
            $protokoll,
            $aenderungen
        );
    }

    /**
     * Uebernimmt eine zurueckgehaltene Lieferung: die Administration hat
     * den Rueckgang geprueft und bestaetigt ihn damit.
     *
     * Verarbeitet wird mit dem heutigen Zuordnungsstand, nicht mit dem vom
     * Zeitpunkt der Lieferung - genau wie bei einer neuen Lieferung. Scheitert
     * die Verarbeitung, bleibt die Lieferung offen.
     *
     * @param int $importid ID der wartenden Lieferung
     * @param int $entschiedenvon userid der freigebenden Person
     * @return array Ergebnis wie verarbeiten()
     * @throws \moodle_exception wenn die Lieferung nicht (mehr) auf Freigabe wartet
     */
    public function freigeben(int $importid, int $entschiedenvon): array {
        global $DB;

        $wartend = $this->wartende_lieferung($importid);
        $csvinhalt = (string) $wartend->get('csvdaten');

        $transaktion = $DB->start_delegated_transaction();
        try {
            $this->entscheide($wartend, 'freigegeben', $entschiedenvon);
            $ergebnis = $this->verarbeiten($csvinhalt, (string) $wartend->get('quelle'), $entschiedenvon, false, true);
            if ($ergebnis['status'] === 'fehlgeschlagen') {
                throw new \moodle_exception(
                    'planfreigabe:fehlgeschlagen',
                    'local_berufsbildung',
                    '',
                    implode('; ', $ergebnis['protokoll'])
                );
            }
        } catch (\Throwable $e) {
            // Rollt auch die Entscheidung zurueck und wirft weiter.
            $transaktion->rollback($e);
        }
        $transaktion->allow_commit();

        return $ergebnis;
    }

    /**
     * Verwirft eine zurueckgehaltene Lieferung. Der Einsatzbestand bleibt,
     * wie er ist.
     *
     * @param int $importid ID der wartenden Lieferung
     * @param int $entschiedenvon userid der verwerfenden Person
     * @throws \moodle_exception wenn die Lieferung nicht (mehr) auf Freigabe wartet
     */
    public function verwerfen(int $importid, int $entschiedenvon): void {
        $this->entscheide($this->wartende_lieferung($importid), 'verworfen', $entschiedenvon);
    }

    /**
     * Was die Administration fuer die Entscheidung braucht: was die
     * Lieferung heute bewirken wuerde und wer darin fehlt, verglichen mit dem
     * letzten erfolgreichen Import. Schreibt nichts.
     *
     * "Fehlend" heisst: beim letzten erfolgreichen Import verarbeitet, jetzt
     * nicht mehr - etwa nach einem Lehrabschluss oder weil das Skript eine
     * unvollstaendige Datei erzeugt hat. Deren Einsaetze bleiben auch bei
     * einer Freigabe erhalten, sie werden nur nicht mehr nachgefuehrt.
     *
     * "Aenderungen" sind je gelieferter Person die Einsaetze, die bei einer
     * Freigabe wegfallen oder neu dazukommen, siehe vergleiche_einsaetze().
     *
     * @param plan_import $wartend
     * @return array{personen_verarbeitet: int, einsaetze_erzeugt: int, fehlende_personen: int[],
     *               aenderungen: array<int, array{entfaellt: array[], neu: array[]}>, personen_unveraendert: int}
     */
    public function pruefe_wartende(plan_import $wartend): array {
        $geparst = $this->parser->parsen((string) $wartend->get('csvdaten'));
        $aufgeloest = $this->zeilen_aufloesen($geparst['eintraege']);

        $bisher = [];
        $letztererfolgreicher = $this->letzter_erfolgreicher_import();
        if ($letztererfolgreicher !== null) {
            foreach (einsatz::get_records(['importid' => (int) $letztererfolgreicher->get('id')]) as $einsatz) {
                $bisher[(int) $einsatz->get('userid')] = true;
            }
        }

        $aenderungen = $this->vergleiche_einsaetze($aufgeloest['nachuserid']);

        return [
            'personen_verarbeitet' => count($aufgeloest['nachuserid']),
            'einsaetze_erzeugt' => $this->zaehle_eintraege($aufgeloest['nachuserid']),
            'fehlende_personen' => array_values(array_diff(array_keys($bisher), array_keys($aufgeloest['nachuserid']))),
            'aenderungen' => $aenderungen,
            'personen_unveraendert' => count($aufgeloest['nachuserid']) - count($aenderungen),
        ];
    }

    /**
     * Was eine Lieferung je Person am Einsatzbestand aendern wuerde: genau
     * die Einsaetze, die schreiben() ersetzt, gegen die gelieferten. Gleich
     * ist ein Einsatz mit demselben Block und denselben Kalenderwochen; ein
     * verschobener erscheint deshalb als entfallen und neu. Die Bemerkung
     * wird nicht gespeichert und zaehlt nicht.
     *
     * @param array $nachuserid Struktur: array<int,array>
     * @return array<int, array{entfaellt: array[], neu: array[]}> nur Personen mit
     *  mindestens einer Aenderung; je Einsatz ['block' => string, 'kw_von' => string,
     *  'kw_bis' => string, 'von' => int], nach Beginn sortiert
     */
    private function vergleiche_einsaetze(array $nachuserid): array {
        $blocknummern = [];
        $aenderungen = [];

        foreach ($nachuserid as $userid => $eintraege) {
            $bestehend = [];
            foreach ($this->bestehende_im_zeitraum((int) $userid, $eintraege) as $einsatz) {
                $blockid = (int) $einsatz->get('blockid');
                if (!array_key_exists($blockid, $blocknummern)) {
                    $block = block::get_record(['id' => $blockid]);
                    $blocknummern[$blockid] = $block ? (string) $block->get('nummer') : (string) $blockid;
                }
                $eintrag = [
                    'block' => $blocknummern[$blockid],
                    'kw_von' => (string) $einsatz->get('kw_von'),
                    'kw_bis' => (string) $einsatz->get('kw_bis'),
                    'von' => (int) $einsatz->get('von'),
                ];
                $bestehend[$this->einsatz_schluessel($eintrag)] = $eintrag;
            }

            $geliefert = [];
            foreach ($eintraege as $eintrag) {
                $eintrag = [
                    'block' => (string) $eintrag['block'],
                    'kw_von' => (string) $eintrag['kw_von'],
                    'kw_bis' => (string) $eintrag['kw_bis'],
                    'von' => (int) $eintrag['von'],
                ];
                $geliefert[$this->einsatz_schluessel($eintrag)] = $eintrag;
            }

            $entfaellt = array_values(array_diff_key($bestehend, $geliefert));
            $neu = array_values(array_diff_key($geliefert, $bestehend));
            if (empty($entfaellt) && empty($neu)) {
                continue;
            }

            $nachbeginn = static fn (array $a, array $b): int => $a['von'] <=> $b['von'];
            usort($entfaellt, $nachbeginn);
            usort($neu, $nachbeginn);
            $aenderungen[(int) $userid] = ['entfaellt' => $entfaellt, 'neu' => $neu];
        }

        return $aenderungen;
    }

    /**
     * Vergleichsschluessel eines Einsatzes fuer vergleiche_einsaetze().
     *
     * @param array $eintrag
     * @return string
     */
    private function einsatz_schluessel(array $eintrag): string {
        return $eintrag['block'] . "\x00" . $eintrag['kw_von'] . "\x00" . $eintrag['kw_bis'];
    }

    /**
     * Die Aenderungen aus vergleiche_einsaetze() als flache Liste, so wie
     * der Webservice sie zurueckgibt und einsatz_aenderung sie speichert: je
     * Person zuerst, was entfaellt, dann, was neu ist. Die Mailadresse ist
     * die gelieferte, damit das Skript die Person ohne Moodle-ID erkennt.
     *
     * @param array $aenderungen Ergebnis von vergleiche_einsaetze()
     * @param array $nachuserid Struktur: array<int,array>
     * @return array[] je Einsatz ['userid' => int, 'email' => string, 'art' => 'entfaellt'|'neu',
     *  'block' => string, 'kw_von' => string, 'kw_bis' => string, 'von' => int]
     */
    private function aenderungen_liste(array $aenderungen, array $nachuserid): array {
        $liste = [];
        foreach ($aenderungen as $userid => $jeart) {
            foreach ([einsatz_aenderung::ENTFAELLT, einsatz_aenderung::NEU] as $art) {
                foreach ($jeart[$art] as $einsatz) {
                    $liste[] = [
                        'userid' => (int) $userid,
                        'email' => (string) $nachuserid[$userid][0]['email'],
                        'art' => $art,
                    ] + $einsatz;
                }
            }
        }

        return $liste;
    }

    /**
     * Was ein Importlauf an den Einsaetzen geaendert hat, in derselben Form
     * wie im Ergebnis von verarbeiten(), aber ohne Mailadresse: die steht nur
     * in der Lieferung, nicht im Protokoll.
     *
     * @param int $importid
     * @return array[] je Einsatz ['userid' => int, 'art' => 'entfaellt'|'neu', 'block' => string,
     *  'kw_von' => string, 'kw_bis' => string, 'von' => int], nach Person, Art und Beginn
     */
    public function aenderungen_fuer_import(int $importid): array {
        return array_map(
            static fn (einsatz_aenderung $aenderung): array => [
                'userid' => (int) $aenderung->get('userid'),
                'art' => (string) $aenderung->get('art'),
                'block' => (string) $aenderung->get('block'),
                'kw_von' => (string) $aenderung->get('kw_von'),
                'kw_bis' => (string) $aenderung->get('kw_bis'),
                'von' => (int) $aenderung->get('von'),
            ],
            array_values(einsatz_aenderung::get_records(['importid' => $importid], 'userid, art, von'))
        );
    }

    /**
     * Raeumt den Verlauf auf, was aelter als VERLAUF_MONATE ist: loescht die
     * geaenderten Einsaetze und leert den Protokolltext der Laeufe - er kann
     * nicht zugeordnete Mailadressen enthalten. Die Laeufe selbst bleiben,
     * die Einsaetze verweisen auf sie, und ihre Zahlen tragen keinen
     * Personenbezug. Eine wartende Lieferung behaelt ihr Protokoll, es wird
     * fuer die Entscheidung gebraucht.
     *
     * "Hoechstens ein Jahr alt": was genau an der Grenze liegt, bleibt.
     * Aufgerufen taeglich von task\bereinige_planverlauf.
     *
     * @param int|null $stichtag Timestamp, null bedeutet "jetzt"
     * @return array{aenderungen: int, protokolle: int} Anzahl geloeschter Aenderungen
     *  und geleerter Protokolle
     */
    public function bereinige_verlauf(?int $stichtag = null): array {
        global $DB;

        $grenze = strtotime('-' . self::VERLAUF_MONATE . ' months', $stichtag ?? time());

        $bedingung = 'timecreated < :grenze';
        $aenderungen = $DB->count_records_select(einsatz_aenderung::TABLE, $bedingung, ['grenze' => $grenze]);
        $DB->delete_records_select(einsatz_aenderung::TABLE, $bedingung, ['grenze' => $grenze]);

        $bedingung = 'zeitpunkt < :grenze AND status <> :wartend AND '
            . $DB->sql_isnotempty(plan_import::TABLE, 'protokoll', true, true);
        $parameter = ['grenze' => $grenze, 'wartend' => 'zur_freigabe'];
        $protokolle = $DB->count_records_select(plan_import::TABLE, $bedingung, $parameter);
        $DB->set_field_select(plan_import::TABLE, 'protokoll', '', $bedingung, $parameter);

        return ['aenderungen' => $aenderungen, 'protokolle' => $protokolle];
    }

    /**
     * Die bestehenden Einsaetze einer Person, die eine Lieferung ersetzt.
     *
     * Eine Lieferung deckt einen Planungszeitraum ab, nicht die ganze
     * Lehrzeit. Ersetzt wird deshalb nur, was in diesem Zeitraum liegt:
     * wuerde der gesamte Bestand geloescht, naehme die Lieferung fuers dritte
     * Lehrjahr die Einsaetze der ersten beiden mit - und die Lueckenanalyse
     * rechnete anschliessend mit einem Bruchteil der tatsaechlichen
     * Ausbildung.
     *
     * Dieselbe Ueberschneidungslogik wie plan_service::get_einsaetze(): was
     * in den Zeitraum hineinreicht, gehoert dazu. Fuer diesen Zeitraum ist
     * die Lieferung massgebend, auch wenn ein bestehender Einsatz nur
     * teilweise hineinragt - sonst blieben an den Raendern zwei
     * widersprechende Einsaetze nebeneinander stehen.
     *
     * @param int $userid
     * @param array $eintraege die gelieferten Eintraege dieser Person
     * @return einsatz[]
     */
    private function bestehende_im_zeitraum(int $userid, array $eintraege): array {
        return einsatz::get_records_select(
            'userid = :userid AND bis >= :fenstervon AND von <= :fensterbis',
            [
                'userid' => $userid,
                'fenstervon' => min(array_column($eintraege, 'von')),
                'fensterbis' => max(array_column($eintraege, 'bis')),
            ]
        );
    }

    /**
     * Die wartende Lieferung mit dieser ID.
     *
     * @param int $importid
     * @return plan_import
     * @throws \moodle_exception wenn sie nicht (mehr) auf Freigabe wartet,
     *  etwa weil eine andere Person schneller entschieden hat
     */
    private function wartende_lieferung(int $importid): plan_import {
        $wartend = plan_import::get_record(['id' => $importid, 'status' => 'zur_freigabe']);
        if ($wartend === false) {
            throw new \moodle_exception('planfreigabe:nicht_mehr_offen', 'local_berufsbildung');
        }

        return $wartend;
    }

    /**
     * Haelt die Entscheidung fest. Die gespeicherte Lieferung wird danach
     * nicht mehr gebraucht und geloescht - sie enthaelt die Mailadressen
     * aller Lernenden, auch derer ohne Zuordnung.
     *
     * @param plan_import $wartend
     * @param string $status 'freigegeben' | 'verworfen' | 'ueberholt'
     * @param int|null $entschiedenvon null, wenn eine neuere Lieferung sie ueberholt hat
     */
    private function entscheide(plan_import $wartend, string $status, ?int $entschiedenvon): void {
        $wartend->set('status', $status);
        $wartend->set('csvdaten', null);
        $wartend->set('entschieden_von', $entschiedenvon);
        $wartend->set('entschieden_am', time());
        $wartend->update();
    }

    /**
     * Fuer die Loeschanfrage einer Person (privacy\provider): eine wartende
     * Lieferung, die ihre Mailadresse enthaelt, wird verworfen. Die naechste
     * Lieferung des Skripts kommt ohnehin.
     *
     * @param string $email
     */
    public function verwerfe_wartende_mit_mailadresse(string $email): void {
        foreach (plan_import::get_records(['status' => 'zur_freigabe']) as $wartend) {
            if (stripos((string) $wartend->get('csvdaten'), $email) !== false) {
                $this->entscheide($wartend, 'verworfen', null);
            }
        }
    }

    /**
     * Eine neuere Lieferung ueberholt die wartende.
     */
    private function ueberhole_wartende(): void {
        foreach (plan_import::get_records(['status' => 'zur_freigabe']) as $wartend) {
            $this->entscheide($wartend, 'ueberholt', null);
        }
    }

    /**
     * Meldet eine zurueckgehaltene Lieferung allen, die sie freigeben
     * duerfen. Wer sie selbst von Hand hochgeladen hat, sieht das Ergebnis
     * ohnehin auf der Seite.
     *
     * @param plan_import $wartend
     * @param int $ausgefuehrtvon
     * @param int $personenvorher Personen beim letzten erfolgreichen Import
     */
    private function benachrichtige_freigabe(plan_import $wartend, int $ausgefuehrtvon, int $personenvorher): void {
        // Site-Admins haben jede Capability, tauchen in
        // get_users_by_capability() aber nur mit passender Rolle auf.
        $empfaenger = get_users_by_capability(\context_system::instance(), self::CAPABILITY_FREIGABE);
        foreach (get_admins() as $admin) {
            $empfaenger[(int) $admin->id] = $admin;
        }
        unset($empfaenger[$ausgefuehrtvon]);

        $url = new \moodle_url('/local/berufsbildung/plan_freigabe.php');
        $a = (object) [
            'personen' => (int) $wartend->get('personen_verarbeitet'),
            'vorher' => $personenvorher,
            'quelle' => (string) $wartend->get('quelle'),
            'zeitpunkt' => userdate((int) $wartend->get('zeitpunkt')),
            'url' => $url->out(false),
        ];

        foreach ($empfaenger as $person) {
            $nachricht = new \core\message\message();
            $nachricht->component = 'local_berufsbildung';
            $nachricht->name = self::NACHRICHT_FREIGABE;
            $nachricht->userfrom = \core_user::get_noreply_user();
            $nachricht->userto = $person;
            $nachricht->subject = get_string('planfreigabe:nachricht_betreff', 'local_berufsbildung');
            $nachricht->fullmessage = get_string('planfreigabe:nachricht_text', 'local_berufsbildung', $a);
            $nachricht->fullmessageformat = FORMAT_PLAIN;
            $nachricht->fullmessagehtml = '';
            $nachricht->smallmessage = get_string('planfreigabe:nachricht_kurz', 'local_berufsbildung', $a);
            $nachricht->notification = 1;
            $nachricht->contexturl = $url->out(false);
            $nachricht->contexturlname = get_string('planfreigabe:titel', 'local_berufsbildung');
            message_send($nachricht);
        }
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
        $gemeldeteadressen = [];

        foreach ($eintraege as $eintrag) {
            $nutzer = $DB->get_records('user', ['email' => $eintrag['email'], 'deleted' => 0], '', 'id');
            if (count($nutzer) !== 1) {
                // Eine Person hat meist mehrere Einsaetze - die Adresse
                // trotzdem nur einmal melden.
                if (!isset($gemeldeteadressen[$eintrag['email']])) {
                    $gemeldeteadressen[$eintrag['email']] = true;
                    $protokoll[] = "Mailadresse {$eintrag['email']} keinem Moodle-Konto zugeordnet – übersprungen";
                }
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
     * Importprotokoll samt den Aenderungen - alles in einer Transaktion,
     * damit ein Fehler mittendrin den bestehenden Datenbestand nicht
     * antastet (Architekturregel 5).
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
     * @param array[] $aenderungen siehe aenderungen_liste(), vor dem Ersetzen ermittelt
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
        array $protokoll,
        array $aenderungen
    ): int {
        global $DB;

        $transaktion = $DB->start_delegated_transaction();

        $this->ueberhole_wartende();

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

        foreach ($aenderungen as $aenderung) {
            (new einsatz_aenderung(0, (object) [
                'importid' => $importid,
                'userid' => $aenderung['userid'],
                'art' => $aenderung['art'],
                'block' => $aenderung['block'],
                'kw_von' => $aenderung['kw_von'],
                'kw_bis' => $aenderung['kw_bis'],
                'von' => $aenderung['von'],
            ]))->create();
        }

        $einsaetzeerzeugt = 0;
        foreach ($nachuserid as $userid => $eintraege) {
            // Nur der gelieferte Zeitraum, siehe bestehende_im_zeitraum() -
            // dieselbe Auswahl, die die Freigabe-Seite als "entfaellt" zeigt.
            foreach ($this->bestehende_im_zeitraum((int) $userid, $eintraege) as $bestehend) {
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
     * Fuer die Faelle 'fehlgeschlagen' und 'zur_freigabe': ein
     * Protokolleintrag ohne jede Einsatz-/Block-Aenderung, keine Transaktion
     * noetig.
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
     * @param string|null $csvinhalt nur bei 'zur_freigabe': die Lieferung, bis entschieden ist
     * @return plan_import
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
        array $protokoll,
        ?string $csvinhalt = null
    ): plan_import {
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
            'csvdaten' => $csvinhalt,
        ]);
        $eintrag->create();

        return $eintrag;
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
    public function letzter_erfolgreicher_import(): ?plan_import {
        // Die ID entscheidet, wenn zwei Laeufe in dieselbe Sekunde fallen -
        // etwa eine Freigabe direkt nach der vorherigen Lieferung.
        $treffer = plan_import::get_records_select(
            "status IN ('ok', 'mit_warnungen')",
            [],
            'zeitpunkt DESC, id DESC',
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
     * @param array[] $aenderungen siehe aenderungen_liste()
     * @return array
     */
    private function ergebnis(
        string $status,
        bool $unveraendert,
        int $zeilengelesen,
        int $personenverarbeitet,
        int $ausserhalbgeltungsbereich,
        int $einsaetzeerzeugt,
        array $protokoll,
        array $aenderungen = []
    ): array {
        return [
            'status' => $status,
            'unveraendert' => $unveraendert,
            'zeilen_gelesen' => $zeilengelesen,
            'personen_verarbeitet' => $personenverarbeitet,
            'zeilen_ausserhalb_geltungsbereich' => $ausserhalbgeltungsbereich,
            'einsaetze_erzeugt' => $einsaetzeerzeugt,
            'protokoll' => $protokoll,
            'aenderungen' => $aenderungen,
        ];
    }
}

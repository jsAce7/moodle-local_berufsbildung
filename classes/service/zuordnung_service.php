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
 * Schreiboperationen fuer Zuordnungen.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\service;

use local_berufsbildung\navigation\sichtbarkeit;
use local_berufsbildung\persistent\kohorten_link;
use local_berufsbildung\persistent\zuordnung;

/**
 * Stellt widerspruchsfreie Zeitraeume je lernender Person und Rolle sicher.
 */
class zuordnung_service {
    /**
     * Liesse sich diese Zuordnung anlegen? Dieselbe Pruefung wie in
     * anlegen(), ohne zu schreiben - fuer die Vorschau des CSV-Imports.
     *
     * @param int $berufsbildnerid
     * @param int $lernendeid
     * @param int $gueltigvon
     * @param string $rolle
     * @return string|null Schluessel des Sprachstrings, null = in Ordnung
     *     (auch, wenn dieselbe Zuordnung schon besteht)
     */
    public function pruefe(int $berufsbildnerid, int $lernendeid, int $gueltigvon, string $rolle): ?string {
        return $this->bewerte($berufsbildnerid, $lernendeid, $gueltigvon, $rolle)['fehler'];
    }

    /**
     * Gemeinsame Pruefung von pruefe() und anlegen().
     *
     * @param int $berufsbildnerid
     * @param int $lernendeid
     * @param int $gueltigvon
     * @param string $rolle
     * @return array{fehler: ?string, identisch: ?zuordnung, ueberschneidend: zuordnung[]}
     */
    private function bewerte(int $berufsbildnerid, int $lernendeid, int $gueltigvon, string $rolle): array {
        $ergebnis = ['fehler' => null, 'identisch' => null, 'ueberschneidend' => []];

        if ($berufsbildnerid === $lernendeid) {
            $ergebnis['fehler'] = 'zuordnung:fehler_gleiche_person';
            return $ergebnis;
        }

        $ueberschneidend = zuordnung::get_records_select(
            'lernendeid = :lernendeid AND rolle = :rolle
             AND (gueltig_bis IS NULL OR gueltig_bis >= :gueltigvon)',
            ['lernendeid' => $lernendeid, 'rolle' => $rolle, 'gueltigvon' => $gueltigvon]
        );
        foreach ($ueberschneidend as $einzelne) {
            $beginn = (int) $einzelne->get('gueltig_von');
            if ($beginn > $gueltigvon) {
                $ergebnis['fehler'] = 'zuordnung:fehler_ueberschneidung';
                return $ergebnis;
            }
            if ($beginn === $gueltigvon) {
                // Dieselbe Zuordnung noch einmal (etwa ein erneut hochgeladener
                // CSV-Import): nichts zu tun. Eine andere Person zum selben
                // Beginn liesse sich nur mit einem leeren Zeitraum
                // (gueltig_bis vor gueltig_von) verdraengen - abgelehnt.
                if ((int) $einzelne->get('berufsbildnerid') === $berufsbildnerid) {
                    $ergebnis['identisch'] = $einzelne;
                } else {
                    $ergebnis['fehler'] = 'zuordnung:fehler_gleicher_beginn';
                }
                return $ergebnis;
            }
        }

        $ergebnis['ueberschneidend'] = $ueberschneidend;
        return $ergebnis;
    }

    /**
     * Legt eine Zuordnung an und beendet dabei nur einen vorherigen Zeitraum derselben Rolle.
     *
     * Besteht dieselbe Zuordnung (Person, Rolle, Beginn) bereits, bleibt sie
     * unveraendert und wird zurueckgegeben.
     *
     * @param int $berufsbildnerid
     * @param int $lernendeid
     * @param string $beruf
     * @param int $gueltigvon
     * @param string $rolle
     * @param int|null $kohortenlinkid
     * @return zuordnung
     * @throws \moodle_exception bei Ueberschneidung, gleichem Beginn mit anderer Person oder gleicher Person
     */
    public function anlegen(
        int $berufsbildnerid,
        int $lernendeid,
        string $beruf,
        int $gueltigvon,
        string $rolle,
        ?int $kohortenlinkid
    ): zuordnung {
        global $DB;

        $bewertung = $this->bewerte($berufsbildnerid, $lernendeid, $gueltigvon, $rolle);
        if ($bewertung['fehler'] !== null) {
            throw new \moodle_exception($bewertung['fehler'], 'local_berufsbildung');
        }
        if ($bewertung['identisch'] !== null) {
            return $bewertung['identisch'];
        }
        $ueberschneidend = $bewertung['ueberschneidend'];

        $transaktion = $DB->start_delegated_transaction();
        $verdraengte = [];
        foreach ($ueberschneidend as $einzelne) {
            $einzelne->set('gueltig_bis', $gueltigvon - 1);
            $einzelne->update();
            $verdraengte[(int) $einzelne->get('berufsbildnerid')] = true;
        }
        $neue = new zuordnung(0, (object) [
            'berufsbildnerid' => $berufsbildnerid,
            'lernendeid' => $lernendeid,
            'beruf' => $beruf,
            'rolle' => $rolle,
            'gueltig_von' => $gueltigvon,
            'gueltig_bis' => null,
            'kohorten_link_id' => $kohortenlinkid,
        ]);
        $neue->create();
        $transaktion->allow_commit();
        sichtbarkeit::vergiss();

        $rollensync = new role_sync_service();
        $rollensync->synchronisiere_paar($berufsbildnerid, $lernendeid);
        // War die beendete Zuordnung die letzte laufende der verdraengten
        // Person, faellt ihre Planungsrolle sofort weg und nicht erst beim
        // naechsten Task-Lauf - Begruendung wie in beenden().
        unset($verdraengte[$berufsbildnerid]);
        foreach (array_keys($verdraengte) as $verdraengtid) {
            $rollensync->synchronisiere_planungsrolle($verdraengtid);
        }
        return $neue;
    }

    /**
     * Setzt ein Enddatum oder oeffnet einen zuvor beendeten Zeitraum wieder.
     *
     * @param int $zuordnungid
     * @param int|null $gueltigbis
     */
    public function beenden(int $zuordnungid, ?int $gueltigbis): void {
        $zuordnung = new zuordnung($zuordnungid);
        if ($gueltigbis !== null && $gueltigbis < (int) $zuordnung->get('gueltig_von')) {
            throw new \moodle_exception('zuordnung:fehler_enddatum', 'local_berufsbildung');
        }
        // Nur pruefen, wenn der Zeitraum waechst (Wiederoeffnen oder spaeteres
        // Ende): ein frueheres Ende kann keine Ueberschneidung erzeugen, und
        // ein normales Beenden kommt so ohne die Abfrage aus.
        $bisher = $zuordnung->get('gueltig_bis');
        $waechst = $bisher !== null && ($gueltigbis === null || $gueltigbis > (int) $bisher);
        if ($waechst) {
            $select = 'id <> :id AND lernendeid = :lernendeid AND rolle = :rolle
                       AND (gueltig_bis IS NULL OR gueltig_bis >= :gueltigvon)';
            $params = [
                'id' => $zuordnungid,
                'lernendeid' => $zuordnung->get('lernendeid'),
                'rolle' => $zuordnung->get('rolle'),
                'gueltigvon' => $zuordnung->get('gueltig_von'),
            ];
            if ($gueltigbis !== null) {
                $select .= ' AND gueltig_von <= :gueltigbis';
                $params['gueltigbis'] = $gueltigbis;
            }
            if (zuordnung::record_exists_select($select, $params)) {
                throw new \moodle_exception('zuordnung:fehler_ueberschneidung', 'local_berufsbildung');
            }
        }
        $zuordnung->set('gueltig_bis', $gueltigbis);
        $zuordnung->update();
        sichtbarkeit::vergiss();

        // Auch beim Beenden abgleichen, nicht nur beim Anlegen und Loeschen:
        // sonst behaelt eine Person die systemweite Planungsrolle bis zum
        // naechsten stuendlichen Task-Lauf. Das Wiederoeffnen (gueltig_bis =
        // null) setzt sie ueber denselben Aufruf zurueck.
        (new role_sync_service())->synchronisiere_paar(
            (int) $zuordnung->get('berufsbildnerid'),
            (int) $zuordnung->get('lernendeid')
        );
    }

    /**
     * Loescht eine nachweislich falsch erfasste Zuordnung endgueltig.
     *
     * @param int $zuordnungid
     */
    public function loeschen(int $zuordnungid): void {
        $zuordnung = new zuordnung($zuordnungid);
        $berufsbildnerid = (int) $zuordnung->get('berufsbildnerid');
        $lernendeid = (int) $zuordnung->get('lernendeid');
        $zuordnung->delete();
        sichtbarkeit::vergiss();
        (new role_sync_service())->synchronisiere_paar($berufsbildnerid, $lernendeid);
    }

    /**
     * Beendet die Zustaendigkeit einer Berufsbildner/in, deren Konto geloescht
     * wurde: laufende Zuordnungen enden jetzt, noch nicht begonnene werden
     * geloescht (sie waren nie wirksam), Kohorten-Links werden deaktiviert,
     * damit der Kohorten-Abgleich keine neuen Zuordnungen anlegt.
     *
     * Beendet statt geloescht, weil eine vergangene Zustaendigkeit Teil der
     * Ausbildungshistorie der lernenden Person ist (Architekturregel 2); sie
     * verschwindet mit deren Aufbewahrungsfrist.
     *
     * Bewusst ohne beenden()/loeschen() und deren Rollenabgleich: delete_user()
     * hat die Rollen des Kontos bereits entfernt, und role_assign() lehnt ein
     * geloeschtes Konto ab. Das Ende liegt eine Sekunde vor jetzt, weil
     * gueltig_bis den letzten gueltigen Zeitpunkt nennt.
     *
     * @param int $berufsbildnerid
     * @return int Anzahl beendeter oder geloeschter Zuordnungen
     */
    public function beende_fuer_geloeschtes_konto(int $berufsbildnerid): int {
        $jetzt = time();
        $anzahl = 0;

        $offen = zuordnung::get_records_select(
            'berufsbildnerid = :berufsbildnerid AND (gueltig_bis IS NULL OR gueltig_bis >= :jetzt)',
            ['berufsbildnerid' => $berufsbildnerid, 'jetzt' => $jetzt]
        );
        foreach ($offen as $zuordnung) {
            $beginn = (int) $zuordnung->get('gueltig_von');
            if ($beginn > $jetzt) {
                $zuordnung->delete();
            } else {
                $zuordnung->set('gueltig_bis', max($beginn, $jetzt - 1));
                $zuordnung->update();
            }
            $anzahl++;
        }

        foreach (kohorten_link::get_records(['berufsbildnerid' => $berufsbildnerid, 'aktiv' => true]) as $link) {
            $link->set('aktiv', false);
            $link->update();
        }
        if ($anzahl > 0) {
            sichtbarkeit::vergiss();
        }

        return $anzahl;
    }

    /**
     * Hat die Person heute mindestens eine laufende Zuordnung als
     * Berufsbildner/in? Bedingung wie api::is_zustaendig().
     *
     * @param int $berufsbildnerid
     * @return bool
     */
    public function hat_laufende_als_berufsbildner(int $berufsbildnerid): bool {
        $jetzt = time();

        return zuordnung::record_exists_select(
            'berufsbildnerid = :berufsbildnerid
             AND gueltig_von <= :stichtag1
             AND (gueltig_bis IS NULL OR gueltig_bis >= :stichtag2)',
            ['berufsbildnerid' => $berufsbildnerid, 'stichtag1' => $jetzt, 'stichtag2' => $jetzt]
        );
    }
}

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
 * Loescht Zuordnungen nach Ablauf der Aufbewahrungsfrist.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\service;

use local_berufsbildung\api;
use local_berufsbildung\persistent\aufbewahrung;
use local_berufsbildung\persistent\teilnahmeprofil;
use local_berufsbildung\persistent\zuordnung;

/**
 * Aufgerufen vom Scheduled Task task\zuordnung_retention und, fuer die
 * fruehzeitige Loeschung auf Anfrage, vom Privacy-Provider. Einziger
 * sanktionierte Aufrufer von zuordnung::delete() im ganzen Plugin - siehe
 * CLAUDE.md, Architekturregel 2 (Ausnahme).
 */
class zuordnung_retention_service {
    /**
     * Prueft alle je zugeordneten lernenden Personen - nicht nur solche mit
     * bereits beendeter Zeile, da eine Zuordnung bei Abschluss durchaus
     * weiterhin laufend sein kann (siehe docs/konzept.md §13.4).
     *
     * @return array{geprueft: int, geloescht: int, uebersprungen_aufbewahrung: int}
     */
    public function bereinige_abgelaufene(): array {
        global $DB;

        $ergebnis = ['geprueft' => 0, 'geloescht' => 0, 'uebersprungen_aufbewahrung' => 0];

        $lernendeids = $DB->get_fieldset_sql(
            'SELECT DISTINCT lernendeid FROM {local_berufsbildung_zuordnung}'
        );

        foreach ($lernendeids as $lernendeid) {
            $lernendeid = (int) $lernendeid;
            $ergebnis['geprueft']++;

            if (api::aufbewahrungsfrist_abgelaufen($lernendeid)) {
                $this->loesche_fuer_lernende($lernendeid);
                $ergebnis['geloescht']++;
            } else if (api::hat_aufbewahrungspflicht($lernendeid)) {
                $ergebnis['uebersprungen_aufbewahrung']++;
            }
        }

        return $ergebnis;
    }

    /**
     * Loescht alle Zuordnungen dieser Person, unabhaengig von gueltig_bis,
     * sowie bereits abgelaufene Aufbewahrungsvermerke (deren Grund entfaellt
     * mit den Daten, auf die sie sich bezogen).
     *
     * @param int $lernendeid
     * @return int Anzahl geloeschter Zuordnungen
     */
    public function loesche_fuer_lernende(int $lernendeid): int {
        $anzahl = 0;
        $berufsbildnerids = [];
        foreach (zuordnung::get_records(['lernendeid' => $lernendeid]) as $zuordnung) {
            $berufsbildnerids[(int) $zuordnung->get('berufsbildnerid')] = true;
            $zuordnung->delete();
            $anzahl++;
        }

        foreach (aufbewahrung::get_records(['lernendeid' => $lernendeid]) as $vermerk) {
            $vermerk->delete();
        }
        foreach (teilnahmeprofil::get_records(['userid' => $lernendeid]) as $profil) {
            $profil->delete();
        }

        // War das die letzte laufende Zuordnung einer betreuenden Person,
        // faellt damit auch ihr systemweiter Zugang zur Blockverwaltung weg -
        // sonst bliebe er bis zum naechsten Lauf von task\sync_role_assignments
        // bestehen. Bewusst nur die Planungsrolle: der personenbezogene
        // Abgleich braucht den User-Kontext der lernenden Person, und genau
        // der verschwindet hier gerade (Account-Loeschung).
        $rollensync = new role_sync_service();
        foreach (array_keys($berufsbildnerids) as $berufsbildnerid) {
            $rollensync->synchronisiere_planungsrolle($berufsbildnerid);
        }

        return $anzahl;
    }
}

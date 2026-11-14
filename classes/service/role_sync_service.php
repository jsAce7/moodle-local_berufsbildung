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
 * Gleicht die Rolle 'berufsbildner' mit der Zuordnungstabelle ab.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\service;

use context;
use context_system;
use context_user;
use local_berufsbildung\persistent\zuordnung;

/**
 * Bereits begonnene Zuordnung ohne Rollenzuweisung -> zuweisen.
 * Rollenzuweisungen bleiben bis zur Datenbereinigung erhalten, damit eine
 * historische Zuständigkeit nach einem Betreuerwechsel noch aufgelöst wird.
 *
 * Setzt component = 'local_berufsbildung' bei jeder selbst vergebenen
 * Zuweisung und erkennt daran seine eigenen wieder - manuell (component =
 * '') vergebene Rollen werden nie angefasst, auch nicht entzogen, wenn
 * keine Zuordnung (mehr) dahintersteht (siehe docs/konzept.md Abschnitt 7).
 */
class role_sync_service {
    /**
     * Gleicht ein einzelnes Paar sofort ab, damit eine gerade angelegte
     * aktuelle Zuordnung nicht bis zum stündlichen Task warten muss.
     *
     * @param int $berufsbildnerid
     * @param int $lernendeid
     */
    public function synchronisiere_paar(int $berufsbildnerid, int $lernendeid): void {
        global $DB;

        // Ein geloeschtes Konto bekommt keine Rolle mehr (role_assign() lehnt
        // es ab), und der User-Kontext geloeschter Lernender verschwindet mit
        // dem naechsten context_cleanup_task. delete_user() hat die Rollen
        // des Kontos ohnehin entfernt - es bleibt nur die Planungsrolle der
        // betreuenden Person nachzufuehren.
        $geloescht = $this->geloeschte_konten([$berufsbildnerid, $lernendeid]);
        if (isset($geloescht[$berufsbildnerid])) {
            return;
        }
        if (isset($geloescht[$lernendeid])) {
            $this->synchronisiere_planungsrolle($berufsbildnerid);
            return;
        }

        $roleid = $DB->get_field('role', 'id', ['shortname' => 'berufsbildner'], MUST_EXIST);
        $context = context_user::instance($lernendeid);
        $besteht = zuordnung::record_exists_select(
            'berufsbildnerid = :berufsbildnerid AND lernendeid = :lernendeid AND gueltig_von <= :jetzt',
            ['berufsbildnerid' => $berufsbildnerid, 'lernendeid' => $lernendeid, 'jetzt' => time()]
        );
        $zuweisung = $DB->get_record('role_assignments', [
            'roleid' => $roleid,
            'userid' => $berufsbildnerid,
            'contextid' => $context->id,
            'component' => 'local_berufsbildung',
        ]);

        if ($besteht && !$zuweisung) {
            role_assign($roleid, $berufsbildnerid, $context->id, 'local_berufsbildung');
        } else if (!$besteht && $zuweisung) {
            role_unassign($roleid, $berufsbildnerid, $context->id, 'local_berufsbildung');
        }

        // Zugang zur Blockverwaltung sofort mitziehen. Die Frage ist eine
        // andere als oben: nicht "zustaendig fuer diese Person", sondern
        // "betreut ueberhaupt noch jemanden".
        $this->synchronisiere_planungsrolle($berufsbildnerid);
    }

    /**
     * Gleicht die Planungsrolle einer einzelnen Person ab, ohne den
     * User-Kontext einer lernenden Person anzufassen.
     *
     * Getrennt von synchronisiere_paar() fuer Aufrufer, bei denen genau
     * dieser Kontext gerade verschwindet - siehe
     * zuordnung_retention_service::loesche_fuer_lernende(), das bei einer
     * Account-Loeschung aufgerufen wird.
     *
     * @param int $berufsbildnerid
     */
    public function synchronisiere_planungsrolle(int $berufsbildnerid): void {
        if ($this->geloeschte_konten([$berufsbildnerid])) {
            return;
        }
        $jetzt = time();

        // Laufende, nicht bloss begonnene Zuordnung - siehe
        // synchronisiere_planungsrollen(). Bedingung wie api::is_zustaendig().
        $this->setze_planungsrolle($berufsbildnerid, zuordnung::record_exists_select(
            'berufsbildnerid = :berufsbildnerid
                AND gueltig_von <= :stichtag1
                AND (gueltig_bis IS NULL OR gueltig_bis >= :stichtag2)',
            ['berufsbildnerid' => $berufsbildnerid, 'stichtag1' => $jetzt, 'stichtag2' => $jetzt]
        ));
    }

    /**
     * Welche der Konten sind geloescht oder fehlen ganz?
     *
     * @param int[] $userids
     * @return array<int, true> User-ID => true
     */
    private function geloeschte_konten(array $userids): array {
        global $DB;

        $userids = array_values(array_unique(array_map('intval', $userids)));
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $vorhanden = $DB->get_fieldset_select('user', 'id', "id $insql AND deleted = 0", $params);

        return array_fill_keys(array_diff($userids, array_map('intval', $vorhanden)), true);
    }

    /**
     * Die Planungsrolle im Systemkontext oeffnet die Blockverwaltung
     * (local/berufsbildung:manageblocks) fuer jede Person, die aktuell
     * mindestens eine laufende Zuordnung hat - und entzieht sie wieder,
     * sobald die letzte davon beendet ist.
     *
     * Bewusst eine eigene Rolle und nicht die personenbezogene Rolle
     * 'berufsbildner': deren Zuweisungen haengen am User-Kontext der
     * jeweiligen lernenden Person. Global zugewiesen wuerden alle ihre
     * Capabilities - auch die, die aufsetzende Plugins ihr spaeter geben -
     * fuer *alle* Personen gelten und damit die Stichtagspruefung in
     * api::is_zustaendig() unterlaufen.
     *
     * @return int|null null, solange das Upgrade die Rolle nicht angelegt hat
     */
    private function planungsrolle_id(): ?int {
        global $DB;

        $roleid = $DB->get_field('role', 'id', ['shortname' => 'berufsbildung_planung'], IGNORE_MISSING);

        return $roleid ? (int) $roleid : null;
    }

    /**
     * Einzelfall - nur aus synchronisiere_paar(). Nie fuer einen
     * vollstaendigen Abgleich verwenden: diese Methode kennt die Soll-Menge
     * der anderen Personen nicht.
     *
     * @param int $berufsbildnerid
     * @param bool $soll
     */
    private function setze_planungsrolle(int $berufsbildnerid, bool $soll): void {
        global $DB;

        $roleid = $this->planungsrolle_id();
        if ($roleid === null) {
            return;
        }

        $context = context_system::instance();
        $zuweisung = $DB->record_exists('role_assignments', [
            'roleid' => $roleid,
            'userid' => $berufsbildnerid,
            'contextid' => $context->id,
            'component' => 'local_berufsbildung',
        ]);

        if ($soll && !$zuweisung) {
            role_assign($roleid, $berufsbildnerid, $context->id, 'local_berufsbildung');
        } else if (!$soll && $zuweisung) {
            role_unassign($roleid, $berufsbildnerid, $context->id, 'local_berufsbildung');
        }
    }

    /**
     * Vollstaendiger Abgleich der Planungsrolle.
     *
     * @param int[] $berufsbildnerids Alle Personen mit laufender Zuordnung
     * @return array{zugewiesen: int, entzogen: int}
     */
    private function synchronisiere_planungsrollen(array $berufsbildnerids): array {
        global $DB;

        $roleid = $this->planungsrolle_id();
        if ($roleid === null) {
            return ['zugewiesen' => 0, 'entzogen' => 0];
        }

        $context = context_system::instance();
        $soll = array_fill_keys(array_map('intval', $berufsbildnerids), true);

        // Wie beim personenbezogenen Abgleich: nur die selbst vergebenen
        // Zuweisungen: eine von Hand vergebene Rolle (component = '') wird
        // nie angefasst.
        $ist = [];
        $zuweisungen = $DB->get_records('role_assignments', [
            'roleid' => $roleid,
            'contextid' => $context->id,
            'component' => 'local_berufsbildung',
        ]);
        foreach ($zuweisungen as $zuweisung) {
            $ist[(int) $zuweisung->userid] = true;
        }

        $zugewiesen = 0;
        foreach (array_keys($soll) as $userid) {
            if (isset($ist[$userid])) {
                continue;
            }
            role_assign($roleid, $userid, $context->id, 'local_berufsbildung');
            $zugewiesen++;
        }

        $entzogen = 0;
        foreach (array_keys($ist) as $userid) {
            if (isset($soll[$userid])) {
                continue;
            }
            role_unassign($roleid, $userid, $context->id, 'local_berufsbildung');
            $entzogen++;
        }

        return ['zugewiesen' => $zugewiesen, 'entzogen' => $entzogen];
    }

    /**
     * Gleicht die Rollenzuweisungen ab.
     *
     * @return array{zugewiesen: int, entzogen: int, planung_zugewiesen: int, planung_entzogen: int}
     */
    public function synchronisiere(): array {
        global $DB;

        $roleid = $DB->get_field('role', 'id', ['shortname' => 'berufsbildner'], MUST_EXIST);
        $jetzt = time();

        // Die Rolle liefert nur die Moodle-Capability. Der konkrete Zugriff
        // bleibt an api::is_zustaendig() zum jeweiligen Stichtag gebunden.
        // Zuordnungen mit einem geloeschten Konto auf einer der beiden Seiten
        // fallen heraus: role_assign() lehnt ein geloeschtes Konto ab, und
        // der User-Kontext geloeschter Lernender verschwindet mit dem
        // naechsten context_cleanup_task. Beides liesse sonst jeden Lauf
        // scheitern, bis die Zuordnung bereinigt ist.
        $aktive = $DB->get_records_sql(
            "SELECT z.id, z.lernendeid, z.berufsbildnerid, z.gueltig_bis
               FROM {local_berufsbildung_zuordnung} z
               JOIN {user} ub ON ub.id = z.berufsbildnerid AND ub.deleted = 0
               JOIN {user} ul ON ul.id = z.lernendeid AND ul.deleted = 0
              WHERE z.gueltig_von <= :jetzt",
            ['jetzt' => $jetzt]
        );

        $soll = [];
        $planungssoll = [];
        foreach ($aktive as $einzelne) {
            $lernendeid = (int) $einzelne->lernendeid;
            $berufsbildnerid = (int) $einzelne->berufsbildnerid;
            $soll["{$lernendeid}:{$berufsbildnerid}"] = [
                'lernendeid' => $lernendeid,
                'berufsbildnerid' => $berufsbildnerid,
            ];

            // Die Planungsrolle haengt an der laufenden Zuordnung, waehrend
            // die Rolle oben schon bei der bloss begonnenen bestehen bleibt:
            // sie ist systemweites Schreibrecht auf Stammdaten, kein
            // stichtagsgepruefter Lesezugriff, fuer den eine beendete
            // Zuordnung noch zaehlen muss. Bedingung wie in
            // api::is_zustaendig().
            $bis = $einzelne->gueltig_bis;
            if ($bis === null || (int) $bis >= $jetzt) {
                $planungssoll[$berufsbildnerid] = true;
            }
        }

        // Ist-Zustand: nur die von diesem Task selbst vergebenen Zuweisungen.
        $vorhandene = $DB->get_records('role_assignments', ['roleid' => $roleid, 'component' => 'local_berufsbildung']);

        $ist = [];
        foreach ($vorhandene as $zuweisung) {
            $context = context::instance_by_id((int) $zuweisung->contextid, IGNORE_MISSING);
            if (!$context || $context->contextlevel !== CONTEXT_USER) {
                continue;
            }
            $ist["{$context->instanceid}:{$zuweisung->userid}"] = $zuweisung;
        }

        // Ein einzelnes Paar, das scheitert, haelt den Abgleich der uebrigen
        // und den Planungsrollen-Abgleich dahinter nicht auf.
        $zugewiesen = 0;
        foreach ($soll as $schluessel => $paar) {
            if (isset($ist[$schluessel])) {
                continue;
            }
            try {
                $context = context_user::instance($paar['lernendeid']);
                role_assign($roleid, $paar['berufsbildnerid'], $context->id, 'local_berufsbildung');
                $zugewiesen++;
            } catch (\moodle_exception $e) {
                debugging(
                    "local_berufsbildung: Rolle fuer Paar {$schluessel} nicht zugewiesen: " . $e->getMessage(),
                    DEBUG_DEVELOPER
                );
            }
        }

        $entzogen = 0;
        foreach ($ist as $schluessel => $zuweisung) {
            if (isset($soll[$schluessel])) {
                continue;
            }
            role_unassign($roleid, (int) $zuweisung->userid, (int) $zuweisung->contextid, 'local_berufsbildung');
            $entzogen++;
        }

        // Wer aktuell jemanden betreut, verwaltet auch die
        // Ausbildungsbloecke. Eigene Zaehler, damit die beiden Rollen im
        // Task-Log auseinander zu halten sind.
        $planung = $this->synchronisiere_planungsrollen(array_keys($planungssoll));

        return [
            'zugewiesen' => $zugewiesen,
            'entzogen' => $entzogen,
            'planung_zugewiesen' => $planung['zugewiesen'],
            'planung_entzogen' => $planung['entzogen'],
        ];
    }
}

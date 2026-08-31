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
 * keine Zuordnung (mehr) dahintersteht (siehe docs/plan.md Abschnitt 7).
 */
class role_sync_service {

    /**
     * Gleicht ein einzelnes Paar sofort ab, damit eine gerade angelegte
     * aktuelle Zuordnung nicht bis zum stündlichen Task warten muss.
     */
    public function synchronisiere_paar(int $berufsbildnerid, int $lernendeid): void {
        global $DB;

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
    }

    /**
     * @return array{zugewiesen: int, entzogen: int}
     */
    public function synchronisiere(): array {
        global $DB;

        $roleid = $DB->get_field('role', 'id', ['shortname' => 'berufsbildner'], MUST_EXIST);
        $jetzt = time();

        // Die Rolle liefert nur die Moodle-Capability. Der konkrete Zugriff
        // bleibt an api::is_zustaendig() zum jeweiligen Stichtag gebunden.
        $aktive = zuordnung::get_records_select(
            'gueltig_von <= :jetzt',
            ['jetzt' => $jetzt]
        );

        $soll = [];
        foreach ($aktive as $einzelne) {
            $lernendeid = (int) $einzelne->get('lernendeid');
            $berufsbildnerid = (int) $einzelne->get('berufsbildnerid');
            $soll["{$lernendeid}:{$berufsbildnerid}"] = [
                'lernendeid' => $lernendeid,
                'berufsbildnerid' => $berufsbildnerid,
            ];
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

        $zugewiesen = 0;
        foreach ($soll as $schluessel => $paar) {
            if (isset($ist[$schluessel])) {
                continue;
            }
            $context = context_user::instance($paar['lernendeid']);
            role_assign($roleid, $paar['berufsbildnerid'], $context->id, 'local_berufsbildung');
            $zugewiesen++;
        }

        $entzogen = 0;
        foreach ($ist as $schluessel => $zuweisung) {
            if (isset($soll[$schluessel])) {
                continue;
            }
            role_unassign($roleid, (int) $zuweisung->userid, (int) $zuweisung->contextid, 'local_berufsbildung');
            $entzogen++;
        }

        return ['zugewiesen' => $zugewiesen, 'entzogen' => $entzogen];
    }
}

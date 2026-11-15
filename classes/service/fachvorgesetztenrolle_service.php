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
 * Pflege der Rolle 'berufsbildung_fachvorgesetzt'.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\service;

use context_system;
use local_berufsbildung\persistent\block_fv;

/**
 * Die Rolle 'berufsbildung_fachvorgesetzt' haengt im Systemkontext an jeder
 * Person, die bei mindestens einem Ausbildungsblock als fachvorgesetzt
 * eingetragen ist - und wird ihr wieder entzogen, sobald sie es bei keinem
 * mehr ist. Welche Capabilities die Rolle traegt, melden die aufsetzenden
 * Plugins ueber den Callback <plugin>_berufsbildung_fachvorgesetzt_capabilities()
 * (wie bei der Leitungsrolle); Kontextlevel und Capabilities stellt
 * stelle_sicher() wieder her.
 *
 * Die Rolle oeffnet fuer sich allein nichts: die aufsetzenden Plugins
 * pruefen neben der Capability immer, ob die Person fuer den Block des
 * Berichts fachvorgesetzt ist (api::ist_fachvorgesetzt_fuer_block()). Sie
 * traegt bewusst keine Berufsbildner-Rechte.
 *
 * Nur Zuweisungen mit component = 'local_berufsbildung' werden angefasst;
 * eine von Hand vergebene Rolle bleibt.
 */
class fachvorgesetztenrolle_service extends leitungsrolle_service {
    /** Kurzname der Rolle. */
    public const SHORTNAME = 'berufsbildung_fachvorgesetzt';

    /** Callback in der lib.php der aufsetzenden Plugins. */
    protected const CALLBACK = 'berufsbildung_fachvorgesetzt_capabilities';

    /**
     * Vollstaendiger Abgleich: alle eingetragenen Fachvorgesetzten mit einem
     * nicht geloeschten Konto haben die Rolle, alle anderen nicht.
     *
     * @return array{zugewiesen: int, entzogen: int}
     */
    public function synchronisiere(): array {
        global $DB;

        $roleid = $this->rolle_id();
        if ($roleid === null) {
            return ['zugewiesen' => 0, 'entzogen' => 0];
        }

        $soll = array_fill_keys(array_map('intval', $DB->get_fieldset_sql(
            'SELECT DISTINCT fv.userid
               FROM {' . block_fv::TABLE . '} fv
               JOIN {user} u ON u.id = fv.userid AND u.deleted = 0'
        )), true);

        $context = context_system::instance();
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
        foreach (array_keys(array_diff_key($soll, $ist)) as $userid) {
            role_assign($roleid, $userid, $context->id, 'local_berufsbildung');
            $zugewiesen++;
        }

        $entzogen = 0;
        foreach (array_keys(array_diff_key($ist, $soll)) as $userid) {
            role_unassign($roleid, $userid, $context->id, 'local_berufsbildung');
            $entzogen++;
        }

        return ['zugewiesen' => $zugewiesen, 'entzogen' => $entzogen];
    }

    /**
     * Einzelfall nach einer Aenderung an den Fachvorgesetzten eines Blocks.
     *
     * @param int $userid
     */
    public function synchronisiere_person(int $userid): void {
        global $DB;

        $roleid = $this->rolle_id();
        if ($roleid === null) {
            return;
        }

        $soll = $DB->record_exists_sql(
            'SELECT 1
               FROM {' . block_fv::TABLE . '} fv
               JOIN {user} u ON u.id = fv.userid AND u.deleted = 0
              WHERE fv.userid = :userid',
            ['userid' => $userid]
        );

        $context = context_system::instance();
        $ist = $DB->record_exists('role_assignments', [
            'roleid' => $roleid,
            'userid' => $userid,
            'contextid' => $context->id,
            'component' => 'local_berufsbildung',
        ]);

        if ($soll && !$ist) {
            role_assign($roleid, $userid, $context->id, 'local_berufsbildung');
        } else if (!$soll && $ist) {
            role_unassign($roleid, $userid, $context->id, 'local_berufsbildung');
        }
    }
}

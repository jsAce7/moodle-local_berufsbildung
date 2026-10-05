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
 * Haelt die Rolle 'berufsbildung_leitung' benutzbar.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\service;

use context_system;

/**
 * Die Leitungsrolle hat keinen Archetyp. "Rolle zuruecksetzen" in der
 * Rollenverwaltung setzt sie deshalb auf nichts zurueck: alle Capabilities
 * und das Kontextlevel sind weg, die Leitung verliert ihre Seiten und die
 * Rolle laesst sich global nicht mehr zuweisen - ohne jede Meldung.
 *
 * Dieser Service stellt beides wieder her. Welche Capabilities die Rolle
 * braucht, melden die aufsetzenden Plugins ueber den Callback
 * <plugin>_berufsbildung_leitung_capabilities() in ihrer lib.php; so
 * kennt das Basis-Plugin keine fremde Capability beim Namen.
 *
 * Wiederhergestellt wird nur, was in der Rolle gar nicht gesetzt ist. Wer
 * der Leitung ein Recht bewusst entziehen will, setzt es in der
 * Rollenverwaltung auf "Verhindern" oder "Verbieten" - das bleibt stehen.
 */
class leitungsrolle_service {
    /** Kurzname der Rolle. */
    public const SHORTNAME = 'berufsbildung_leitung';

    /** Callback in der lib.php der aufsetzenden Plugins. */
    private const CALLBACK = 'berufsbildung_leitung_capabilities';

    /**
     * Stellt Kontextlevel und Capabilities der Rolle wieder her.
     *
     * Laeuft nach jeder entzogenen Capability der Rolle (observer), stuendlich
     * im Task sync_role_assignments und aus Installation und Upgrade der
     * aufsetzenden Plugins.
     *
     * @param string[]|null $capabilities die sicherzustellenden Capabilities;
     *  null fuer alle, die die aufsetzenden Plugins melden. Installation und
     *  Upgrade geben ihre eigenen an, weil der Callback-Cache sie dann noch
     *  nicht kennt.
     * @return int Anzahl wiederhergestellter Eintraege
     */
    public function stelle_sicher(?array $capabilities = null): int {
        $roleid = $this->rolle_id();
        if ($roleid === null) {
            return 0;
        }

        $wiederhergestellt = 0;

        $levels = array_map('intval', array_values(get_role_contextlevels($roleid)));
        if (!in_array(CONTEXT_SYSTEM, $levels, true)) {
            set_role_contextlevels($roleid, array_merge($levels, [CONTEXT_SYSTEM]));
            $wiederhergestellt++;
        }

        $systemid = context_system::instance()->id;
        foreach ($capabilities ?? $this->gemeldete_capabilities() as $capability) {
            // Eine Capability eines deinstallierten Plugins gibt es nicht mehr;
            // assign_capability() wuerde dafuer werfen.
            if (!get_capability_info($capability)) {
                continue;
            }
            if ($this->ist_gesetzt($roleid, $systemid, $capability)) {
                continue;
            }
            assign_capability($capability, CAP_ALLOW, $roleid, $systemid);
            $wiederhergestellt++;
        }

        return $wiederhergestellt;
    }

    /**
     * Die Capabilities, die die aufsetzenden Plugins fuer die Rolle melden.
     *
     * @return string[]
     */
    public function gemeldete_capabilities(): array {
        $capabilities = [];
        foreach (get_plugins_with_function(self::CALLBACK) as $funktionenjetyp) {
            foreach ($funktionenjetyp as $funktion) {
                foreach ($funktion() as $capability) {
                    $capabilities[] = (string) $capability;
                }
            }
        }

        return array_values(array_unique($capabilities));
    }

    /**
     * Die ID der Rolle.
     *
     * @return int|null null, solange sie nicht existiert oder ihr Kurzname
     *  von Hand geaendert wurde
     */
    public function rolle_id(): ?int {
        global $DB;

        $roleid = $DB->get_field('role', 'id', ['shortname' => self::SHORTNAME], IGNORE_MISSING);

        return $roleid ? (int) $roleid : null;
    }

    /**
     * Ob die Rolle die Capability im Systemkontext ueberhaupt setzt, egal mit
     * welchem Wert.
     *
     * @param int $roleid
     * @param int $systemid
     * @param string $capability
     * @return bool
     */
    private function ist_gesetzt(int $roleid, int $systemid, string $capability): bool {
        global $DB;

        return $DB->record_exists('role_capabilities', [
            'roleid' => $roleid,
            'contextid' => $systemid,
            'capability' => $capability,
        ]);
    }
}

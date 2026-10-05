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
 * Traegt die Rollen des Plugins in die Allow-Matrizen von Moodle ein.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\service;

/**
 * create_role() legt in role_allow_assign und role_allow_view keine Zeile
 * an, und get_assignable_roles() bzw. get_viewable_roles() filtern fuer
 * jede Person ohne Admin-Rechte genau an diesen beiden Matrizen. Ohne
 * diesen Schritt konnte deshalb nur eine Administratorin die Rollen des
 * Plugins von Hand vergeben - obwohl die README das Vergeben der
 * Planungsrolle der Ausbildungsleitung zuschreibt.
 *
 * Zuweisbar werden bewusst nur die beiden Systemrollen 'berufsbildung_planung'
 * und 'berufsbildung_leitung'. Die personenbezogene
 * Rolle 'berufsbildner' pflegt der role_sync_service aus der
 * Zuordnungstabelle; von Hand vergeben oeffnet sie ohnehin nichts, weil
 * die aufsetzenden Plugins neben ihrer Capability immer auch
 * api::is_zustaendig() pruefen (Architekturregel 2: die Zuordnungstabelle
 * ist die einzige Wahrheit). Sichtbar sind alle drei, damit ihre Namen in
 * Rollenuebersichten nicht leer bleiben.
 */
class role_matrix_service {
    /**
     * Rolle, die die beiden Systemrollen vergeben darf. Die Capabilities des
     * Plugins tragen in db/access.php denselben Archetyp - 'manager' ist
     * hier also die "berechtigte Verwaltung" aus Architekturregel 2.
     */
    private const VERWALTUNG = 'manager';

    /** Rollen, die die Verwaltung vergeben darf. */
    private const ZUWEISBAR = ['berufsbildung_planung', 'berufsbildung_leitung'];

    /** Rollen, deren Namen die Verwaltung sehen darf. */
    private const SICHTBAR = ['berufsbildung_planung', 'berufsbildung_leitung', 'berufsbildner'];

    /**
     * Laeuft aus db/install.php und db/upgrade.php.
     *
     * Bewusst idempotent: role_allow_assign und role_allow_view haben
     * keinen Unique-Index, ein zweiter Lauf wuerde die Erlaubnis sonst
     * doppelt eintragen, statt zu scheitern - und doppelte Zeilen blieben
     * unbemerkt, weil die Admin-Oberflaeche die Matrix vor jedem Speichern
     * komplett neu schreibt.
     *
     * Eine von Hand entfernte Erlaubnis wird nicht wieder gesetzt: der
     * Schritt laeuft je Installation und Rolle nur einmal (Savepoint im
     * Upgrade). Kommt eine Rolle spaeter dazu, traegt ihr Upgrade-Schritt
     * deshalb nur sie nach und gibt sie in $nur an.
     *
     * @param string[]|null $nur Kurznamen der Rollen, die nachgetragen werden;
     *  null fuer alle
     * @return array{assign: int, view: int} Anzahl neu eingetragener Zeilen
     */
    public function synchronisiere(?array $nur = null): array {
        $verwaltung = $this->rolle_id(self::VERWALTUNG);
        if ($verwaltung === null) {
            return ['assign' => 0, 'view' => 0];
        }

        $assign = 0;
        foreach ($this->rollen(self::ZUWEISBAR, $nur) as $zielrolle) {
            if ($this->fehlt('role_allow_assign', 'allowassign', $verwaltung, $zielrolle)) {
                core_role_set_assign_allowed($verwaltung, $zielrolle);
                $assign++;
            }
        }

        $view = 0;
        foreach ($this->rollen(self::SICHTBAR, $nur) as $zielrolle) {
            if ($this->fehlt('role_allow_view', 'allowview', $verwaltung, $zielrolle)) {
                core_role_set_view_allowed($verwaltung, $zielrolle);
                $view++;
            }
        }

        return ['assign' => $assign, 'view' => $view];
    }

    /**
     * Die IDs der existierenden Rollen aus einer Liste von Kurznamen.
     *
     * @param string[] $shortnames
     * @param string[]|null $nur nur diese Kurznamen beruecksichtigen; null fuer alle
     * @return int[]
     */
    private function rollen(array $shortnames, ?array $nur): array {
        $ids = [];
        foreach ($shortnames as $shortname) {
            if ($nur !== null && !in_array($shortname, $nur, true)) {
                continue;
            }
            $roleid = $this->rolle_id($shortname);
            if ($roleid !== null) {
                $ids[] = $roleid;
            }
        }

        return $ids;
    }

    /**
     * Liefert die ID einer Rolle anhand ihres Kurznamens.
     *
     * @param string $shortname
     * @return int|null null, solange die Rolle nicht existiert - die
     *  Planungsrolle etwa, bis das Upgrade sie angelegt hat
     */
    private function rolle_id(string $shortname): ?int {
        global $DB;

        $roleid = $DB->get_field('role', 'id', ['shortname' => $shortname], IGNORE_MISSING);

        return $roleid ? (int) $roleid : null;
    }

    /**
     * Prueft, ob eine Erlaubnis in der Allow-Matrix noch fehlt.
     *
     * @param string $tabelle 'role_allow_assign' oder 'role_allow_view'
     * @param string $spalte Zielspalte der Tabelle: 'allowassign' bzw. 'allowview'
     * @param int $vonrolle
     * @param int $zielrolle
     * @return bool true, wenn die Erlaubnis noch nicht eingetragen ist
     */
    private function fehlt(string $tabelle, string $spalte, int $vonrolle, int $zielrolle): bool {
        global $DB;

        return !$DB->record_exists($tabelle, ['roleid' => $vonrolle, $spalte => $zielrolle]);
    }
}

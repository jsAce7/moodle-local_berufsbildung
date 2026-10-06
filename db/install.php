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
 * Installationsschritte.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Legt die drei Rollen des Plugins an, sofern sie nicht existieren:
 *
 * - 'berufsbildner' im User-Kontext einer lernenden Person, ohne eigene
 *   Capabilities - die vergeben die aufsetzenden Plugins.
 * - 'berufsbildung_planung' im Systemkontext, traegt die Blockverwaltung.
 * - 'berufsbildung_leitung' im Systemkontext, ohne eigene Capabilities.
 *
 * und macht sie anschliessend fuer die Rollenverwaltung sichtbar bzw.
 * vergebbar - siehe role_matrix_service.
 */
function xmldb_local_berufsbildung_install() {
    global $DB;

    if (!$DB->record_exists('role', ['shortname' => 'berufsbildner'])) {
        $roleid = create_role(
            get_string('role:berufsbildner', 'local_berufsbildung'),
            'berufsbildner',
            get_string('role:berufsbildner_desc', 'local_berufsbildung')
        );
        set_role_contextlevels($roleid, [CONTEXT_USER]);
    }

    // Getrennt von 'berufsbildner', weil deren Zuweisungen personenbezogen
    // im User-Kontext haengen: global zugewiesen wuerden alle ihre
    // Capabilities fuer alle Personen gelten und die Stichtagspruefung in
    // api::is_zustaendig() unterlaufen. Siehe role_sync_service.
    if (!$DB->record_exists('role', ['shortname' => 'berufsbildung_planung'])) {
        $roleid = create_role(
            get_string('role:planung', 'local_berufsbildung'),
            'berufsbildung_planung',
            get_string('role:planung_desc', 'local_berufsbildung')
        );
        set_role_contextlevels($roleid, [CONTEXT_SYSTEM]);

        // Moodles upgrade_plugins() ruft update_capabilities() erst nach dieser
        // Funktion auf. Bei einer Neuinstallation kennt die Datenbank
        // 'manageblocks' deshalb noch nicht und assign_capability() bricht
        // mit einer coding_exception ab. Der vorgezogene Aufruf ist
        // idempotent - der spaetere von upgrade_plugins() bleibt gueltig.
        update_capabilities('local_berufsbildung');

        assign_capability(
            'local/berufsbildung:manageblocks',
            CAP_ALLOW,
            $roleid,
            context_system::instance()->id,
            true
        );
    }

    xmldb_local_berufsbildung_lege_leitungsrolle_an();

    // Moodles create_role() traegt in role_allow_assign und role_allow_view nichts
    // ein. Ohne diesen Schritt bekommt nur eine Administratorin die Rollen
    // ueberhaupt zur Auswahl, weil get_assignable_roles() fuer alle
    // anderen genau an dieser Matrix filtert.
    (new \local_berufsbildung\service\role_matrix_service())->synchronisiere();

    // Kommt das Plugin zu einer bestehenden üK- oder Bildungsbericht-
    // Installation dazu, bringt es deren PDF-Gestaltung mit.
    \local_berufsbildung\pdf\gestaltung::uebernehme_bisherige_einstellungen();
}

/**
 * Legt die Rolle 'berufsbildung_leitung' an, sofern sie nicht existiert.
 *
 * Fuer die Leitung Berufsbildung, die ueber alle Lernenden hinweg liest -
 * etwa die Noten eines ganzen Jahrgangs. Die aufsetzenden Plugins vergeben
 * ihr ihre Capabilities selbst, wie bei 'berufsbildner'.
 *
 * Getrennt von 'berufsbildung_planung', weil der role_sync_service jene
 * jeder Person mit laufender Zuordnung zuweist: ein Leserecht ohne
 * Zustaendigkeitspruefung darf dort nie haengen. Diese Rolle weist niemand
 * automatisch zu, sie wird von Hand global vergeben.
 *
 * Auch aus db/upgrade.php aufgerufen.
 */
function xmldb_local_berufsbildung_lege_leitungsrolle_an(): void {
    global $DB;

    if ($DB->record_exists('role', ['shortname' => 'berufsbildung_leitung'])) {
        return;
    }

    $roleid = create_role(
        get_string('role:leitung', 'local_berufsbildung'),
        'berufsbildung_leitung',
        get_string('role:leitung_desc', 'local_berufsbildung')
    );
    set_role_contextlevels($roleid, [CONTEXT_SYSTEM]);
}

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
 * Hook-Callbacks.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung;

use context_system;
use core\hook\navigation\primary_extend;
use moodle_url;
use navigation_node;

/**
 * Haengt "Meine Lehre" / "Meine Lernenden" / "Ausbildungsbloecke"
 * direkt in die primaere Navigationsleiste (Dashboard / Meine Kurse / ...)
 * statt in den einklapp- oder ausblendbaren Seiten-Drawer - der ist je nach
 * Theme nicht zuverlaessig erreichbar, die primaere Leiste ist es immer.
 */
class hook_callbacks {

    /** Key des Core-Knotens "Website-Administration" in der primaeren Navigation. */
    private const SITEADMIN_KEY = 'siteadminnode';

    /**
     * @param primary_extend $hook
     */
    public static function primary_extend(primary_extend $hook): void {
        global $USER;

        if (!isloggedin() || isguestuser()) {
            return;
        }

        $primaryview = $hook->get_primaryview();
        $userid = (int) $USER->id;

        // Key => [Sprachstring, Seite]. Die Reihenfolge hier ist die
        // Reihenfolge in der Leiste.
        $eintraege = [];

        if (api::get_ausbildungsstand($userid) !== null) {
            $eintraege['local_berufsbildung_meine_lehre'] = ['nav:meine_lehre', 'meine_lehre.php'];
        }

        if (!empty(api::get_lernende_for($userid))) {
            $eintraege['local_berufsbildung_meine_lernenden'] = ['nav:meine_lernenden', 'meine_lernenden.php'];
        }

        // Die Blockverwaltung liegt im Admin-Baum unter "Ausbildungsverwaltung
        // -> Planung". Ohne diesen Eintrag findet sie niemand, der nicht
        // ohnehin in der Website-Administration unterwegs ist - die
        // Capability allein macht eine Seite nicht auffindbar.
        if (has_capability('local/berufsbildung:manageblocks', context_system::instance())) {
            $eintraege['local_berufsbildung_bloecke'] = ['nav:bloecke', 'bloecke.php'];
        }

        // Vor die Website-Administration einsortieren: die fachlichen
        // Einstiege sind Alltag, der Admin-Knoten die Ausnahme. add() haengt
        // immer hinten an, eine Position kennt nur add_node(). Fehlt der
        // Admin-Knoten (Nutzer ohne Admin-Zugang) oder heisst er in einer
        // kuenftigen Version anders, bleibt $vor null und die Eintraege
        // stehen wie bisher am Ende - das vermeidet zugleich die
        // debugging()-Meldung, die ein unbekannter $beforekey ausloest.
        $vor = $primaryview->find(self::SITEADMIN_KEY, null) ? self::SITEADMIN_KEY : null;

        foreach ($eintraege as $schluessel => [$stringkey, $seite]) {
            $primaryview->add_node(navigation_node::create(
                get_string($stringkey, 'local_berufsbildung'),
                new moodle_url('/local/berufsbildung/' . $seite),
                navigation_node::TYPE_CUSTOM,
                null,
                $schluessel
            ), $vor);
        }
    }
}

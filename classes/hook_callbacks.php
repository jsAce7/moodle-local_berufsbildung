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

use core\hook\navigation\primary_extend;
use moodle_url;
use navigation_node;

/**
 * Haengt "Meine Lehre" / "Meine Lernenden" direkt in die primaere
 * Navigationsleiste (Home / Dashboard / ...) statt in den einklapp- oder
 * ausblendbaren Seiten-Drawer - der ist je nach Theme nicht zuverlaessig
 * erreichbar, die primaere Leiste ist es immer.
 */
class hook_callbacks {

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

        if (api::get_ausbildungsstand($userid) !== null) {
            $primaryview->add(
                get_string('nav:meine_lehre', 'local_berufsbildung'),
                new moodle_url('/local/berufsbildung/meine_lehre.php'),
                navigation_node::TYPE_CUSTOM,
                null,
                'local_berufsbildung_meine_lehre'
            );
        }

        if (!empty(api::get_lernende_for($userid))) {
            $primaryview->add(
                get_string('nav:meine_lernenden', 'local_berufsbildung'),
                new moodle_url('/local/berufsbildung/meine_lernenden.php'),
                navigation_node::TYPE_CUSTOM,
                null,
                'local_berufsbildung_meine_lernenden'
            );
        }
    }
}

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
 * Service for cohort links.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\service;

use local_berufsbildung\persistent\kohorten_link;
use local_berufsbildung\persistent\zuordnung;

/**
 * Safely removes cohort links that have not yet created assignments.
 */
class kohorten_link_service {
    /**
     * Deletes a cohort link without assignment history.
     *
     * @param int $linkid Cohort link ID.
     * @return void
     */
    public function loeschen(int $linkid): void {
        if (zuordnung::record_exists_select('kohorten_link_id = :linkid', ['linkid' => $linkid])) {
            throw new \moodle_exception('kohortenlink:loeschen_mit_zuordnungen', 'local_berufsbildung');
        }

        (new kohorten_link($linkid))->delete();
    }
}

<?php
// This file is part of Moodle - http://moodle.org/

// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

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
        if (zuordnung::record_exists(['kohorten_link_id' => $linkid])) {
            throw new \moodle_exception('kohortenlink:loeschen_mit_zuordnungen', 'local_berufsbildung');
        }

        (new kohorten_link($linkid))->delete();
    }
}

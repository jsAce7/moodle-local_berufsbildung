<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Teilnahmeprofil einer lernenden Person.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\persistent;

use core\persistent;

/** Persönliches Betreuungsprofil, unabhängig von Kohorten und Zuordnungen. */
final class teilnahmeprofil extends persistent {
    const TABLE = 'local_berufsbildung_teilnahmeprofil';

    protected static function define_properties(): array {
        return [
            'userid' => ['type' => PARAM_INT],
            'art' => ['type' => PARAM_ALPHANUMEXT, 'default' => 'lehre'],
        ];
    }
}

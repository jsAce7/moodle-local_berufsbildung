<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Optional interface for sources that contribute dated actions.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\nachweis;

/** Sources may expose planning data without the base plugin knowing them. */
interface quelle_mit_faelligkeiten {
    /**
     * @param int $lernendeid
     * @return faelligkeit[]
     */
    public function get_faelligkeiten(int $lernendeid): array;
}

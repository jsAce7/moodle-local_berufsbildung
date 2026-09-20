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
 * A dated action supplied by an optional training-record source.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\nachweis;

/**
 * A source-independent due item.
 */
final class faelligkeit {
    /** @var string Stable source key. */
    public readonly string $quellekey;
    /** @var string Description for a supervising person. */
    public readonly string $bezeichnung;
    /** @var int Deadline timestamp. */
    public readonly int $datum;
    /** @var string Destination for the action. */
    public readonly string $url;
    // Keep the property name used by source plugins.
    // phpcs:disable moodle.NamingConventions.ValidVariableName.MemberNameUnderscore
    /** @var string Description when viewing one's own due dates. */
    public readonly string $eigene_bezeichnung;
    // phpcs:enable moodle.NamingConventions.ValidVariableName.MemberNameUnderscore

    // Preserve the public constructor parameter name for named arguments.
    // phpcs:disable moodle.NamingConventions.ValidVariableName.VariableNameUnderscore
    /**
     * Create a dated action with an optional description for the learner.
     *
     * @param string $quellekey Stable source key
     * @param string $bezeichnung Human-readable action
     * @param int $datum Deadline timestamp
     * @param string $url Destination for the action
     * @param string|null $eigene_bezeichnung Optional description for the learner
     */
    public function __construct(
        string $quellekey,
        string $bezeichnung,
        int $datum,
        string $url,
        ?string $eigene_bezeichnung = null
    ) {
        $this->quellekey = $quellekey;
        $this->bezeichnung = $bezeichnung;
        $this->datum = $datum;
        $this->url = $url;
        $this->eigene_bezeichnung = $eigene_bezeichnung ?? $bezeichnung;
    }
    // phpcs:enable moodle.NamingConventions.ValidVariableName.VariableNameUnderscore
}

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
    /** @var string The learner's own task, e.g. submitting or giving feedback. */
    public const AUFGABE_LERNENDE = 'lernende';
    /** @var string A task of the supervising person, e.g. writing a report or reviewing. */
    public const AUFGABE_ZUSTAENDIGE = 'zustaendige';

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
    /** @var bool Whether the learner sees this item among their own due dates. */
    public readonly bool $fuer_lernende;
    // phpcs:enable moodle.NamingConventions.ValidVariableName.MemberNameUnderscore
    /**
     * @var string|null Whose task this is (AUFGABE_*), null if the source does
     *                  not say. Decides whose calendar shows it, see
     *                  service\termin_service: the overview pages show every
     *                  due item, the calendar only one's own tasks.
     */
    public readonly ?string $aufgabe;
    /** @var int|null Date for the calendar if it differs from the deadline, e.g. the end of probation. */
    public readonly ?int $kalenderdatum;
    /**
     * @var string Description in the calendar of a supervising person,
     *             e.g. one that names the calendar date. Defaults to $bezeichnung.
     */
    public readonly string $kalenderbezeichnung;

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
     * @param bool $fuer_lernende False for tasks of the supervising person only
     * @param string|null $aufgabe Whose task this is (AUFGABE_*), null if unspecified
     * @param int|null $kalenderdatum Date for the calendar, null for the deadline
     * @param string|null $kalenderbezeichnung Description in the calendar of a supervising person, null for $bezeichnung
     */
    public function __construct(
        string $quellekey,
        string $bezeichnung,
        int $datum,
        string $url,
        ?string $eigene_bezeichnung = null,
        bool $fuer_lernende = true,
        ?string $aufgabe = null,
        ?int $kalenderdatum = null,
        ?string $kalenderbezeichnung = null
    ) {
        if ($aufgabe !== null && !in_array($aufgabe, [self::AUFGABE_LERNENDE, self::AUFGABE_ZUSTAENDIGE], true)) {
            throw new \coding_exception('Unbekannte Aufgabe: ' . $aufgabe);
        }

        $this->quellekey = $quellekey;
        $this->bezeichnung = $bezeichnung;
        $this->datum = $datum;
        $this->url = $url;
        $this->eigene_bezeichnung = $eigene_bezeichnung ?? $bezeichnung;
        $this->fuer_lernende = $fuer_lernende;
        $this->aufgabe = $aufgabe;
        $this->kalenderdatum = $kalenderdatum;
        $this->kalenderbezeichnung = $kalenderbezeichnung ?? $bezeichnung;
    }

    /**
     * Whether this item belongs in the calendar of the learner (true) or of
     * a responsible person (false). Without $aufgabe both see it, as on the
     * overview pages - the learner only if $fuer_lernende.
     *
     * @param bool $alslernende
     * @return bool
     */
    public function im_kalender_von(bool $alslernende): bool {
        if ($alslernende) {
            return $this->fuer_lernende && $this->aufgabe !== self::AUFGABE_ZUSTAENDIGE;
        }

        return $this->aufgabe !== self::AUFGABE_LERNENDE;
    }

    /**
     * Timestamp of the calendar event: the end of the due day (or of the
     * calendar date), since a deadline is overdue only from the next day
     * (ueberfaellig_ab()). A date without time would otherwise appear at
     * midnight at the start of its day.
     *
     * @return int
     */
    public function kalenderzeitpunkt(): int {
        return self::ueberfaellig_ab($this->kalenderdatum ?? $this->datum) - 1;
    }
    // phpcs:enable moodle.NamingConventions.ValidVariableName.VariableNameUnderscore

    /**
     * Whether the deadline has passed. The due day itself still counts as
     * within the deadline, see ueberfaellig_ab().
     *
     * @param int|null $jetzt Timestamp, null for now
     * @return bool
     */
    public function ist_ueberfaellig(?int $jetzt = null): bool {
        return ($jetzt ?? time()) >= self::ueberfaellig_ab($this->datum);
    }

    /**
     * When a deadline becomes overdue: at the start of the day after it.
     * A deadline at midnight (a date without time) would otherwise be
     * overdue for its whole due day. Calculated in the server's time zone,
     * so pages and scheduled tasks agree on the day for everyone. A
     * deadline at the last second of a day becomes overdue one second
     * later, as before.
     *
     * @param int $datum Deadline timestamp
     * @return int Timestamp
     */
    public static function ueberfaellig_ab(int $datum): int {
        $tag = new \DateTime('@' . $datum);
        $tag->setTimezone(\core_date::get_server_timezone_object());
        $tag->setTime(0, 0);
        $tag->modify('+1 day');

        return $tag->getTimestamp();
    }
}

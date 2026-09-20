<?php
// This file is part of Moodle - http://moodle.org/

/**
 * A dated action supplied by an optional training-record source.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\nachweis;

/** A source-independent due item. */
final class faelligkeit {
    /** @var string Stable source key. */
    public readonly string $quellekey;
    /** @var string Description for a supervising person. */
    public readonly string $bezeichnung;
    /** @var int Deadline timestamp. */
    public readonly int $datum;
    /** @var string Destination for the action. */
    public readonly string $url;
    /** @var string Description when viewing one's own due dates. */
    public readonly string $eigene_bezeichnung;

    /**
     * @param string $quellekey Stable source key
     * @param string $bezeichnung Human-readable action
     * @param int $datum Deadline timestamp
     * @param string $url Destination for the action
     * @param string|null $eigene_bezeichnung Optional description for the learner
     */
    public function __construct(string $quellekey, string $bezeichnung, int $datum, string $url, ?string $eigene_bezeichnung = null) {
        $this->quellekey = $quellekey;
        $this->bezeichnung = $bezeichnung;
        $this->datum = $datum;
        $this->url = $url;
        $this->eigene_bezeichnung = $eigene_bezeichnung ?? $bezeichnung;
    }
}

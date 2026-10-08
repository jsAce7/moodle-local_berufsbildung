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
 * Wertobjekt fuer einen Menuepunkt eines aufsetzenden Plugins.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\navigation;

use moodle_url;

/**
 * Ein Eintrag in einem der drei Menues der primaeren Navigation. Ein
 * aufsetzendes Plugin liefert seine Eintraege ueber den Callback
 * <plugin>_berufsbildung_navigation() in seiner lib.php, und zwar nur die,
 * deren Seite die angemeldete Person oeffnen darf - dieses Plugin prueft
 * keine fremden Rechte (siehe hook_callbacks::primary_extend()).
 *
 * Einzelne readonly-Properties statt einer readonly-Klasse, damit die
 * Klasse auch unter PHP 8.1 (Moodle 4.5) funktioniert.
 */
class menuepunkt {
    /** Unter "Meine Lehre", nur fuer Personen, denen "Meine Lehre" erscheint. */
    public const MEINE_LEHRE = 'meine_lehre';

    /** Unter "Meine Lernenden", nur fuer Personen mit eigenen Lernenden. */
    public const MEINE_LERNENDEN = 'meine_lernenden';

    /** Unter "Berufsbildung": Uebersichten ueber alle Lernenden, fuer die Leitung. */
    public const BERUFSBILDUNG = 'berufsbildung';

    /**
     * Konstruktor.
     *
     * @param string $menue self::MEINE_LEHRE, self::MEINE_LERNENDEN oder self::BERUFSBILDUNG
     * @param string $text Beschriftung
     * @param moodle_url $url Ziel
     * @param string $schluessel Eindeutiger Schluessel des Knotens, z.B. 'local_uekkn_jahrgaenge'
     */
    public function __construct(
        /** @var string Menue, in das der Punkt gehoert. */
        public readonly string $menue,
        /** @var string Beschriftung. */
        public readonly string $text,
        /** @var moodle_url Ziel. */
        public readonly moodle_url $url,
        /** @var string Eindeutiger Schluessel des Knotens. */
        public readonly string $schluessel,
    ) {
        if (!in_array($menue, [self::MEINE_LEHRE, self::MEINE_LERNENDEN, self::BERUFSBILDUNG], true)) {
            throw new \coding_exception('Unbekanntes Menue: ' . $menue);
        }
    }
}

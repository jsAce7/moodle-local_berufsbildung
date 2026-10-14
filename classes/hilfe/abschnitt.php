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
 * Wertobjekt fuer einen Abschnitt der Hilfeseite.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\hilfe;

/**
 * Eine Anleitung auf hilfe.php, z.B. "Einen Bildungsbericht erfassen".
 * Ein aufsetzendes Plugin liefert seine Abschnitte ueber den Callback
 * <plugin>_berufsbildung_hilfe() in seiner lib.php, und zwar nur die, deren
 * Seiten die angemeldete Person oeffnen kann - wie bei den Menuepunkten
 * prueft dieses Plugin keine fremden Rechte (siehe sammlung).
 *
 * Der Text steht in einem Mustache-Template des liefernden Plugins, nicht in
 * Sprach-Strings: die Hilfe gibt es nur auf Deutsch, und ein Absatz mit
 * Aufzaehlung und Links liesse sich als String kaum pflegen.
 *
 * Einzelne readonly-Properties statt einer readonly-Klasse, damit die
 * Klasse auch unter PHP 8.1 (Moodle 4.5) funktioniert.
 */
class abschnitt {
    /** Grundbegriffe, die Lernende und Berufsbildner/innen gleichermassen brauchen. */
    public const GRUNDLAGEN = 'grundlagen';

    /** Fuer Lernende. */
    public const LERNENDE = 'lernende';

    /** Fuer Berufsbildner/innen. */
    public const BERUFSBILDNER = 'berufsbildner';

    /** Fuer Kursleitungen der ueberbetrieblichen Kurse, im Moodle-Kurs. */
    public const KURSLEITUNG = 'kursleitung';

    /** Ausbildungsplanung: Bloecke, Kompetenzen, Versetzungsplan. */
    public const PLANUNG = 'planung';

    /** Leitung und Ausbildungsadministration: Uebersichten ueber alle Lernenden, Zuordnungen. */
    public const LEITUNG = 'leitung';

    /** Systemadministration: Einstellungen, Rollen, Einrichtung. */
    public const ADMINISTRATION = 'administration';

    /** Die Zielgruppen in der Reihenfolge der Hilfeseite. */
    public const ZIELGRUPPEN = [
        self::GRUNDLAGEN,
        self::LERNENDE,
        self::BERUFSBILDNER,
        self::KURSLEITUNG,
        self::PLANUNG,
        self::LEITUNG,
        self::ADMINISTRATION,
    ];

    /**
     * Konstruktor.
     *
     * @param string $zielgruppe Eine der Konstanten aus self::ZIELGRUPPEN
     * @param string $anker Sprungmarke auf hilfe.php, mit dem Plugin als
     *        Praefix, z.B. 'bildungsbericht-erfassen'; nur a-z, 0-9 und "-"
     * @param string $titel Ueberschrift des Abschnitts
     * @param string $template Template mit dem Text, z.B. 'local_bildungsbericht/hilfe/erfassen'
     * @param array $kontext Daten fuer das Template, etwa URLs
     * @param int $reihenfolge Platz innerhalb der Zielgruppe, kleinere Zahlen
     *        zuerst, bei Gleichstand in der Reihenfolge der Meldung. Dieses
     *        Plugin verwendet 0, damit seine Grundlagen vorne stehen.
     */
    public function __construct(
        /** @var string Zielgruppe. */
        public readonly string $zielgruppe,
        /** @var string Sprungmarke. */
        public readonly string $anker,
        /** @var string Ueberschrift. */
        public readonly string $titel,
        /** @var string Template mit dem Text. */
        public readonly string $template,
        /** @var array Daten fuer das Template. */
        public readonly array $kontext = [],
        /** @var int Platz innerhalb der Zielgruppe. */
        public readonly int $reihenfolge = 0,
    ) {
        if (!in_array($zielgruppe, self::ZIELGRUPPEN, true)) {
            throw new \coding_exception('Unbekannte Zielgruppe: ' . $zielgruppe);
        }
        if (!preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $anker)) {
            throw new \coding_exception('Ungueltiger Anker: ' . $anker);
        }
    }
}

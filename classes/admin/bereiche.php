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
 * Bereiche unter Website-Administration ▸ Berufsbildung.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\admin;

use admin_category;
use admin_root;
use coding_exception;
use lang_string;

/**
 * Legt die Bereiche der Kategorie "Berufsbildung" im Admin-Baum an, fuer
 * dieses Plugin und die aufsetzenden.
 *
 * Moodle laedt die settings.php der lokalen Plugins sortiert nach ihrem
 * uebersetzten Namen, die Ladereihenfolge haengt also von der Sprache ab.
 * Jeder Bereich wird deshalb vor dem ersten bereits vorhandenen Bereich
 * eingefuegt, der laut self::REIHENFOLGE nach ihm kommt. So steht der Baum
 * in jeder Sprache gleich: zuerst Allgemeines, dann die Fachthemen.
 */
class bereiche {
    /** Die Kategorie "Berufsbildung" selbst. */
    public const OBERKATEGORIE = 'ausbildungsverwaltung';

    /** Allgemeine Einstellungen und Aufbewahrungspflichten. */
    public const ALLGEMEIN = 'ausbildungsverwaltung_system';

    /** Zuordnungen und Verknuepfungen mit globalen Gruppen. */
    public const ZUORDNUNGEN = 'ausbildungsverwaltung_organisation';

    /** Ausbildungsbloecke, Kompetenzrahmen und Versetzungsplan. */
    public const PLANUNG = 'ausbildungsverwaltung_planung';

    /** Bereich von local_bildungsbericht. */
    public const BILDUNGSBERICHT = 'ausbildungsverwaltung_bildungsbericht';

    /** Bereich von local_lerndokumentation. */
    public const LERNDOKUMENTATION = 'ausbildungsverwaltung_lerndokumentation';

    /** Bereich von local_uekkn. */
    public const UEK = 'ausbildungsverwaltung_uek';

    /** Reihenfolge der Bereiche im Admin-Baum. */
    private const REIHENFOLGE = [
        self::ALLGEMEIN,
        self::ZUORDNUNGEN,
        self::PLANUNG,
        self::BILDUNGSBERICHT,
        self::LERNDOKUMENTATION,
        self::UEK,
    ];

    /**
     * Legt einen Bereich an, falls er noch fehlt, samt der Kategorie
     * "Berufsbildung" darueber.
     *
     * @param admin_root $admin Der Admin-Baum ($ADMIN in settings.php)
     * @param string $name Eine der Konstanten dieser Klasse
     * @param lang_string|string $titel Angezeigter Name des Bereichs
     * @return void
     */
    public static function anlegen(admin_root $admin, string $name, lang_string|string $titel): void {
        $position = array_search($name, self::REIHENFOLGE, true);
        if ($position === false) {
            throw new coding_exception('Unbekannter Bereich der Berufsbildung: ' . $name);
        }

        if (!$admin->locate(self::OBERKATEGORIE)) {
            $admin->add('root', new admin_category(
                self::OBERKATEGORIE,
                new lang_string('admin:uebersicht', 'local_berufsbildung')
            ), 'ai');
        }
        if ($admin->locate($name)) {
            return;
        }

        $davor = null;
        foreach (array_slice(self::REIHENFOLGE, $position + 1) as $nachfolger) {
            if ($admin->locate($nachfolger)) {
                $davor = $nachfolger;
                break;
            }
        }
        $admin->add(self::OBERKATEGORIE, new admin_category($name, $titel), $davor);
    }
}

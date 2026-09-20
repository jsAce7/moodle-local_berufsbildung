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
 * Rendert eine Roster-Kachel fuer meine_lernenden.php.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\output;

use core_text;
use local_berufsbildung\ausbildungsstand;

/**
 * Kompakte Zusammenfassung als <summary>, volle Details (Luecken-
 * Aufschluesselung, Taetigkeiten, Profil-Link) erst bei Bedarf
 * ausgeklappt - natives <details>-Element, kein JavaScript noetig.
 */
class lernenden_kachel {
    /**
     * Baut die Daten fuer das Template zusammen.
     *
     * @param string $name Vollstaendiger Name, bereits durch fullname() formatiert
     * @param ?ausbildungsstand $stand null, wenn Lehre nicht begonnen oder beendet
     * @param int $anzahlluecken 0 unterdrueckt das Luecken-Badge
     * @param int $anzahltaetigkeiten
     * @param string $detailhtml Bereits gerendertes HTML fuer den ausgeklappten Bereich
     *                            (Luecken-Detail, Taetigkeitenliste, Profil-Link)
     * @param string|null $bildhtml Bereits gerendertes Profilbild, siehe
     *        core_renderer::user_picture(). Null, wenn die Person keines
     *        hinterlegt hat - dann stehen die Initialen dort, und nicht
     *        die fuer alle gleiche graue Silhouette.
     * @param string|null $einsatzname Ausbildungsblock, in dem die Person
     *        gerade steht, siehe api::get_aktueller_einsatz(). Null, wenn
     *        zum Stichtag kein Einsatz laeuft oder kein Versetzungsplan
     *        vorliegt - die Spalte bleibt dann leer und haelt trotzdem
     *        ihre Breite, damit die Zeilen untereinander buendig bleiben.
     * @param int $anzahlueberfaellig Nur überfällige Aufgaben erscheinen
     *        bereits im geschlossenen Header; künftige Fristen stehen im
     *        Detail, damit die Übersicht nicht zur Aufgabenliste wird.
     * @param bool $istextern Externe üK-Teilnahme statt regulärer Lehre.
     */
    public static function render(
        string $name,
        ?ausbildungsstand $stand,
        int $anzahlluecken,
        int $anzahltaetigkeiten,
        string $detailhtml,
        ?string $bildhtml = null,
        ?string $einsatzname = null,
        int $anzahlueberfaellig = 0,
        bool $istextern = false
    ): string {
        global $OUTPUT;

        return $OUTPUT->render_from_template('local_berufsbildung/lernenden_kachel', [
            'name' => $name,
            'hasbild' => $bildhtml !== null,
            'bildhtml' => $bildhtml ?? '',
            'initialen' => self::initialen($name),
            'haseinsatz' => $einsatzname !== null,
            'einsatzname' => $einsatzname ?? '',
            'hatstand' => $stand !== null,
            'ausbildungsstand' => $stand !== null ? get_string('form:ausbildungsstand', 'local_berufsbildung', (object) [
                'beruf' => $stand->beruf,
                'lehrjahr' => $stand->lehrjahr,
                'semester' => $stand->semester,
            ]) : '',
            'stepper' => $stand !== null ? semester_stepper::render($stand->semester, $stand->gesamtsemester, kompakt: true) : '',
            'hasluecken' => $anzahlluecken > 0,
            'anzahlluecken' => $anzahlluecken,
            'hasueberfaellig' => $anzahlueberfaellig > 0,
            'anzahlueberfaellig' => $anzahlueberfaellig,
            'hasextern' => $istextern,
            'anzahltaetigkeiten' => $anzahltaetigkeiten,
            'detailhtml' => $detailhtml,
        ]);
    }

    /**
     * Erste Buchstaben von Vor- und Nachnamen, z.B. "Elena Furrer" -> "EF".
     * Ein einzelnes Wort liefert dessen ersten Buchstaben.
     *
     * @param string $name
     */
    private static function initialen(string $name): string {
        $woerter = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY);
        if (empty($woerter)) {
            return '';
        }

        $erstes = core_text::strtoupper(core_text::substr($woerter[0], 0, 1));
        if (count($woerter) === 1) {
            return $erstes;
        }

        $letztes = core_text::strtoupper(core_text::substr($woerter[count($woerter) - 1], 0, 1));

        return $erstes . $letztes;
    }
}

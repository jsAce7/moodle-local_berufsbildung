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
 * Wandelt eine ISO-8601-Kalenderwoche in einen Zeitraum um.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\versetzungsplan;

use coding_exception;
use DateTime;

/**
 * Kennt nur das Format 'JJJJ-Wnn', keine anderen Kalenderwochenschreibweisen.
 * Nutzt DateTime::setISODate(), das die ISO-8601-Wochendefinition (Woche 1
 * enthaelt den ersten Donnerstag des Jahres, Wochenbeginn Montag) bereits
 * korrekt implementiert - keine eigene Kalenderarithmetik noetig.
 */
class kw_converter {

    /**
     * @param string $kw z.B. '2027-W03'
     * @return array{0: int, 1: int} [von, bis] - Montag 00:00:00 bis Sonntag 23:59:59
     */
    public function zu_zeitraum(string $kw): array {
        $jahrwoche = $this->zerlegen($kw);

        $montag = new DateTime();
        $montag->setISODate($jahrwoche[0], $jahrwoche[1], 1);
        $montag->setTime(0, 0, 0);

        $sonntag = clone $montag;
        $sonntag->modify('+6 days');
        $sonntag->setTime(23, 59, 59);

        return [$montag->getTimestamp(), $sonntag->getTimestamp()];
    }

    /**
     * Nur fuer den Formatvergleich zweier Kalenderwochen (z.B. "liegt kw_bis
     * nicht vor kw_von") - vergleicht Jahr und Woche numerisch, ohne den
     * Umweg ueber einen Zeitraum.
     *
     * @param string $kw
     * @return int Jahr * 100 + Woche, aufsteigend vergleichbar
     */
    public function sortierschluessel(string $kw): int {
        $jahrwoche = $this->zerlegen($kw);

        return $jahrwoche[0] * 100 + $jahrwoche[1];
    }

    /**
     * @param string $kw
     * @return array{0: int, 1: int} [Jahr, Woche]
     */
    private function zerlegen(string $kw): array {
        if (!preg_match('/^(\d{4})-W(\d{2})$/', $kw, $treffer)) {
            throw new coding_exception("Ungueltiges Kalenderwochenformat: '{$kw}', erwartet 'JJJJ-Wnn'");
        }

        return [(int) $treffer[1], (int) $treffer[2]];
    }
}

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
 * Umrechnung zwischen Datumsfeld und «gueltig bis».
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung;

use core_date;
use DateTimeImmutable;

/**
 * Ein date_selector liefert 00:00:00 des gewaehlten Tages. Ein
 * «gueltig bis» nennt dagegen den letzten gueltigen Zeitpunkt (Bedingung
 * gueltig_bis >= Stichtag), der gewaehlte Tag soll also noch ganz zaehlen.
 * Beim Speichern wird darum auf das Tagesende gerechnet, beim Vorbelegen
 * des Formulars zurueck auf den Tagesbeginn.
 *
 * Gerechnet wird in der Zeitzone der angemeldeten Person, wie im
 * date_selector selbst; so stimmt auch ein Tag mit Zeitumstellung.
 */
class datum {
    /**
     * Letzte Sekunde des Tages, in dem der Zeitpunkt liegt.
     *
     * @param int $zeitpunkt Timestamp, in der Regel 00:00:00 aus einem date_selector
     * @return int
     */
    public static function tagesende(int $zeitpunkt): int {
        $tag = (new DateTimeImmutable('@' . $zeitpunkt))
            ->setTimezone(core_date::get_user_timezone_object())
            ->setTime(0, 0);

        return $tag->modify('+1 day')->getTimestamp() - 1;
    }

    /**
     * 00:00:00 des Tages, in dem der Zeitpunkt liegt - zum Vorbelegen eines
     * date_selector mit einem gespeicherten «gueltig bis».
     *
     * @param int $zeitpunkt
     * @return int
     */
    public static function tagesbeginn(int $zeitpunkt): int {
        return (new DateTimeImmutable('@' . $zeitpunkt))
            ->setTimezone(core_date::get_user_timezone_object())
            ->setTime(0, 0)
            ->getTimestamp();
    }
}

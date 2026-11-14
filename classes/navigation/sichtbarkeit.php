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
 * Sichtbarkeit der eigenen Einstiege in der Navigation, gecacht.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\navigation;

use cache;
use local_berufsbildung\api;
use local_berufsbildung\service\zuordnung_service;

/**
 * Primaere Navigation und Benutzermenue fragen auf jeder Seite, ob eine
 * Person "Meine Lehre" bzw. "Meine Lernenden" sieht - auch Personen ohne
 * jeden Bezug zur Berufsbildung. Ungecacht kostete das je Seite mehrere
 * Abfragen (Teilnahmeprofil, Profilfelder, Zuordnungen).
 *
 * Der Cache (db/caches.php, 'navigation') wird bei jedem Schreiben einer
 * Zuordnung oder Teilnahmeart ganz geleert (vergiss()) - das ist selten.
 * Was sich ohne Schreibvorgang aendert, etwa der Beginn einer vorab
 * erfassten Zuordnung oder ein Profilfeld, zeigt sich nach der Lebensdauer
 * des Caches (fuenf Minuten).
 */
class sichtbarkeit {
    /**
     * Ob "Meine Lehre" erscheint - siehe hook_callbacks::sieht_meine_lehre().
     *
     * @param int $userid
     * @return bool
     */
    public static function meine_lehre(int $userid): bool {
        return self::stand($userid)['lehre'];
    }

    /**
     * Ob "Meine Lernenden" erscheint - siehe hook_callbacks::sieht_meine_lernenden().
     *
     * @param int $userid
     * @return bool
     */
    public static function meine_lernenden(int $userid): bool {
        return self::stand($userid)['lernende'];
    }

    /**
     * Leert den Cache, nach jeder Aenderung an Zuordnungen oder Teilnahmeart.
     */
    public static function vergiss(): void {
        cache::make('local_berufsbildung', 'navigation')->purge();
    }

    /**
     * Vergisst den Stand einer einzelnen Person, etwa nach einer
     * Profilaenderung.
     *
     * @param int $userid
     */
    public static function vergiss_person(int $userid): void {
        cache::make('local_berufsbildung', 'navigation')->delete($userid);
    }

    /**
     * Beide Werte einer Person, aus dem Cache oder frisch berechnet.
     *
     * @param int $userid
     * @return array{lehre: bool, lernende: bool}
     */
    private static function stand(int $userid): array {
        $cache = cache::make('local_berufsbildung', 'navigation');
        $stand = $cache->get($userid);
        if (is_array($stand)) {
            return $stand;
        }

        $stand = [
            'lehre' => !api::ist_uek_extern($userid) && api::get_ausbildungsphase($userid) !== api::PHASE_UNBEKANNT,
            'lernende' => (new zuordnung_service())->hat_laufende_als_berufsbildner($userid),
        ];
        $cache->set($userid, $stand);

        return $stand;
    }
}

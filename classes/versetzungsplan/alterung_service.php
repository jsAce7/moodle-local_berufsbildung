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
 * Alterung des Versetzungsplans - die Rueckversicherung aus docs/konzept.md
 * §5.4 und §5.5.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\versetzungsplan;

use local_berufsbildung\persistent\plan_import;

/**
 * Erkennt einen veralteten Versetzungsplan und meldet ihn auf Wunsch den
 * Site-Admins.
 *
 * Liefert das Skript nicht mehr - Token abgelaufen, Endpunkt nicht
 * erreichbar -, sieht Moodle keinen Fehler, nur das Ausbleiben. Der Hinweis
 * auf import_plan.php allein reicht dafuer nicht: im Regelbetrieb oeffnet
 * die Seite niemand. Deshalb zusaetzlich eine Nachricht, einmal je
 * veraltetem Import, bis wieder ein Import erfolgreich ist.
 */
class alterung_service {
    /** Nachrichtentyp, siehe db/messages.php. */
    public const NACHRICHT_ALTERUNG = 'versetzungsplan_alterung';

    /**
     * Konstruktor.
     *
     * @param import_service $importservice
     */
    public function __construct(
        /** @var import_service */
        private readonly import_service $importservice = new import_service()
    ) {
    }

    /**
     * Nach wie vielen Tagen ohne erfolgreichen Import der Plan als veraltet gilt.
     *
     * @return int
     */
    public function frist_tage(): int {
        $wert = get_config('local_berufsbildung', 'versetzungsplan_alterung_tage');

        return max(0, $wert !== false ? (int) $wert : 10);
    }

    /**
     * Der letzte erfolgreiche Import, wenn er zum Stichtag laenger als die
     * Frist zurueckliegt. Genau an der Frist gilt er noch als aktuell. Null
     * auch, wenn es noch nie einen gab: ohne Import ist der Plan keine
     * Voraussetzung (§5.8), also auch nicht veraltet.
     *
     * @param int|null $stichtag Timestamp, null bedeutet "jetzt"
     * @return plan_import|null
     */
    public function veralteter_import(?int $stichtag = null): ?plan_import {
        $stichtag ??= time();
        $letzter = $this->importservice->letzter_erfolgreicher_import();
        if ($letzter === null || (int) $letzter->get('zeitpunkt') >= $stichtag - $this->frist_tage() * DAYSECS) {
            return null;
        }

        return $letzter;
    }

    /**
     * Meldet einen veralteten Plan den Site-Admins, wenn die Einstellung
     * versetzungsplan_alterung_meldung eingeschaltet ist. Je veraltetem
     * Import nur einmal: erst ein neuer erfolgreicher Import, der seinerseits
     * veraltet, loest die naechste Meldung aus.
     *
     * @param int|null $stichtag Timestamp, null bedeutet "jetzt"
     * @return int Anzahl benachrichtigter Personen
     */
    public function melde_falls_veraltet(?int $stichtag = null): int {
        if (!get_config('local_berufsbildung', 'versetzungsplan_alterung_meldung')) {
            return 0;
        }

        $veraltet = $this->veralteter_import($stichtag);
        if ($veraltet === null) {
            return 0;
        }

        $importid = (int) $veraltet->get('id');
        if ((int) get_config('local_berufsbildung', 'alterung_gemeldet_importid') === $importid) {
            return 0;
        }

        $url = new \moodle_url('/local/berufsbildung/import_plan.php');
        $a = (object) [
            'zeitpunkt' => userdate((int) $veraltet->get('zeitpunkt')),
            'tage' => $this->frist_tage(),
            'url' => $url->out(false),
        ];

        $admins = get_admins();
        foreach ($admins as $admin) {
            $nachricht = new \core\message\message();
            $nachricht->component = 'local_berufsbildung';
            $nachricht->name = self::NACHRICHT_ALTERUNG;
            $nachricht->userfrom = \core_user::get_noreply_user();
            $nachricht->userto = $admin;
            $nachricht->subject = get_string('planimport:alterung_nachricht_betreff', 'local_berufsbildung');
            $nachricht->fullmessage = get_string('planimport:alterung_nachricht_text', 'local_berufsbildung', $a);
            $nachricht->fullmessageformat = FORMAT_PLAIN;
            $nachricht->fullmessagehtml = '';
            $nachricht->smallmessage = get_string('planimport:alterung_nachricht_kurz', 'local_berufsbildung', $a);
            $nachricht->notification = 1;
            $nachricht->contexturl = $url->out(false);
            $nachricht->contexturlname = get_string('planimport:titel', 'local_berufsbildung');
            message_send($nachricht);
        }

        set_config('alterung_gemeldet_importid', $importid, 'local_berufsbildung');

        return count($admins);
    }
}

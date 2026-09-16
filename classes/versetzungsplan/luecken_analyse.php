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
 * Handlungskompetenzen, die bis zu einem Stichtag in keinem betrieblichen
 * Einsatz vorkamen - siehe docs/plan.md §5.7.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\versetzungsplan;

use core_competency\competency;
use core_competency\competency_framework;
use local_berufsbildung\api;
use local_berufsbildung\bereich_abdeckung;
use local_berufsbildung\service\kompetenz_baum;

/**
 * Ein Vorschlag fuer die Ausbildungsplanung, keine Festlegung
 * (Architekturregel 6) - eine Kompetenz "ohne Abdeckung" kann trotzdem
 * anderweitig vermittelt worden sein, ausserhalb des importierten Plans.
 */
class luecken_analyse {
    /**
     * Handlungskompetenzen ohne Abdeckung, flach ueber alle Bereiche.
     *
     * @param int $lernendeid
     * @param int|null $stichtag Timestamp, null = jetzt
     * @return int[] competencyids ohne Abdeckung. Leer, wenn Beruf/Jahrgang
     *               fehlen, die Lehre zum Stichtag nicht laeuft, oder kein
     *               Kompetenzrahmen fuer den Beruf konfiguriert ist.
     */
    public function get_luecken(int $lernendeid, ?int $stichtag = null): array {
        $luecken = [];
        foreach ($this->get_abdeckung($lernendeid, $stichtag) as $abdeckung) {
            foreach ($abdeckung->luecken as $competencyid) {
                $luecken[] = $competencyid;
            }
        }

        return $luecken;
    }

    /**
     * Dieselbe Auswertung wie get_luecken(), aber nach
     * Handlungskompetenzbereich gruppiert und um die Bezugsgroesse
     * ergaenzt: "zwei von sechs offen" sagt fuer die Ausbildungsplanung
     * mehr als zwei Namen ohne Nenner.
     *
     * Bereiche, deren HK ausschliesslich Wahlpflicht sind, erscheinen
     * nicht - sie haetten sonst eine leere Bezugsgroesse.
     *
     * @param int $lernendeid
     * @param int|null $stichtag Timestamp, null = jetzt
     * @return bereich_abdeckung[] In der Reihenfolge des Kompetenzrahmens
     */
    public function get_abdeckung(int $lernendeid, ?int $stichtag = null): array {
        $ausbildungsstand = api::get_ausbildungsstand($lernendeid, $stichtag);
        if ($ausbildungsstand === null) {
            return [];
        }

        $frameworkidnumber = api::get_kompetenzrahmen_for_beruf($ausbildungsstand->beruf);
        if ($frameworkidnumber === null) {
            return [];
        }

        $framework = competency_framework::get_record(['idnumber' => $frameworkidnumber]);
        if (!$framework) {
            return [];
        }

        // Ausgewertet werden die Handlungskompetenzen (zweite Ebene, siehe
        // kompetenz_baum), nicht die obersten Handlungskompetenzbereiche -
        // sonst koennte innerhalb eines Bereichs eine einzelne fehlende HK
        // unbemerkt bleiben. Die LK darunter werden den Ausbildungsblöcken
        // zugeordnet und über plan_service auf ihre HK hochgerechnet.
        $wahlpflicht = array_flip(api::get_wahlpflicht_hk_for_beruf($ausbildungsstand->beruf));
        $rahmenkompetenzen = competency::get_records(['competencyframeworkid' => (int) $framework->get('id')], 'sortorder');

        // Die Bereiche vorab in Rahmenreihenfolge anlegen, damit die
        // Ausgabe der Gliederung des Rahmens folgt und nicht der
        // Reihenfolge, in der die einzelnen HK auftauchen.
        $bereiche = [];
        foreach ($rahmenkompetenzen as $kompetenz) {
            if ((int) $kompetenz->get('parentid') === 0) {
                $bereiche[(int) $kompetenz->get('id')] = ['soll' => 0, 'luecken' => []];
            }
        }

        $pflichtkompetenzen = array_filter(
            (new kompetenz_baum())->nur_handlungskompetenzen($rahmenkompetenzen),
            static fn (competency $kompetenz): bool => !isset($wahlpflicht[$kompetenz->get('idnumber')])
        );

        // Kein unterer Rand (0 = Unix-Epoche): der Versetzungsplan enthaelt
        // ohnehin nur Einsaetze aus der tatsaechlichen Lehrzeit dieser
        // Person, ein separat berechneter Lehrbeginn waere redundant.
        $ausgebildet = array_flip(api::get_ausgebildete_kompetenzen($lernendeid, 0, $stichtag ?? time()));

        foreach ($pflichtkompetenzen as $kompetenz) {
            $bereichid = (int) $kompetenz->get('parentid');
            $competencyid = (int) $kompetenz->get('id');

            $bereiche[$bereichid]['soll']++;
            if (!isset($ausgebildet[$competencyid])) {
                $bereiche[$bereichid]['luecken'][] = $competencyid;
            }
        }

        $abdeckungen = [];
        foreach ($bereiche as $bereichid => $zahlen) {
            if ($zahlen['soll'] === 0) {
                continue;
            }
            $abdeckungen[] = new bereich_abdeckung(
                bereichid: $bereichid,
                soll: $zahlen['soll'],
                luecken: $zahlen['luecken'],
            );
        }

        return $abdeckungen;
    }
}

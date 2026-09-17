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
 * Stand aller Handlungskompetenzen eines Kompetenzrahmens im importierten
 * Versetzungsplan - die Datengrundlage des Kompetenzrasters.
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
use local_berufsbildung\raster_bereich;
use local_berufsbildung\raster_kompetenz;
use local_berufsbildung\service\kompetenz_baum;

/**
 * Die vollstaendige Sicht auf den Rahmen: jede Handlungskompetenz mit
 * ihrem Stand, nicht nur die fehlenden. Damit laesst sich der Rahmen so
 * darstellen, wie er im offiziellen Bildungsplan steht - Bereiche als
 * Zeilen, Handlungskompetenzen als Spalten.
 *
 * Ein Vorschlag fuer die Ausbildungsplanung, keine Festlegung
 * (Architekturregel 6): eine Kompetenz ohne Abdeckung kann anderweitig
 * vermittelt worden sein, ausserhalb des importierten Plans.
 *
 * Bewusst die breitere Auswertung: luecken_analyse leitet ihr Ergebnis
 * hieraus ab, damit Liste und Raster nie auseinanderlaufen koennen.
 */
class raster_analyse {
    /**
     * Alle Handlungskompetenzen des Rahmens, nach Bereich gruppiert und
     * mit ihrem Stand zum Stichtag.
     *
     * @param int $lernendeid
     * @param int|null $stichtag Timestamp, null = jetzt
     * @return raster_bereich[] In der Reihenfolge des Kompetenzrahmens. Leer,
     *                          wenn Beruf/Jahrgang fehlen, die Lehre zum
     *                          Stichtag nicht laeuft, oder kein
     *                          Kompetenzrahmen konfiguriert ist.
     */
    public function get_raster(int $lernendeid, ?int $stichtag = null): array {
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

        $wahlpflicht = array_flip(api::get_wahlpflicht_hk_for_beruf($ausbildungsstand->beruf));
        $rahmenkompetenzen = competency::get_records(['competencyframeworkid' => (int) $framework->get('id')], 'sortorder');

        // Zwei Zeitraeume, damit sich "noch nicht" und "kommt noch"
        // unterscheiden lassen. Kein unterer Rand (0 = Unix-Epoche): der
        // Versetzungsplan enthaelt ohnehin nur Einsaetze dieser Person.
        // Seit der Import nur noch sein eigenes Lieferfenster ersetzt
        // (import_service::schreiben()), reicht der Bestand tatsaechlich
        // ueber die bisherige Lehrzeit und nicht nur ueber die letzte
        // Lieferung.
        $bis = $stichtag ?? time();
        $abgedeckt = array_flip(api::get_ausgebildete_kompetenzen($lernendeid, 0, $bis));
        $jemals = array_flip(api::get_ausgebildete_kompetenzen($lernendeid, 0, PHP_INT_MAX));

        // Die Bereiche vorab in Rahmenreihenfolge anlegen, damit die
        // Ausgabe der Gliederung des Rahmens folgt und nicht der
        // Reihenfolge, in der die einzelnen HK auftauchen.
        $bereiche = [];
        foreach ($rahmenkompetenzen as $kompetenz) {
            if ((int) $kompetenz->get('parentid') === 0) {
                $bereiche[(int) $kompetenz->get('id')] = [];
            }
        }

        foreach ((new kompetenz_baum())->nur_handlungskompetenzen($rahmenkompetenzen) as $kompetenz) {
            $competencyid = (int) $kompetenz->get('id');

            if (isset($abgedeckt[$competencyid])) {
                $status = raster_kompetenz::STATUS_ABGEDECKT;
            } else if (isset($jemals[$competencyid])) {
                $status = raster_kompetenz::STATUS_EINGEPLANT;
            } else {
                $status = raster_kompetenz::STATUS_OFFEN;
            }

            $bereiche[(int) $kompetenz->get('parentid')][] = new raster_kompetenz(
                competencyid: $competencyid,
                status: $status,
                istwahlpflicht: isset($wahlpflicht[$kompetenz->get('idnumber')]),
            );
        }

        $raster = [];
        foreach ($bereiche as $bereichid => $kompetenzen) {
            // Ein Bereich ohne Handlungskompetenzen waere eine leere Zeile -
            // im Bildungsplan gibt es das nicht, in einem unvollstaendig
            // gepflegten Rahmen schon.
            if (empty($kompetenzen)) {
                continue;
            }

            $raster[] = new raster_bereich(bereichid: $bereichid, kompetenzen: $kompetenzen);
        }

        return $raster;
    }

    /**
     * Ende des zuletzt eingeplanten Einsatzes - bis wohin der vorliegende
     * Plan ueberhaupt reicht.
     *
     * Ohne diese Angabe ist "offen" nicht interpretierbar: Eine
     * Handlungskompetenz, die in keinem Einsatz vorkommt, kann in einer
     * spaeteren Lieferung durchaus noch auftauchen. Der Horizont sagt, ab
     * wann das gilt.
     *
     * @param int $lernendeid
     * @return int|null Timestamp, null wenn kein Einsatz vorliegt
     */
    public function get_planungshorizont(int $lernendeid): ?int {
        $einsaetze = (new plan_service())->get_einsaetze($lernendeid);
        if (empty($einsaetze)) {
            return null;
        }

        return max(array_map(
            static fn ($einsatz): int => (int) $einsatz->get('bis'),
            $einsaetze
        ));
    }
}

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

        // Verglichen wird ueber das Kuerzel, nicht ueber die volle
        // ID-Nummer: der Rahmen-Praefix ("7777BE a.04") steht in jeder
        // Zeile gleich da, und wer ihn in der Einstellung anders oder gar
        // nicht schreibt, meint trotzdem dieselbe Handlungskompetenz.
        // Ein exakter Vergleich liess die Kennzeichnung stillschweigend
        // ins Leere laufen - alles erschien als Pflicht.
        $wahlpflicht = [];
        foreach (api::get_wahlpflicht_hk_for_beruf($ausbildungsstand->beruf) as $eintrag) {
            $wahlpflicht[$this->vergleichsschluessel($eintrag)] = true;
        }

        $rahmenkompetenzen = competency::get_records(['competencyframeworkid' => (int) $framework->get('id')], 'sortorder');

        // Zwei Zeitraeume, damit sich "noch nicht" und "kommt noch"
        // unterscheiden lassen. Kein unterer Rand (0 = Unix-Epoche): der
        // Versetzungsplan enthaelt ohnehin nur Einsaetze dieser Person.
        // Seit der Import nur noch sein eigenes Lieferfenster ersetzt
        // (import_service::schreiben()), reicht der Bestand tatsaechlich
        // ueber die bisherige Lehrzeit und nicht nur ueber die letzte
        // Lieferung.
        $bis = $stichtag ?? time();

        // Die Zuordnungen, wie sie an den Bloecken stehen - je Zeitraum
        // einmal. Die uebergeordneten Kompetenzen kommen aus dem bereits
        // geladenen Rahmen dazu, statt sie je Kompetenz nachzuladen: auf
        // "Meine Lernenden" laeuft das fuer jede Person. Ausserhalb des
        // Rahmens interessiert hier nichts.
        $planservice = new plan_service();
        $direktabgedeckt = array_flip($planservice->get_zugeordnete_kompetenzen($lernendeid, 0, $bis));
        $direktjemals = array_flip($planservice->get_zugeordnete_kompetenzen($lernendeid, 0, PHP_INT_MAX));

        $eltern = [];
        foreach ($rahmenkompetenzen as $kompetenz) {
            $eltern[(int) $kompetenz->get('id')] = (int) $kompetenz->get('parentid');
        }

        // Eine HK gilt als abgedeckt, sobald sie selbst oder eines ihrer
        // LK zugeordnet ist - dieselbe Regel wie get_ausgebildete_kompetenzen().
        $abgedeckt = $this->mit_vorfahren($direktabgedeckt, $eltern);
        $jemals = $this->mit_vorfahren($direktjemals, $eltern);

        $lkjehk = [];
        foreach ((new kompetenz_baum())->baum($rahmenkompetenzen) as $zweig) {
            foreach ($zweig['handlungskompetenzen'] as $eintrag) {
                $hkid = (int) $eintrag['kompetenz']->get('id');
                $lkjehk[$hkid] = [];
                foreach ($eintrag['leistungskriterien'] as $lk) {
                    $lkid = (int) $lk->get('id');
                    if ($this->zugeordnet_bis_hk($lkid, $hkid, $direktabgedeckt, $eltern)) {
                        $lkjehk[$hkid][$lkid] = raster_kompetenz::STATUS_ABGEDECKT;
                    } else if ($this->zugeordnet_bis_hk($lkid, $hkid, $direktjemals, $eltern)) {
                        $lkjehk[$hkid][$lkid] = raster_kompetenz::STATUS_EINGEPLANT;
                    } else {
                        $lkjehk[$hkid][$lkid] = raster_kompetenz::STATUS_OFFEN;
                    }
                }
            }
        }

        // Die Bereiche vorab in Rahmenreihenfolge anlegen, damit die
        // Ausgabe der Gliederung des Rahmens folgt und nicht der
        // Reihenfolge, in der die einzelnen HK auftauchen.
        $bereiche = [];
        $bereichkompetenzen = [];
        foreach ($rahmenkompetenzen as $kompetenz) {
            if ((int) $kompetenz->get('parentid') === 0) {
                $bereiche[(int) $kompetenz->get('id')] = [];
                $bereichkompetenzen[(int) $kompetenz->get('id')] = $kompetenz;
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
                istwahlpflicht: isset($wahlpflicht[$this->vergleichsschluessel((string) $kompetenz->get('idnumber'))]),
                leistungskriterien: $lkjehk[$competencyid] ?? [],
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

            $raster[] = new raster_bereich(
                bereichid: $bereichid,
                kompetenzen: $kompetenzen,
                kuerzel: kompetenz_baum::kuerzel($bereichkompetenzen[$bereichid]),
            );
        }

        return $raster;
    }

    /**
     * Die Kompetenzen und alle ihre Vorfahren im Rahmen.
     *
     * @param array $kompetenzen competencyid => beliebig
     * @param array $eltern competencyid => parentid, der ganze Rahmen
     * @return array competencyid => true
     */
    private function mit_vorfahren(array $kompetenzen, array $eltern): array {
        $alle = [];
        foreach (array_keys($kompetenzen) as $id) {
            while ($id !== 0 && !isset($alle[$id])) {
                $alle[$id] = true;
                $id = $eltern[$id] ?? 0;
            }
        }

        return $alle;
    }

    /**
     * Ist ein Leistungskriterium selbst zugeordnet, oder eine Kompetenz
     * zwischen ihm und seiner Handlungskompetenz, die HK eingeschlossen?
     * Eine als Ganzes zugeordnete HK deckt alle ihre LK ab.
     *
     * @param int $lkid
     * @param int $hkid
     * @param array $zugeordnet competencyid => beliebig
     * @param array $eltern competencyid => parentid
     */
    private function zugeordnet_bis_hk(int $lkid, int $hkid, array $zugeordnet, array $eltern): bool {
        $id = $lkid;
        while ($id !== 0) {
            if (isset($zugeordnet[$id])) {
                return true;
            }
            if ($id === $hkid) {
                return false;
            }
            $id = $eltern[$id] ?? 0;
        }

        return false;
    }

    /**
     * Schluessel, unter dem eine ID-Nummer mit der Wahlpflicht-Einstellung
     * abgeglichen wird: das Kuerzel ohne Rahmen-Praefix, klein geschrieben.
     *
     * Damit passen "7777BE a.04", "7777 a.04" und "a.04" auf dieselbe
     * Handlungskompetenz - der Praefix ist Rahmensache, die Einstellung
     * beschreibt den Bildungsplan.
     *
     * @param string $idnumber
     */
    private function vergleichsschluessel(string $idnumber): string {
        return \core_text::strtolower(kompetenz_baum::kuerzel_aus_idnumber($idnumber));
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

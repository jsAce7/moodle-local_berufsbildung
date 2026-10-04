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
 * Einsatz vorkamen - siehe docs/konzept.md §5.7.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\versetzungsplan;

use local_berufsbildung\bereich_abdeckung;
use local_berufsbildung\raster_bereich;

/**
 * Ein Vorschlag fuer die Ausbildungsplanung, keine Festlegung
 * (Architekturregel 6) - eine Kompetenz "ohne Abdeckung" kann trotzdem
 * anderweitig vermittelt worden sein, ausserhalb des importierten Plans.
 *
 * Die Auswertung selbst macht raster_analyse; diese Klasse reduziert
 * deren vollstaendiges Bild auf die Planungssicht "was fehlt noch".
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
        return $this->aus_raster((new raster_analyse())->get_raster($lernendeid, $stichtag));
    }

    /**
     * Dieselbe Reduktion wie get_abdeckung(), aber auf ein bereits
     * berechnetes Raster - ohne Datenbankzugriff.
     *
     * Fuer Seiten, die beide Darstellungen zeigen (Liste und Raster):
     * sie berechnen das Raster einmal und leiten die Liste daraus ab,
     * statt dieselbe Auswertung zweimal anzustossen.
     *
     * @param raster_bereich[] $raster Ergebnis von raster_analyse::get_raster()
     * @return bereich_abdeckung[] In derselben Reihenfolge
     */
    public function aus_raster(array $raster): array {
        $abdeckungen = [];

        foreach ($raster as $bereich) {
            // Bereiche, deren HK ausschliesslich Wahlpflicht sind,
            // erscheinen nicht - sie haetten sonst eine leere
            // Bezugsgroesse. Im Raster selbst bleiben sie sichtbar.
            if ($bereich->soll() === 0) {
                continue;
            }

            $abdeckungen[] = new bereich_abdeckung(
                bereichid: $bereich->bereichid,
                soll: $bereich->soll(),
                luecken: $bereich->luecken(),
            );
        }

        return $abdeckungen;
    }
}

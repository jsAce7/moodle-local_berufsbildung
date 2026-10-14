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
 * Wertobjekt fuer eine Gruppe von Wahlpflicht-Handlungskompetenzen.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung;

/**
 * Wie viele Wahlpflicht-HK der Bildungsplan aus welchen Bereichen verlangt,
 * z. B. eine aus den Bereichen a, b und c zusammen und eine aus d. Eine
 * Gruppe kann statt eines ganzen Bereichs auch einzelne HK nennen ("d.06").
 *
 * Wird aus der Einstellung berechnet, nicht gespeichert - deshalb kein
 * Persistent. Einzelne readonly-Properties statt einer readonly-Klasse,
 * damit die Klasse auch unter PHP 8.1 (Moodle 4.5) funktioniert.
 */
class wahlpflicht_gruppe {
    /**
     * Konstruktor.
     *
     * @param string[] $bereiche Kuerzel der Handlungskompetenzbereiche ("a") oder einzelner
     *                           HK ("d.06"), klein geschrieben; leer heisst: alle Bereiche
     * @param int $anzahl Wie viele Wahlpflicht-HK aus diesen Bereichen verlangt sind
     */
    public function __construct(
        /** @var string[] Kuerzel der Bereiche oder einzelner HK, leer fuer alle. */
        public readonly array $bereiche,
        /** @var int Verlangte Anzahl Wahlpflicht-HK. */
        public readonly int $anzahl,
    ) {
    }

    /**
     * Nennt die Gruppe dieses Kuerzel, oder gilt sie fuer den ganzen Beruf?
     *
     * @param string $kuerzel Kuerzel eines Bereichs ("a") oder einer HK ("d.06")
     */
    public function umfasst(string $kuerzel): bool {
        return $this->bereiche === [] || in_array(\core_text::strtolower(trim($kuerzel)), $this->bereiche, true);
    }

    /**
     * Zaehlt eine HK zu dieser Gruppe - weil die Gruppe ihren Bereich nennt
     * oder sie selbst?
     *
     * @param raster_bereich $bereich Der Bereich, in dem die HK steht
     * @param raster_kompetenz $kompetenz
     */
    public function umfasst_kompetenz(raster_bereich $bereich, raster_kompetenz $kompetenz): bool {
        return $this->umfasst($bereich->kuerzel)
            || ($kompetenz->kuerzel !== '' && $this->umfasst($kompetenz->kuerzel));
    }

    /**
     * Liegt jede HK der anderen Gruppe auch in dieser? Verglichen werden
     * die Kuerzel, nicht ein Raster: eine HK "d.06" liegt im Bereich "d".
     *
     * @param wahlpflicht_gruppe $andere
     */
    public function enthaelt(wahlpflicht_gruppe $andere): bool {
        if ($andere->bereiche === []) {
            return $this->bereiche === [];
        }
        foreach ($andere->bereiche as $kuerzel) {
            if (!$this->deckt($kuerzel)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Koennte eine HK in beiden Gruppen liegen?
     *
     * @param wahlpflicht_gruppe $andere
     */
    public function ueberschneidet(wahlpflicht_gruppe $andere): bool {
        if ($this->bereiche === [] || $andere->bereiche === []) {
            return true;
        }
        foreach ($andere->bereiche as $kuerzel) {
            if ($this->deckt($kuerzel)) {
                return true;
            }
        }
        foreach ($this->bereiche as $kuerzel) {
            if ($andere->deckt($kuerzel)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Wie viele Wahlpflicht-HK dieser Gruppe vollstaendig vorkamen (alle
     * LK), wie viele teilweise und wie viele erst eingeplant sind - siehe
     * raster_kompetenz::anzeigestand().
     *
     * Raster und Kachel auf "Meine Lernenden" zaehlen beide hiermit, damit
     * sie nie auseinanderlaufen.
     *
     * @param raster_bereich[] $raster Ergebnis von api::get_kompetenzraster()
     * @return array{vollstaendig: int, teilweise: int, eingeplant: int}
     */
    public function stand(array $raster): array {
        $stand = [
            raster_kompetenz::ANZEIGE_VOLLSTAENDIG => 0,
            raster_kompetenz::ANZEIGE_TEILWEISE => 0,
            raster_kompetenz::ANZEIGE_EINGEPLANT => 0,
        ];
        foreach ($raster as $bereich) {
            foreach ($bereich->kompetenzen as $kompetenz) {
                if (!$kompetenz->istwahlpflicht || !$this->umfasst_kompetenz($bereich, $kompetenz)) {
                    continue;
                }
                $anzeige = $kompetenz->anzeigestand();
                if (isset($stand[$anzeige])) {
                    $stand[$anzeige]++;
                }
            }
        }

        return $stand;
    }

    /**
     * Wie viele der verlangten Wahlpflicht-HK noch nicht vollstaendig
     * vorkamen. Erfuellt ist die Gruppe erst, wenn genug HK vollstaendig
     * sind - wie das Haekchen im Raster.
     *
     * @param raster_bereich[] $raster Ergebnis von api::get_kompetenzraster()
     */
    public function offen(array $raster): int {
        return max(0, $this->anzahl - $this->stand($raster)[raster_kompetenz::ANZEIGE_VOLLSTAENDIG]);
    }

    /**
     * Liegt alles, was das Kuerzel meint, in dieser Gruppe? Ein Bereich
     * nur, wenn die Gruppe ihn nennt; eine HK auch, wenn die Gruppe ihren
     * Bereich nennt - der Teil vor dem ersten Punkt ("d" bei "d.06").
     *
     * @param string $kuerzel Kuerzel eines Bereichs oder einer HK
     */
    private function deckt(string $kuerzel): bool {
        if ($this->bereiche === [] || in_array($kuerzel, $this->bereiche, true)) {
            return true;
        }

        return str_contains($kuerzel, '.') && in_array(strstr($kuerzel, '.', true), $this->bereiche, true);
    }
}

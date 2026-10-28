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
 * Rendert die Stationen einer Quelle als Zeitleiste fuer meine_lernenden.php.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\output;

use local_berufsbildung\nachweis\erfassen_aktion;
use local_berufsbildung\nachweis\station;
use local_berufsbildung\nachweis\stationen;

/**
 * Zuoberst im Detail einer Person: alle Stationen einer Quelle als
 * Zeitleiste, darunter die aktuelle mit ihrem Stand und der Aktion der
 * Quelle. Erledigte Stationen tragen ihren Stand (etwa das
 * Gespraechsdatum) und den Link direkt in der Zeitleiste - eine eigene
 * Liste der abgeschlossenen hiesse dasselbe zweimal. So steht alles zu
 * Bildungsberichten an einer Stelle statt verteilt auf Schaltflaeche,
 * Faelligkeiten und Taetigkeiten.
 */
class stationen_leiste {
    /**
     * Baut die Daten fuer das Template zusammen.
     *
     * @param stationen $stationen Aus collector::get_stationen()
     * @param erfassen_aktion|null $aktion Die Aktion derselben Quelle fuer Zustaendige,
     *        siehe collector::get_zustaendigen_aktionen() - sie steht bei der
     *        aktuellen Station statt zuoberst im Detail. Auf "Meine Lehre" die
     *        Erfassen-Aktion der Quelle fuer die eigene Person.
     * @param bool $aktionzweitrangig Umrandet statt gefuellt - wo dieselbe Aktion
     *        schon als Primaerschaltflaeche in der Kopfkarte steht.
     * @return string
     */
    public static function render(
        stationen $stationen,
        ?erfassen_aktion $aktion = null,
        bool $aktionzweitrangig = false
    ): string {
        global $OUTPUT;

        $aktuelle = $stationen->aktuelle();

        return $OUTPUT->render_from_template('local_berufsbildung/stationen_leiste', [
            'titel' => $stationen->titel,
            'hasverlauf' => $stationen->verlaufurl !== null,
            'verlaufurl' => $stationen->verlaufurl ?? '',
            'eintraege' => array_map([self::class, 'station_daten'], $stationen->eintraege),
            'hasaktuelle' => $aktuelle !== null,
            'aktuelle' => $aktuelle !== null ? self::station_daten($aktuelle) : null,
            'hasaktion' => $aktion !== null,
            'aktion' => $aktion !== null ? [
                'label' => $aktion->label,
                'url' => $aktion->url,
                'klasse' => $aktionzweitrangig ? 'btn-outline-primary' : 'btn-primary',
            ] : null,
        ]);
    }

    /**
     * Darstellungsdaten einer Station, auch fuer die Spalte der Kachel
     * (lernenden_kachel). Farben ueber Bootstrap-Klassen, wie ueberall in
     * diesem Plugin; die Bedeutung steht zusaetzlich im statustext.
     *
     * @param station $station
     * @return array
     */
    public static function station_daten(station $station): array {
        [$icon, $punktklasse] = match ($station->status) {
            station::STATUS_ERLEDIGT => ['fa-check', 'bg-success text-white border-success'],
            station::STATUS_OFFEN => ['fa-edit', 'bg-primary text-white border-primary'],
            station::STATUS_UEBERFAELLIG => ['fa-exclamation', 'bg-danger text-white border-danger'],
            station::STATUS_ENTFAELLT => ['fa-minus', 'bg-light text-muted border'],
            default => ['', 'bg-white border'],
        };

        return [
            'bezeichnung' => $station->bezeichnung,
            'kurzbezeichnung' => $station->kurzbezeichnung ?? $station->bezeichnung,
            'statustext' => $station->statustext,
            'status' => $station->status,
            'hasstand' => $station->stand !== null && $station->stand !== '',
            // In der Zeitleiste nur bei erledigten Stationen, etwa "Gespraech
            // am ..."; der Stand offener ist lang und steht bei der aktuellen.
            'zeigestand' => $station->status === station::STATUS_ERLEDIGT && $station->stand !== null && $station->stand !== '',
            'stand' => $station->stand ?? '',
            'hasurl' => $station->url !== null,
            'url' => $station->url ?? '',
            'aktuell' => $station->aktuell,
            'hasicon' => $icon !== '',
            'icon' => $icon,
            'punktklasse' => $punktklasse,
            'istueberfaellig' => $station->status === station::STATUS_UEBERFAELLIG,
        ];
    }
}

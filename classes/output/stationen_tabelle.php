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
 * Rendert die Stationen einer Quelle fuer alle Lernenden als Tabelle (stationen.php).
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
 * Eine Zeile je Person, eine Spalte je Station, zuletzt der naechste
 * Schritt: bei 16 Lernenden der Vergleich, den die aufklappbaren Zeilen
 * auf "Meine Lernenden" nicht leisten - wer ist wo, wer haengt hinterher.
 *
 * Die Spalten folgen der Kurzbezeichnung der Stationen, nicht ihrer
 * Position: eine Person mit drei Lehrjahren hat weniger Semester, eine
 * mit verlaengerter Probezeit eine Station mehr. Symbole und Farben wie in
 * der Zeitleiste (stationen_leiste::station_daten()).
 */
class stationen_tabelle {
    /** Status in der Reihenfolge der Legende. */
    private const LEGENDE = [
        station::STATUS_ERLEDIGT,
        station::STATUS_OFFEN,
        station::STATUS_UEBERFAELLIG,
        station::STATUS_GEPLANT,
        station::STATUS_ENTFAELLT,
    ];

    /**
     * Baut die Daten fuer das Template zusammen.
     *
     * @param string $titel Anzeigename der Quelle
     * @param array $zeilen Struktur: array<int, array{name: string, stand: ?string, stationen: stationen,
     *        aktion: ?erfassen_aktion}> - stand etwa "AU_EFZ · 3. Semester", aktion
     *        aus collector::get_zustaendigen_aktionen() fuer dieselbe Quelle
     * @return string
     */
    public static function render(string $titel, array $zeilen): string {
        global $OUTPUT;

        $spalten = self::spalten(array_map(
            static fn (array $zeile): stationen => $zeile['stationen'],
            $zeilen
        ));

        $vorhanden = [];
        $zeilendaten = [];
        foreach ($zeilen as $zeile) {
            $jespalte = [];
            foreach ($zeile['stationen']->eintraege as $eintrag) {
                $jespalte[self::kurz($eintrag)] ??= $eintrag;
            }

            $zellen = [];
            foreach ($spalten as $spalte) {
                $eintrag = $jespalte[$spalte] ?? null;
                if ($eintrag !== null) {
                    $vorhanden[$eintrag->status] = true;
                }
                $zellen[] = [
                    'hatstation' => $eintrag !== null,
                    'station' => $eintrag !== null ? stationen_leiste::station_daten($eintrag) + [
                        'titel' => self::zellentitel($eintrag),
                    ] : null,
                ];
            }

            $aktuelle = $zeile['stationen']->aktuelle();
            $aktion = $zeile['aktion'];
            $zeilendaten[] = [
                'name' => $zeile['name'],
                'hasstand' => $zeile['stand'] !== null,
                'stand' => $zeile['stand'] ?? '',
                'zellen' => $zellen,
                'hasaktion' => $aktion !== null,
                'aktion' => $aktion !== null ? ['label' => $aktion->label, 'url' => $aktion->url] : null,
                'hasaktuelle' => $aktuelle !== null,
                'aktuelle' => $aktuelle !== null ? stationen_leiste::station_daten($aktuelle) : null,
            ];
        }

        $legende = array_values(array_map(
            static fn (string $status): array => stationen_leiste::station_daten(
                new station('', '', $status, get_string('stationen:status_' . $status, 'local_berufsbildung'))
            ),
            array_filter(self::LEGENDE, static fn (string $status): bool => isset($vorhanden[$status]))
        ));

        return $OUTPUT->render_from_template('local_berufsbildung/stationen_tabelle', [
            'titel' => $titel,
            'spalten' => array_map(static fn (string $spalte): array => ['name' => $spalte], $spalten),
            'zeilen' => $zeilendaten,
            'haslegende' => !empty($legende),
            'legende' => $legende,
        ]);
    }

    /**
     * Die Spalten ueber alle Personen: jede Kurzbezeichnung einmal, in der
     * Reihenfolge der Stationen. Kommt eine nur bei einer Person vor, etwa
     * eine Verlaengerung der Probezeit, steht sie hinter der Station, der
     * sie bei dieser Person folgt.
     *
     * @param stationen[] $jeperson
     * @return string[]
     */
    public static function spalten(array $jeperson): array {
        $spalten = [];
        foreach ($jeperson as $stationen) {
            $davor = -1;
            foreach ($stationen->eintraege as $eintrag) {
                $kurz = self::kurz($eintrag);
                $position = array_search($kurz, $spalten, true);
                if ($position === false) {
                    $position = $davor + 1;
                    array_splice($spalten, $position, 0, [$kurz]);
                }
                $davor = max($davor, $position);
            }
        }

        return $spalten;
    }

    /**
     * Name der Spalte einer Station.
     *
     * @param station $station
     * @return string
     */
    private static function kurz(station $station): string {
        return $station->kurzbezeichnung ?? $station->bezeichnung;
    }

    /**
     * Was beim Zeigen auf eine Zelle erscheint und was der Screenreader
     * vorliest: die volle Bezeichnung mit Status, bei erledigten auch den
     * Stand (etwa das Gespraechsdatum).
     *
     * @param station $station
     * @return string
     */
    private static function zellentitel(station $station): string {
        $titel = $station->bezeichnung . ': ' . $station->statustext;
        if ($station->status === station::STATUS_ERLEDIGT && $station->stand !== null && $station->stand !== '') {
            $titel .= ' · ' . $station->stand;
        }

        return $titel;
    }
}

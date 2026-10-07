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
 * Prueft, ob Rahmen, Bloecke und Wahlpflicht je Beruf zusammenpassen.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\service;

use core_competency\competency_framework;
use local_berufsbildung\api;
use local_berufsbildung\persistent\block;
use local_berufsbildung\persistent\block_lk;

/**
 * Das Kompetenzraster einer lernenden Person bleibt leer oder unvollstaendig,
 * wenn eines von mehreren Teilen fehlt: der Rahmen des Berufs, aktive
 * betriebliche Bloecke oder deren Kompetenzzuordnung. Von der Person aus
 * gesehen ist nicht zu erkennen, welcher Teil fehlt. Diese Pruefung
 * nennt es je Beruf, damit die Verwaltung es beheben kann.
 *
 * Geprueft werden die Berufe, die einen Block haben oder einem Rahmen
 * zugeordnet sind. Berufe, die nur als Option im Profilfeld stehen, waeren
 * sonst lauter Fehlalarme fuer Berufe, die der Betrieb gar nicht ausbildet.
 */
class einrichtung_pruefung {
    /** @var string Raster bleibt leer oder unvollstaendig. */
    public const STUFE_WARNUNG = 'warning';

    /** @var string Moeglicherweise gewollt, aber pruefenswert. */
    public const STUFE_HINWEIS = 'info';

    /**
     * Die Befunde je Beruf.
     *
     * @return array Beruf => Liste von ['stufe' => STUFE_*, 'code' => string, 'a' => mixed];
     *               nur Berufe mit mindestens einem Befund, natuerlich sortiert.
     *               'code' ist der Schluessel des Sprachstrings ohne Praefix
     *               "einrichtung:", 'a' sein Parameter.
     */
    public function pruefe(): array {
        $bloeckejeberuf = [];
        foreach (block::get_records() as $block) {
            $beruf = (string) $block->get('beruf');
            if ($beruf !== '') {
                $bloeckejeberuf[$beruf][(int) $block->get('id')] = $block;
            }
        }

        $anzahlkompetenzen = [];
        foreach (block_lk::get_records() as $abdeckung) {
            $blockid = (int) $abdeckung->get('blockid');
            $anzahlkompetenzen[$blockid] = ($anzahlkompetenzen[$blockid] ?? 0) + 1;
        }

        $konfiguration = get_config('local_berufsbildung', 'beruf_rahmen_mapping');
        $berufe = array_unique(array_merge(
            array_map('strval', array_keys($bloeckejeberuf)),
            (new rahmen_resolver())->alle_codes($konfiguration !== false ? (string) $konfiguration : '')
        ));
        sort($berufe, SORT_NATURAL | SORT_FLAG_CASE);

        $befunde = [];
        foreach ($berufe as $beruf) {
            $liste = array_merge(
                $this->pruefe_rahmen($beruf),
                $this->pruefe_bloecke($bloeckejeberuf[$beruf] ?? [], $anzahlkompetenzen),
                $this->pruefe_wahlpflicht($beruf)
            );
            if (!empty($liste)) {
                $befunde[$beruf] = $liste;
            }
        }

        return $befunde;
    }

    /**
     * Ist dem Beruf ein Rahmen zugeordnet, und gibt es ihn?
     *
     * @param string $beruf
     * @return array
     */
    private function pruefe_rahmen(string $beruf): array {
        $idnumber = api::get_kompetenzrahmen_for_beruf($beruf);
        if ($idnumber === null) {
            return [$this->befund(self::STUFE_WARNUNG, 'kein_rahmen')];
        }
        if (!competency_framework::record_exists_select('idnumber = :idnumber', ['idnumber' => $idnumber])) {
            return [$this->befund(self::STUFE_WARNUNG, 'rahmen_fehlt', $idnumber)];
        }

        return [];
    }

    /**
     * Gibt es aktive betriebliche Bloecke mit Kompetenzen, und liegen
     * Kompetenzen in Bloecken, die nicht zaehlen?
     *
     * @param block[] $bloecke Die Bloecke des Berufs
     * @param array $anzahlkompetenzen blockid => Anzahl zugeordneter Kompetenzen
     * @return array
     */
    private function pruefe_bloecke(array $bloecke, array $anzahlkompetenzen): array {
        if (empty($bloecke)) {
            return [$this->befund(self::STUFE_WARNUNG, 'keine_bloecke')];
        }

        $zaehlende = 0;
        $ungenutzt = [];
        foreach ($bloecke as $blockid => $block) {
            $zaehlt = $block->get('aktiv') && $block->get('ist_betrieb');
            $hatkompetenzen = ($anzahlkompetenzen[$blockid] ?? 0) > 0;
            if ($zaehlt && $hatkompetenzen) {
                $zaehlende++;
            } else if (!$zaehlt && $hatkompetenzen) {
                $ungenutzt[] = (string) $block->get('nummer');
            }
        }

        $befunde = [];
        if ($zaehlende === 0) {
            $befunde[] = $this->befund(self::STUFE_WARNUNG, 'keine_kompetenzen');
        }
        if (!empty($ungenutzt)) {
            sort($ungenutzt, SORT_NATURAL | SORT_FLAG_CASE);
            $befunde[] = $this->befund(self::STUFE_HINWEIS, 'ungenutzte_kompetenzen', implode(', ', $ungenutzt));
        }

        return $befunde;
    }

    /**
     * Wahlpflicht-HK ohne verlangte Anzahl: das Raster zeigt sie, kann
     * aber nicht sagen, ob genug davon vorkommen.
     *
     * @param string $beruf
     * @return array
     */
    private function pruefe_wahlpflicht(string $beruf): array {
        if (!empty(api::get_wahlpflicht_hk_for_beruf($beruf)) && empty(api::get_wahlpflicht_gruppen_for_beruf($beruf))) {
            return [$this->befund(self::STUFE_HINWEIS, 'wahlpflicht_ohne_anzahl')];
        }

        return [];
    }

    /**
     * Ein einzelner Befund.
     *
     * @param string $stufe STUFE_*
     * @param string $code Sprachstring ohne Praefix "einrichtung:"
     * @param mixed $a Parameter des Sprachstrings
     * @return array
     */
    private function befund(string $stufe, string $code, $a = null): array {
        return ['stufe' => $stufe, 'code' => $code, 'a' => $a];
    }
}

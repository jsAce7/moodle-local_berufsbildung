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
 * Hilfsfunktionen fuer die Baumstruktur eines Kompetenzrahmens.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\service;

use core_competency\competency;

/**
 * Unsere Kompetenzrahmen sind dreistufig aufgebaut:
 * Handlungskompetenzbereich -> Handlungskompetenz -> Leistungskriterium
 * (LK). Diese Klasse trennt die Ebenen eines Rahmens auseinander: die
 * Blattknoten (LK) fuer die Blockzuordnung (block_kompetenzen.php, siehe
 * classes/persistent/block_lk.php) und die mittlere Ebene (HK) fuer die
 * Luecken-Analyse (luecken_analyse.php).
 */
class kompetenz_baum {
    /**
     * Nur die Blattknoten (Leistungskriterien) aus einer Liste von
     * Kompetenzen desselben Rahmens - Kompetenzen, die selbst kein
     * Elternteil eines anderen Knotens in der Liste sind. Funktioniert
     * unabhaengig von der tatsaechlichen Tiefe des Rahmens, ohne eine
     * feste Anzahl Ebenen anzunehmen.
     *
     * @param competency[] $kompetenzen Alle Kompetenzen eines Rahmens
     * @return competency[] Nur die Blattknoten, gleiche Reihenfolge
     */
    public function nur_blaetter(array $kompetenzen): array {
        $elternids = [];
        foreach ($kompetenzen as $kompetenz) {
            $elternid = (int) $kompetenz->get('parentid');
            if ($elternid !== 0) {
                $elternids[$elternid] = true;
            }
        }

        return array_values(array_filter(
            $kompetenzen,
            static fn (competency $kompetenz): bool => !isset($elternids[(int) $kompetenz->get('id')])
        ));
    }

    /**
     * Beschriftungen fuer die Blattknoten (LK) eines Rahmens, jeweils mit
     * der uebergeordneten Handlungskompetenz als Praefix und der idnumber
     * dahinter.
     *
     * Der shortname eines LK allein ist in der Praxis nicht sprechend
     * genug - dieselbe Formulierung kommt in mehreren Handlungskompetenzen
     * vor. Mit HK davor bleibt jeder Eintrag in der Auswahlliste von
     * block_kompetenzen.php eindeutig und ueber beide Ebenen durchsuchbar.
     *
     * Die Werte sind unformatiert: die aufrufende Seite schickt sie durch
     * format_string().
     *
     * @param competency[] $kompetenzen Alle Kompetenzen eines Rahmens
     * @return array<int, string> LK-id => Beschriftung, natuerlich sortiert
     */
    public function blatt_beschriftungen(array $kompetenzen): array {
        $namen = [];
        foreach ($kompetenzen as $kompetenz) {
            $namen[(int) $kompetenz->get('id')] = (string) $kompetenz->get('shortname');
        }

        $beschriftungen = [];
        foreach ($this->nur_blaetter($kompetenzen) as $blatt) {
            $beschriftung = (string) $blatt->get('shortname');

            $idnumber = (string) $blatt->get('idnumber');
            if ($idnumber !== '') {
                $beschriftung .= ' (' . $idnumber . ')';
            }

            $elternid = (int) $blatt->get('parentid');
            if (isset($namen[$elternid])) {
                $beschriftung = $namen[$elternid] . ': ' . $beschriftung;
            }

            $beschriftungen[(int) $blatt->get('id')] = $beschriftung;
        }

        // Sortierung ueber die fertige Beschriftung, damit die LK einer
        // Handlungskompetenz in der Auswahlliste beieinander stehen - die
        // Reihenfolge der uebergebenen Liste tut das nicht.
        asort($beschriftungen, SORT_NATURAL | SORT_FLAG_CASE);

        return $beschriftungen;
    }

    /**
     * Nur die Handlungskompetenzen - die zweite Ebene, direkte Kinder der
     * obersten Handlungskompetenzbereiche (parentid = 0). Fuer die
     * Luecken-Analyse: die oberste Ebene selbst waere zu grob (mehrere HK
     * je Bereich koennten unbemerkt fehlen), die LK-Ebene zu fein (siehe
     * luecken_analyse.php).
     *
     * @param competency[] $kompetenzen Alle Kompetenzen eines Rahmens
     * @return competency[] Nur die Handlungskompetenzen, gleiche Reihenfolge
     */
    public function nur_handlungskompetenzen(array $kompetenzen): array {
        $oberste = [];
        foreach ($kompetenzen as $kompetenz) {
            if ((int) $kompetenz->get('parentid') === 0) {
                $oberste[(int) $kompetenz->get('id')] = true;
            }
        }

        return array_values(array_filter(
            $kompetenzen,
            static fn (competency $kompetenz): bool => isset($oberste[(int) $kompetenz->get('parentid')])
        ));
    }
}

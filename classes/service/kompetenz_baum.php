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
 * (LK). Ein Ausbildungsblock wird auf Ebene LK gepflegt (siehe
 * classes/persistent/block_lk.php) - diese Klasse trennt die Blattknoten
 * eines Rahmens von den Kompetenzrahmen selbst, damit block_kompetenzen.php
 * beim Zuordnen nur LK zur Auswahl anbietet, keine Handlungskompetenzen
 * oder -bereiche.
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
}

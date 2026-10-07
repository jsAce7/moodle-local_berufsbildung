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
 * Welche Leistungskriterien eines Berufs in welchen Bloecken vorkommen.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\service;

use core_competency\competency;
use core_competency\competency_framework;
use local_berufsbildung\api;
use local_berufsbildung\persistent\block;
use local_berufsbildung\persistent\block_lk;

/**
 * Die Blockzuordnung aus Sicht des Kompetenzrahmens statt aus Sicht eines
 * Blocks: je Leistungskriterium die Bloecke des Berufs, die es vermitteln.
 * Damit sieht die Verwaltung, welche LK noch in keinem Block vorkommen,
 * ohne jeden Block einzeln zu oeffnen.
 *
 * Ein LK gilt als zugeordnet, wenn ein aktiver betrieblicher Block es
 * selbst oder seine ganze Handlungskompetenz abdeckt - dieselbe Regel wie
 * im Kompetenzraster (plan_service::get_kompetenzen_je_einsatz()). Inaktive
 * und nicht betriebliche Bloecke werden trotzdem aufgefuehrt, aber
 * gekennzeichnet: sonst sucht man vergeblich, warum eine Zuordnung nicht
 * zaehlt.
 */
class lk_abdeckung_service {
    /**
     * Die Abdeckung aller Leistungskriterien eines Berufs.
     *
     * Eine Handlungskompetenz ohne Leistungskriterien steht als ihre
     * eigene einzige Zeile da, damit sie nicht aus der Zaehlung faellt.
     *
     * @param string $beruf Beruf-Code, z. B. AU_EFZ
     * @return array|null Null, wenn dem Beruf kein vorhandener
     *                    Kompetenzrahmen zugeordnet ist. Sonst mit den
     *                    Schluesseln framework, bereiche, anzahl und offen;
     *                    jeder Bereich mit bereich, anzahl, offen und
     *                    handlungskompetenzen; jede HK mit kompetenz und
     *                    leistungskriterien; jedes LK mit kompetenz,
     *                    zugeordnet und bloecke (je block, ueberhk, zaehlt).
     */
    public function fuer_beruf(string $beruf): ?array {
        if ($beruf === '') {
            return null;
        }

        $frameworkidnumber = api::get_kompetenzrahmen_for_beruf($beruf);
        if ($frameworkidnumber === null) {
            return null;
        }

        $framework = competency_framework::get_record(['idnumber' => $frameworkidnumber]);
        if (!$framework) {
            return null;
        }

        $rahmenkompetenzen = competency::get_records(
            ['competencyframeworkid' => (int) $framework->get('id')],
            'sortorder'
        );
        $baum = (new kompetenz_baum())->baum($rahmenkompetenzen);
        $zuordnungen = $this->zuordnungen($beruf);

        $bereiche = [];
        $anzahl = 0;
        $offen = 0;
        foreach ($baum as $zweig) {
            $bereich = [
                'bereich' => $zweig['bereich'],
                'anzahl' => 0,
                'offen' => 0,
                'handlungskompetenzen' => [],
            ];

            foreach ($zweig['handlungskompetenzen'] as $eintrag) {
                $hk = $eintrag['kompetenz'];
                $hkbloecke = $zuordnungen[(int) $hk->get('id')] ?? [];
                $leistungskriterien = $eintrag['leistungskriterien'] ?: [$hk];

                $zeilen = [];
                foreach ($leistungskriterien as $lk) {
                    $bloecke = [];
                    foreach ($hkbloecke as $blockid => $block) {
                        $bloecke[$blockid] = $this->eintrag($block, $lk !== $hk);
                    }
                    if ($lk !== $hk) {
                        foreach ($zuordnungen[(int) $lk->get('id')] ?? [] as $blockid => $block) {
                            $bloecke[$blockid] = $this->eintrag($block, false);
                        }
                    }
                    uasort($bloecke, static fn (array $a, array $b): int => strnatcasecmp(
                        (string) $a['block']->get('nummer'),
                        (string) $b['block']->get('nummer')
                    ));

                    $zugeordnet = false;
                    foreach ($bloecke as $blockeintrag) {
                        $zugeordnet = $zugeordnet || $blockeintrag['zaehlt'];
                    }

                    $bereich['anzahl']++;
                    if (!$zugeordnet) {
                        $bereich['offen']++;
                    }

                    $zeilen[] = [
                        'kompetenz' => $lk,
                        'zugeordnet' => $zugeordnet,
                        'bloecke' => array_values($bloecke),
                    ];
                }

                $bereich['handlungskompetenzen'][] = ['kompetenz' => $hk, 'leistungskriterien' => $zeilen];
            }

            $anzahl += $bereich['anzahl'];
            $offen += $bereich['offen'];
            $bereiche[] = $bereich;
        }

        return [
            'framework' => $framework,
            'bereiche' => $bereiche,
            'anzahl' => $anzahl,
            'offen' => $offen,
        ];
    }

    /**
     * Die Zuordnungen aller Bloecke eines Berufs, nach Kompetenz.
     *
     * Die Schluessel der inneren Arrays sind die Block-IDs.
     *
     * @param string $beruf
     * @return array competencyid => [blockid => block]
     */
    private function zuordnungen(string $beruf): array {
        global $DB;

        $bloecke = [];
        foreach (block::get_records(['beruf' => $beruf]) as $block) {
            $bloecke[(int) $block->get('id')] = $block;
        }
        if (empty($bloecke)) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal(array_keys($bloecke), SQL_PARAMS_NAMED);
        $zuordnungen = [];
        foreach (block_lk::get_records_select("blockid {$insql}", $params) as $abdeckung) {
            $blockid = (int) $abdeckung->get('blockid');
            $zuordnungen[(int) $abdeckung->get('competencyid')][$blockid] = $bloecke[$blockid];
        }

        return $zuordnungen;
    }

    /**
     * Ein Block in der Liste eines Leistungskriteriums.
     *
     * @param block $block
     * @param bool $ueberhk Der Block deckt die ganze Handlungskompetenz ab, nicht das LK selbst
     * @return array
     */
    private function eintrag(block $block, bool $ueberhk): array {
        return [
            'block' => $block,
            'ueberhk' => $ueberhk,
            'zaehlt' => (bool) $block->get('aktiv') && (bool) $block->get('ist_betrieb'),
        ];
    }
}

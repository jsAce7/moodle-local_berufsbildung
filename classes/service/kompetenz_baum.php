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
 * Blattknoten (LK) und die mittlere Ebene (HK) fuer die Luecken-Analyse
 * (raster_analyse.php), sowie den vollstaendigen Baum fuer die
 * Blockzuordnung (block_kompetenzen.php, siehe
 * classes/persistent/block_lk.php).
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
     * Der Rahmen als Baum, so wie er im Bildungsplan gegliedert ist:
     * Handlungskompetenzbereiche, darunter ihre Handlungskompetenzen,
     * darunter deren Leistungskriterien.
     *
     * Eine flache Liste taugt fuer die Auswahl nicht: dieselbe
     * LK-Bezeichnung kommt unter mehreren Handlungskompetenzen vor und ist
     * ohne ihren Platz im Rahmen nicht zu unterscheiden. Im Baum steht
     * jedes LK unter seiner HK, damit ist es eindeutig.
     *
     * Die Reihenfolge ist die der uebergebenen Liste - wer die Gliederung
     * des Bildungsplans will, uebergibt nach 'sortorder' sortiert.
     *
     * @param competency[] $kompetenzen Alle Kompetenzen eines Rahmens
     * @return array<int, array{bereich: competency, handlungskompetenzen: array}>
     */
    public function baum(array $kompetenzen): array {
        $kinder = [];
        foreach ($kompetenzen as $kompetenz) {
            $kinder[(int) $kompetenz->get('parentid')][] = $kompetenz;
        }

        $baum = [];
        foreach ($kinder[0] ?? [] as $bereich) {
            $handlungskompetenzen = [];
            foreach ($kinder[(int) $bereich->get('id')] ?? [] as $handlungskompetenz) {
                $handlungskompetenzen[] = [
                    'kompetenz' => $handlungskompetenz,
                    // Ueber nur_blaetter() statt der direkten Kinder: ein
                    // Rahmen mit einer Zwischenebene mehr faellt so nicht
                    // hinten runter.
                    'leistungskriterien' => $this->nur_blaetter(
                        $this->nachfahren($handlungskompetenz, $kinder)
                    ),
                ];
            }

            $baum[] = ['bereich' => $bereich, 'handlungskompetenzen' => $handlungskompetenzen];
        }

        return $baum;
    }

    /**
     * Kurzes Kuerzel aus der ID-Nummer, als Anker zum gedruckten
     * Bildungsplan: aus "7777BE b.07" wird "b.07".
     *
     * Die ID-Nummern tragen den Rahmen als Praefix, der in jeder Zeile
     * derselbe waere und nur Platz kostet. Uebrig bleibt der Teil, den
     * auch der Bildungsplan verwendet. Enthaelt die ID-Nummer kein
     * Leerzeichen, steht sie unveraendert da.
     *
     * Unformatiert - die aufrufende Seite schickt den Wert durch
     * format_string().
     *
     * @param competency $kompetenz
     */
    public static function kuerzel(competency $kompetenz): string {
        $idnumber = trim((string) $kompetenz->get('idnumber'));
        if ($idnumber === '') {
            return '';
        }

        $teile = preg_split('/\s+/', $idnumber);

        return (string) end($teile);
    }

    /**
     * Alle Kompetenzen unterhalb eines Knotens, ueber beliebig viele Ebenen.
     *
     * @param competency $knoten
     * @param array<int, competency[]> $kinder parentid => direkte Kinder
     * @return competency[]
     */
    private function nachfahren(competency $knoten, array $kinder): array {
        $nachfahren = [];
        foreach ($kinder[(int) $knoten->get('id')] ?? [] as $kind) {
            $nachfahren[] = $kind;
            $nachfahren = array_merge($nachfahren, $this->nachfahren($kind, $kinder));
        }

        return $nachfahren;
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

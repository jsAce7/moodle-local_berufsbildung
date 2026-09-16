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
 * Gleicht Kohorten-Links mit der aktuellen Kohorten-Mitgliedschaft ab.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\service;

use local_berufsbildung\api;
use local_berufsbildung\persistent\kohorten_link;
use local_berufsbildung\persistent\zuordnung;

/**
 * Fuer jeden aktiven Link: neue Kohorten-Mitglieder erhalten eine Zuordnung
 * (kohorten_link_id gesetzt, damit die Herkunft nachvollziehbar bleibt),
 * ausgeschiedene Mitglieder mit einer von diesem Link erzeugten laufenden
 * Zuordnung werden beendet - nie geloescht (Architekturregel 2). Wer
 * unveraendert Mitglied bleibt, wird nicht angefasst.
 *
 * Inaktive Links werden komplett uebersprungen: kein Zuwachs, kein Entzug -
 * bereits erzeugte Zuordnungen bleiben so, wie sie sind.
 *
 * Ein Kohorten-Mitglied mit bereits abgeschlossener Ausbildung erhaelt
 * bewusst keine neue Zuordnung mehr (siehe synchronisiere_link()) - sonst
 * wuerde eine vom Retention-Task bereits geloeschte Zuordnung bei jedem
 * weiteren Lauf einfach wieder auferstehen.
 */
class kohorten_sync_service {
    /**
     * Gleicht alle aktiven Kohorten-Verknuepfungen ab.
     *
     * @return array{erzeugt: int, beendet: int}
     */
    public function synchronisiere_alle(): array {
        $ergebnis = ['erzeugt' => 0, 'beendet' => 0];

        foreach (kohorten_link::get_records(['aktiv' => true]) as $link) {
            $einzelergebnis = $this->synchronisiere_link($link);
            $ergebnis['erzeugt'] += $einzelergebnis['erzeugt'];
            $ergebnis['beendet'] += $einzelergebnis['beendet'];
        }

        return $ergebnis;
    }

    /**
     * Gleicht eine einzelne Kohorten-Verknuepfung ab.
     *
     * @param kohorten_link $link
     * @return array{erzeugt: int, beendet: int}
     */
    public function synchronisiere_link(kohorten_link $link): array {
        $linkid = (int) $link->get('id');
        $berufsbildnerid = (int) $link->get('berufsbildnerid');

        $aktuellemitglieder = (new kohorten_resolver())->mitglieder([(int) $link->get('cohortid')]);
        // Sich selbst zuzuordnen ist kein sinnvoller Fall, falls die/der
        // Berufsbildner/in zufaellig auch Mitglied der Kohorte ist.
        $aktuellemitglieder = array_diff($aktuellemitglieder, [$berufsbildnerid]);

        $getrackt = zuordnung::get_records_select(
            'kohorten_link_id = :linkid AND gueltig_bis IS NULL',
            ['linkid' => $linkid]
        );
        $getracktelernendeids = array_map(
            static fn (zuordnung $z): int => (int) $z->get('lernendeid'),
            $getrackt
        );

        $neu = array_diff($aktuellemitglieder, $getracktelernendeids);
        $entfernt = array_diff($getracktelernendeids, $aktuellemitglieder);

        $erzeugt = 0;
        $jetzt = time();
        foreach ($neu as $lernendeid) {
            if (api::ist_ausbildung_beendet((int) $lernendeid)) {
                // Abgeschlossene Lehre: keine neue Zuordnung mehr erzeugen -
                // siehe Klassendocblock.
                continue;
            }

            // Ein Kohorten-Link darf eine manuelle Zuordnung oder einen
            // anderen Link derselben Rolle nicht verdraengen. Erst wenn
            // die andere Zuordnung endet, darf dieser Link wieder greifen.
            $hatanderezustaendigkeit = zuordnung::record_exists_select(
                'lernendeid = :lernendeid AND rolle = :rolle
                 AND gueltig_von <= :jetzt1 AND (gueltig_bis IS NULL OR gueltig_bis >= :jetzt2)',
                ['lernendeid' => $lernendeid, 'rolle' => $link->get('rolle'), 'jetzt1' => $jetzt, 'jetzt2' => $jetzt]
            );
            if ($hatanderezustaendigkeit) {
                continue;
            }

            api::set_zuordnung(
                $berufsbildnerid,
                (int) $lernendeid,
                (string) $link->get('beruf'),
                $jetzt,
                (string) $link->get('rolle'),
                $linkid
            );
            $erzeugt++;
        }

        foreach ($getrackt as $einzelne) {
            if (in_array((int) $einzelne->get('lernendeid'), $entfernt, true)) {
                api::beende_zuordnung((int) $einzelne->get('id'), $jetzt);
            }
        }

        return ['erzeugt' => $erzeugt, 'beendet' => count($entfernt)];
    }
}

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
 *
 * Aus demselben Grund entsteht keine neue Zuordnung, wenn die letzte
 * Zuordnung der Person in dieser Rolle von diesem Link stammt und von Hand
 * beendet wurde, waehrend sie Mitglied blieb. Erst ein erneuter Eintritt in
 * die Kohorte (cohort_members.timeadded nach dem Ende) laesst den Link
 * wieder greifen.
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
            // Ein Link, der scheitert, haelt die uebrigen nicht auf.
            try {
                $einzelergebnis = $this->synchronisiere_link($link);
            } catch (\moodle_exception $e) {
                debugging(
                    'local_berufsbildung: Kohorten-Link ' . $link->get('id') . ' nicht abgeglichen: ' . $e->getMessage(),
                    DEBUG_DEVELOPER
                );
                continue;
            }
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
        $rolle = (string) $link->get('rolle');
        foreach ($neu as $lernendeid) {
            $lernendeid = (int) $lernendeid;
            if (api::ist_ausbildung_beendet($lernendeid)) {
                // Abgeschlossene Lehre: keine neue Zuordnung mehr erzeugen -
                // siehe Klassendocblock.
                continue;
            }

            // Ein Kohorten-Link darf eine manuelle Zuordnung oder einen
            // anderen Link derselben Rolle nicht verdraengen. Erst wenn
            // die andere Zuordnung endet, darf dieser Link wieder greifen.
            // Auch eine erst kuenftig beginnende zaehlt: anlegen() lehnt
            // eine Zuordnung vor einer spaeter beginnenden ab.
            $hatanderezustaendigkeit = zuordnung::record_exists_select(
                'lernendeid = :lernendeid AND rolle = :rolle
                 AND (gueltig_bis IS NULL OR gueltig_bis >= :jetzt)',
                ['lernendeid' => $lernendeid, 'rolle' => $rolle, 'jetzt' => $jetzt]
            );
            if ($hatanderezustaendigkeit) {
                continue;
            }

            if ($this->ist_von_hand_beendet($link, $lernendeid)) {
                continue;
            }

            // Ein einzelnes Mitglied, das scheitert, haelt die uebrigen nicht auf.
            try {
                api::set_zuordnung(
                    $berufsbildnerid,
                    $lernendeid,
                    (string) $link->get('beruf'),
                    $jetzt,
                    $rolle,
                    $linkid
                );
                $erzeugt++;
            } catch (\moodle_exception $e) {
                debugging(
                    "local_berufsbildung: Kohorten-Link {$linkid}, Zuordnung fuer {$lernendeid} nicht angelegt: "
                        . $e->getMessage(),
                    DEBUG_DEVELOPER
                );
            }
        }

        foreach ($getrackt as $einzelne) {
            if (in_array((int) $einzelne->get('lernendeid'), $entfernt, true)) {
                api::beende_zuordnung((int) $einzelne->get('id'), $jetzt);
            }
        }

        return ['erzeugt' => $erzeugt, 'beendet' => count($entfernt)];
    }

    /**
     * Hat die Administration die Zuordnung dieses Links von Hand beendet,
     * waehrend die Person Mitglied der Kohorte blieb?
     *
     * Massgebend ist die zuletzt beginnende Zuordnung der Person in dieser
     * Rolle. Stammt sie von einem anderen Link oder ist sie manuell, darf der
     * Link nach deren Ende wieder greifen. Stammt sie von diesem Link, gilt
     * sie als von Hand beendet, sofern die Person nicht erst nach dem Ende
     * (wieder) in die Kohorte eingetreten ist - ein Austritt beendet die
     * Zuordnung ja ebenfalls.
     *
     * @param kohorten_link $link
     * @param int $lernendeid
     * @return bool
     */
    private function ist_von_hand_beendet(kohorten_link $link, int $lernendeid): bool {
        global $DB;

        $letzte = zuordnung::get_records(
            ['lernendeid' => $lernendeid, 'rolle' => $link->get('rolle')],
            'gueltig_von',
            'DESC',
            0,
            1
        );
        $letzte = reset($letzte);
        if (!$letzte || (int) $letzte->get('kohorten_link_id') !== (int) $link->get('id')) {
            return false;
        }

        $eintritt = (int) $DB->get_field('cohort_members', 'timeadded', [
            'cohortid' => $link->get('cohortid'),
            'userid' => $lernendeid,
        ]);

        return $eintritt <= (int) $letzte->get('gueltig_bis');
    }
}

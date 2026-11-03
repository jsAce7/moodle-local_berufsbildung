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
 * Rendert eine zurueckgehaltene Versetzungsplan-Lieferung zur Entscheidung (plan_freigabe.php).
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\output;

use core_collator;
use core_user;
use local_berufsbildung\persistent\plan_import;
use moodle_url;

/**
 * Eine wartende Lieferung mit allem, was die Administration fuer die
 * Entscheidung braucht: die Eckdaten im Vergleich zum letzten erfolgreichen
 * Import, je gelieferter Person die Einsaetze, die wegfallen oder
 * dazukommen, wer nicht mehr geliefert wird, das Protokoll und die Knoepfe
 * Freigeben und Verwerfen.
 */
class plan_freigabe {
    /**
     * Rendert eine wartende Lieferung.
     *
     * @param plan_import $wartend
     * @param array $pruefung Ergebnis von import_service::pruefe_wartende()
     * @param plan_import|null $letzter letzter erfolgreicher Import, null wenn es noch keinen gab
     * @param moodle_url $url Adresse der Seite, Ziel der Knoepfe
     * @return string
     */
    public static function render(plan_import $wartend, array $pruefung, ?plan_import $letzter, moodle_url $url): string {
        global $OUTPUT;

        return $OUTPUT->render_from_template(
            'local_berufsbildung/plan_freigabe',
            self::daten($wartend, $pruefung, $letzter, $url)
        );
    }

    /**
     * Baut die Daten fuer das Template zusammen.
     *
     * @param plan_import $wartend
     * @param array $pruefung Ergebnis von import_service::pruefe_wartende()
     * @param plan_import|null $letzter letzter erfolgreicher Import, null wenn es noch keinen gab
     * @param moodle_url $url Adresse der Seite, Ziel der Knoepfe
     * @return array
     */
    public static function daten(plan_import $wartend, array $pruefung, ?plan_import $letzter, moodle_url $url): array {
        // 0, wenn das Konto inzwischen anonymisiert wurde (privacy\provider).
        $ausgefuehrtvon = core_user::get_user((int) $wartend->get('ausgefuehrt_von'));

        $personen = [];
        foreach (self::namen(array_keys($pruefung['aenderungen'])) as $userid => $name) {
            $einsaetze = [];
            foreach (['entfaellt', 'neu'] as $art) {
                foreach ($pruefung['aenderungen'][$userid][$art] as $einsatz) {
                    $einsaetze[] = [
                        'entfaellt' => $art === 'entfaellt',
                        'block' => $einsatz['block'],
                        'zeitraum' => $einsatz['kw_von'] === $einsatz['kw_bis']
                            ? $einsatz['kw_von']
                            : $einsatz['kw_von'] . ' – ' . $einsatz['kw_bis'],
                    ];
                }
            }
            $personen[] = ['name' => $name, 'einsaetze' => $einsaetze];
        }

        $fehlende = array_map(
            static fn (string $name): array => ['name' => $name],
            array_values(self::namen($pruefung['fehlende_personen']))
        );
        $protokoll = array_map(
            static fn (string $zeile): array => ['zeile' => $zeile],
            array_values(array_filter(explode("\n", (string) $wartend->get('protokoll'))))
        );

        return [
            'id' => (int) $wartend->get('id'),
            'url' => $url->out(false),
            'titel' => get_string(
                'planfreigabe:lieferung_vom',
                'local_berufsbildung',
                userdate((int) $wartend->get('zeitpunkt'))
            ),
            'quelle' => (string) $wartend->get('quelle'),
            'ausgefuehrtvon' => $ausgefuehrtvon ? fullname($ausgefuehrtvon) : '-',
            'zeilengelesen' => (int) $wartend->get('zeilen_gelesen'),
            'personenvorher' => $letzter !== null ? (string) (int) $letzter->get('personen_verarbeitet') : '-',
            'personenheute' => (int) $pruefung['personen_verarbeitet'],
            'einsaetzeheute' => (int) $pruefung['einsaetze_erzeugt'],
            'anzahlaenderungen' => count($personen),
            'personenunveraendert' => (int) $pruefung['personen_unveraendert'],
            'haspersonenunveraendert' => $pruefung['personen_unveraendert'] > 0,
            'personen' => $personen,
            'anzahlfehlende' => count($fehlende),
            'hasfehlende' => !empty($fehlende),
            'fehlende' => $fehlende,
            'hasprotokoll' => !empty($protokoll),
            'protokoll' => $protokoll,
        ];
    }

    /**
     * Anzeigenamen, alphabetisch. Eine inzwischen geloeschte Person
     * erscheint mit ihrer userid.
     *
     * @param int[] $userids
     * @return array<int, string> userid => Name
     */
    private static function namen(array $userids): array {
        $namen = [];
        foreach ($userids as $userid) {
            $person = core_user::get_user((int) $userid);
            $namen[(int) $userid] = $person ? fullname($person) : (string) $userid;
        }
        core_collator::asort($namen);

        return $namen;
    }
}

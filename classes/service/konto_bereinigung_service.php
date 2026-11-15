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
 * Bereinigt die Daten eines geloeschten Kontos.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\service;

use local_berufsbildung\versetzungsplan\import_service;

/**
 * Bei einer Account-Loeschung verschwinden die Zuordnungen der Person als
 * Lernende sofort und unbedingt (Architekturregel 2). Ausgeloest vom
 * Observer auf \core\event\user_deleted, nicht erst vom Privacy-Provider:
 * der laeuft nur, wenn tool_dataprivacy einen Loeschantrag erzeugt und
 * genehmigt - und findet die Person nicht mehr, sobald der
 * context_cleanup_task ihren User-Kontext entfernt hat.
 *
 * Als Sicherheitsnetz fuer Konten, die vor diesem Observer oder an ihm
 * vorbei geloescht wurden, holt task\zuordnung_retention das nach
 * (bereinige_verwaiste()).
 */
class konto_bereinigung_service {
    /** @var string[] Tabellen, deren usermodified auf die bearbeitende Person zeigt. */
    public const TABELLEN_MIT_BEARBEITUNG = [
        'local_berufsbildung_zuordnung',
        'local_berufsbildung_kohorten_link',
        'local_berufsbildung_block',
        'local_berufsbildung_block_lk',
        'local_berufsbildung_block_fv',
        'local_berufsbildung_einsatz',
        'local_berufsbildung_plan_import',
        'local_berufsbildung_einsatz_aenderung',
        'local_berufsbildung_aufbewahrung',
        'local_berufsbildung_teilnahmeprofil',
    ];

    /**
     * Alles, was bei einem geloeschten Konto sofort weg muss.
     *
     * @param int $userid
     * @param string|null $email Mailadresse vor der Loeschung - delete_user()
     *     ueberschreibt sie im Datensatz, das Event traegt sie noch
     */
    public function bereinige_geloeschtes_konto(int $userid, ?string $email): void {
        $this->anonymisiere_bearbeitungsspuren($userid, $email);
        (new zuordnung_retention_service())->loesche_fuer_lernende($userid);
        (new zuordnung_service())->beende_fuer_geloeschtes_konto($userid);
        // Ein geloeschtes Konto ist nirgends mehr fachvorgesetzt.
        (new fachvorgesetzte_service())->entferne_person($userid);
    }

    /**
     * Wer etwas zuletzt bearbeitet, einen Import ausgefuehrt oder eine
     * Lieferung entschieden hat, wird auf 0 gesetzt: die Daten selbst
     * bleiben, nur die Spur der bearbeitenden Person verschwindet. Eine noch
     * wartende Lieferung mit der Mailadresse der Person wird verworfen, sie
     * liesse sich nicht teilweise bereinigen.
     *
     * @param int $userid
     * @param string|null $email
     */
    public function anonymisiere_bearbeitungsspuren(int $userid, ?string $email): void {
        global $DB;

        $DB->set_field('local_berufsbildung_plan_import', 'ausgefuehrt_von', 0, ['ausgefuehrt_von' => $userid]);
        $DB->set_field('local_berufsbildung_plan_import', 'entschieden_von', 0, ['entschieden_von' => $userid]);
        if ($email !== null && $email !== '') {
            (new import_service())->verwerfe_wartende_mit_mailadresse($email);
        }
        foreach (self::TABELLEN_MIT_BEARBEITUNG as $tabelle) {
            $DB->set_field($tabelle, 'usermodified', 0, ['usermodified' => $userid]);
        }
    }

    /**
     * Holt die Bereinigung fuer bereits geloeschte Konten nach, die noch
     * Daten haben - etwa weil sie vor diesem Observer geloescht wurden.
     *
     * @return int Anzahl bereinigter Konten
     */
    public function bereinige_verwaiste(): int {
        global $DB;

        $userids = $DB->get_fieldset_sql(
            "SELECT u.id
               FROM {user} u
              WHERE u.deleted = 1
                AND (EXISTS (SELECT 1 FROM {local_berufsbildung_zuordnung} z WHERE z.lernendeid = u.id)
                     OR EXISTS (SELECT 1 FROM {local_berufsbildung_zuordnung} z
                                 WHERE z.berufsbildnerid = u.id AND (z.gueltig_bis IS NULL OR z.gueltig_bis >= :jetzt))
                     OR EXISTS (SELECT 1 FROM {local_berufsbildung_kohorten_link} k
                                 WHERE k.berufsbildnerid = u.id AND k.aktiv = 1)
                     OR EXISTS (SELECT 1 FROM {local_berufsbildung_aufbewahrung} a WHERE a.lernendeid = u.id)
                     OR EXISTS (SELECT 1 FROM {local_berufsbildung_teilnahmeprofil} t WHERE t.userid = u.id)
                     OR EXISTS (SELECT 1 FROM {local_berufsbildung_block_fv} f WHERE f.userid = u.id))",
            ['jetzt' => time()]
        );

        foreach ($userids as $userid) {
            // Die Mailadresse ist nach delete_user() nicht mehr bekannt; eine
            // wartende Lieferung ueberholt ohnehin die naechste.
            $this->bereinige_geloeschtes_konto((int) $userid, null);
        }

        return count($userids);
    }
}

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
 * Fachvorgesetzte der Ausbildungsbloecke.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\service;

use local_berufsbildung\persistent\block_fv;

/**
 * Wer in einer Abteilung (Ausbildungsblock) fachvorgesetzt ist. Heute in
 * Moodle je Block gepflegt; kommen die Fachvorgesetzten spaeter je Einsatz
 * aus dem Versetzungsplan, aendert sich nur dieser Service - die
 * aufsetzenden Plugins fragen ueber die api.
 *
 * Fachvorgesetzte sind bewusst keine Zuordnung (Architekturregel 4): eine
 * Zuordnung macht zustaendig fuer die ganze lernende Person, ein
 * Fachvorgesetzter nur fuer ihren Einsatz in seiner Abteilung.
 */
class fachvorgesetzte_service {
    /**
     * Die Fachvorgesetzten eines Blocks mit nicht geloeschtem Konto.
     *
     * @param int $blockid
     * @return int[] User-IDs, aufsteigend
     */
    public function get_fuer_block(int $blockid): array {
        return $this->get_fuer_bloecke([$blockid])[$blockid] ?? [];
    }

    /**
     * Die Fachvorgesetzten mehrerer Bloecke in einer Abfrage.
     *
     * @param int[] $blockids
     * @return array<int, int[]> blockid => User-IDs; Bloecke ohne fehlen
     */
    public function get_fuer_bloecke(array $blockids): array {
        global $DB;

        $blockids = array_values(array_unique(array_map('intval', $blockids)));
        if (empty($blockids)) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal($blockids, SQL_PARAMS_NAMED);
        $zeilen = $DB->get_recordset_sql(
            'SELECT fv.id, fv.blockid, fv.userid
               FROM {' . block_fv::TABLE . "} fv
               JOIN {user} u ON u.id = fv.userid AND u.deleted = 0
              WHERE fv.blockid {$insql}
           ORDER BY fv.blockid, fv.userid",
            $params
        );
        $ergebnis = [];
        foreach ($zeilen as $zeile) {
            $ergebnis[(int) $zeile->blockid][] = (int) $zeile->userid;
        }
        $zeilen->close();

        return $ergebnis;
    }

    /**
     * Die Namen der Fachvorgesetzten mehrerer Bloecke - fuer Uebersichten,
     * in einer Abfrage.
     *
     * @param int[] $blockids
     * @return array<int, string[]> blockid => volle Namen, alphabetisch
     */
    public function namen_fuer_bloecke(array $blockids): array {
        global $DB;

        $blockids = array_values(array_unique(array_map('intval', $blockids)));
        if (empty($blockids)) {
            return [];
        }

        $namensfelder = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
        [$insql, $params] = $DB->get_in_or_equal($blockids, SQL_PARAMS_NAMED);
        $zeilen = $DB->get_recordset_sql(
            "SELECT fv.id AS fvid, fv.blockid, u.id, {$namensfelder}
               FROM {" . block_fv::TABLE . "} fv
               JOIN {user} u ON u.id = fv.userid AND u.deleted = 0
              WHERE fv.blockid {$insql}",
            $params
        );
        $ergebnis = [];
        foreach ($zeilen as $zeile) {
            $ergebnis[(int) $zeile->blockid][] = fullname($zeile);
        }
        $zeilen->close();

        foreach ($ergebnis as &$namen) {
            \core_collator::asort($namen);
            $namen = array_values($namen);
        }

        return $ergebnis;
    }

    /**
     * Sucht Personen fuer das Feld "Fachvorgesetzte": aktive Konten, deren
     * Name oder Mailadresse den Suchtext enthaelt. Bewusst eigene Suche
     * statt core_user_search_identity - jene verlangt
     * moodle/user:viewalldetails, das die Planungsrolle nicht haben soll.
     *
     * @param string $suche Mindestens zwei Zeichen
     * @param int $hoechstens
     * @return array[] Je Treffer ['id' => int, 'name' => string, 'email' => string]
     */
    public function suche_personen(string $suche, int $hoechstens = 20): array {
        global $DB, $CFG;

        $suche = trim($suche);
        if (\core_text::strlen($suche) < 2) {
            return [];
        }

        $namensfelder = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
        $vollname = $DB->sql_fullname('u.firstname', 'u.lastname');
        $bedingungen = [];
        $params = ['guest' => (int) $CFG->siteguest];
        foreach (preg_split('/\s+/u', $suche) as $i => $wort) {
            $muster = '%' . $DB->sql_like_escape($wort) . '%';
            $bedingungen[] = '(' . $DB->sql_like($vollname, ':name' . $i, false, false)
                . ' OR ' . $DB->sql_like('u.email', ':mail' . $i, false, false) . ')';
            $params['name' . $i] = $muster;
            $params['mail' . $i] = $muster;
        }

        $treffer = $DB->get_records_sql(
            "SELECT u.id, u.email, {$namensfelder}
               FROM {user} u
              WHERE u.deleted = 0 AND u.suspended = 0 AND u.confirmed = 1 AND u.id <> :guest
                AND " . implode(' AND ', $bedingungen) . '
           ORDER BY u.lastname, u.firstname, u.id',
            $params,
            0,
            $hoechstens
        );

        return array_values(array_map(static fn($u): array => [
            'id' => (int) $u->id,
            'name' => fullname($u),
            'email' => (string) $u->email,
        ], $treffer));
    }

    /**
     * Die Bloecke, in denen die Person fachvorgesetzt ist.
     *
     * @param int $userid
     * @return int[] blockids, aufsteigend
     */
    public function get_bloecke_fuer(int $userid): array {
        $ids = array_map(
            static fn (block_fv $fv): int => (int) $fv->get('blockid'),
            block_fv::get_records(['userid' => $userid], 'blockid')
        );

        return array_values($ids);
    }

    /**
     * Ist die Person im Block fachvorgesetzt?
     *
     * @param int $userid
     * @param int $blockid
     * @return bool
     */
    public function ist_fachvorgesetzt_fuer_block(int $userid, int $blockid): bool {
        return in_array($userid, $this->get_fuer_block($blockid), true);
    }

    /**
     * Ist die Person in irgendeinem Block fachvorgesetzt?
     *
     * @param int $userid
     * @return bool
     */
    public function ist_fachvorgesetzt(int $userid): bool {
        return block_fv::record_exists_select('userid = :userid', ['userid' => $userid]);
    }

    /**
     * Setzt die Fachvorgesetzten eines Blocks und haelt die Rolle der
     * Betroffenen nach.
     *
     * @param int $blockid
     * @param int[] $userids
     */
    public function setze_fuer_block(int $blockid, array $userids): void {
        $soll = array_fill_keys(array_map('intval', array_filter($userids)), true);
        $ist = [];
        foreach (block_fv::get_records(['blockid' => $blockid]) as $fv) {
            $ist[(int) $fv->get('userid')] = $fv;
        }

        $betroffen = [];
        foreach (array_keys(array_diff_key($soll, $ist)) as $userid) {
            (new block_fv(0, (object) ['blockid' => $blockid, 'userid' => $userid]))->create();
            $betroffen[] = $userid;
        }
        foreach (array_diff_key($ist, $soll) as $userid => $fv) {
            $fv->delete();
            $betroffen[] = $userid;
        }

        $this->rolle_nachfuehren($betroffen);
    }

    /**
     * Entfernt alle Fachvorgesetzten eines Blocks - beim Loeschen des Blocks.
     *
     * @param int $blockid
     */
    public function loesche_fuer_block(int $blockid): void {
        $this->setze_fuer_block($blockid, []);
    }

    /**
     * Entfernt die Person aus allen Bloecken - bei einer Account-Loeschung
     * oder einem Loeschantrag.
     *
     * @param int $userid
     */
    public function entferne_person(int $userid): void {
        global $DB;

        $DB->delete_records(block_fv::TABLE, ['userid' => $userid]);
        $this->rolle_nachfuehren([$userid]);
    }

    /**
     * Haelt die Rolle der betroffenen Personen nach.
     *
     * @param int[] $userids
     */
    private function rolle_nachfuehren(array $userids): void {
        $rolle = new fachvorgesetztenrolle_service();
        foreach (array_unique($userids) as $userid) {
            $rolle->synchronisiere_person((int) $userid);
        }
    }
}

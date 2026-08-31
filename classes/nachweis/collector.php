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
 * Sammelt Nachweise aus allen registrierten Quellen ein.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\nachweis;

use local_berufsbildung\api;
use moodle_exception;

/**
 * Die Zustaendigkeitspruefung passiert hier, bevor ein Provider gefragt
 * wird - nie im Provider selbst (siehe CLAUDE.md, Architekturregel 7).
 * Ein nachlaessig geschriebener Provider darf Leistungsdaten nicht fuer
 * alle oeffnen koennen.
 */
class collector {

    /** @var provider[] */
    private readonly array $providers;

    /**
     * @param provider[]|null $providers Zum Testen von aussen vorgebbar;
     *                                    im Produktivbetrieb werden die
     *                                    registrierten Provider ueber
     *                                    get_plugins_with_function() geladen.
     */
    public function __construct(?array $providers = null) {
        $this->providers = $providers ?? self::lade_registrierte_provider();
    }

    /**
     * @return provider[]
     */
    private static function lade_registrierte_provider(): array {
        $providers = [];

        foreach (get_plugins_with_function('berufsbildung_nachweis_provider') as $funktionenjetyp) {
            foreach ($funktionenjetyp as $funktion) {
                $providers[] = $funktion();
            }
        }

        return $providers;
    }

    /**
     * Nachweise einer lernenden Person im Zeitraum, aus allen registrierten
     * Quellen, neueste zuerst.
     *
     * @param int $abrufendeid Wer fragt ab
     * @param int $lernendeid Fuer wen
     * @param int $von Timestamp
     * @param int $bis Timestamp
     * @param int|null $berechtigungsstichtag Zuständigkeit wird zu diesem
     *        Zeitpunkt geprüft; null bedeutet jetzt. Eine historische
     *        Semesteransicht übergibt ihr Semesterende.
     * @return nachweis[]
     */
    public function get_nachweise(
        int $abrufendeid,
        int $lernendeid,
        int $von,
        int $bis,
        ?int $berechtigungsstichtag = null
    ): array {
        if ($abrufendeid !== $lernendeid
                && !api::is_zustaendig($abrufendeid, $lernendeid, $berechtigungsstichtag)) {
            throw new moodle_exception('error:keinezustaendigkeit', 'local_berufsbildung');
        }

        $ergebnis = [];
        foreach ($this->providers as $einzelprovider) {
            foreach ($einzelprovider->get_nachweise($lernendeid, $von, $bis) as $einzelnachweis) {
                $ergebnis[] = $einzelnachweis;
            }
        }

        usort($ergebnis, static fn (nachweis $a, nachweis $b): int => $b->datum <=> $a->datum);

        return $ergebnis;
    }

    /**
     * Anzeigenamen aller registrierten Quellen, fuer die gruppierte
     * Darstellung von Nachweisen nach Quelle (siehe nachweis_liste::render()).
     *
     * @return array<string, string> Quelle-Key => Anzeigename
     */
    public function get_quelle_namen(): array {
        $namen = [];
        foreach ($this->providers as $einzelprovider) {
            $namen[$einzelprovider->get_quelle_key()] = $einzelprovider->get_quelle_name();
        }

        return $namen;
    }
}

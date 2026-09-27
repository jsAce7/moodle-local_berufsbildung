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
     * Konstruktor.
     *
     * @param provider[]|null $providers Zum Testen von aussen vorgebbar;
     *                                    im Produktivbetrieb werden die
     *                                    registrierten Provider ueber
     *                                    get_plugins_with_function() geladen.
     */
    public function __construct(?array $providers = null) {
        $this->providers = $providers ?? self::lade_registrierte_provider();
    }

    /**
     * Laedt die registrierten Nachweis-Provider.
     *
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
        if (
            $abrufendeid !== $lernendeid
            && !api::is_zustaendig($abrufendeid, $lernendeid, $berechtigungsstichtag)
        ) {
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

    /**
     * Zusammenfassungen je Quelle ueber bereits eingesammelte Nachweise, fuer
     * den Gruppenkopf der Taetigkeitenliste (siehe quelle_mit_zusammenfassung).
     *
     * Nimmt das Ergebnis von get_nachweise() entgegen statt einer
     * Personen-ID: die Zustaendigkeit ist damit bereits geprueft, und jede
     * Quelle fasst genau die Nachweise zusammen, die daneben angezeigt
     * werden. Eine Quelle ohne Nachweise wird nicht gefragt.
     *
     * @param nachweis[] $nachweise Ergebnis von get_nachweise()
     * @return array<string, string> Quelle-Key => Zusammenfassung, nur Quellen mit einer
     */
    public function get_zusammenfassungen(array $nachweise): array {
        $jequelle = [];
        foreach ($nachweise as $einzelnachweis) {
            $jequelle[$einzelnachweis->quellekey][] = $einzelnachweis;
        }

        $zusammenfassungen = [];
        foreach ($this->providers as $einzelprovider) {
            $quellekey = $einzelprovider->get_quelle_key();
            if (!$einzelprovider instanceof quelle_mit_zusammenfassung || empty($jequelle[$quellekey])) {
                continue;
            }

            $zusammenfassung = $einzelprovider->get_zusammenfassung($jequelle[$quellekey]);
            if ($zusammenfassung !== null && $zusammenfassung !== '') {
                $zusammenfassungen[$quellekey] = $zusammenfassung;
            }
        }

        return $zusammenfassungen;
    }

    /**
     * Was je Quelle fuer die Person noch aussteht (siehe
     * quelle_mit_ausstehenden), fuer die Taetigkeitenliste.
     *
     * Dieselbe Zustaendigkeitspruefung wie bei get_nachweise(): auch das
     * Soll einer Person - welche ueK sie noch vor sich hat, wann sie
     * eingeplant ist - geht nur die Person selbst und ihre zustaendige
     * Berufsbildner/in etwas an.
     *
     * @param int $abrufendeid Wer fragt ab
     * @param int $lernendeid Fuer wen
     * @return array<string, ausstehend[]> Quelle-Key => Ausstehendes, nur Quellen mit Eintraegen
     */
    public function get_ausstehende(int $abrufendeid, int $lernendeid): array {
        if ($abrufendeid !== $lernendeid && !api::is_zustaendig($abrufendeid, $lernendeid)) {
            throw new moodle_exception('error:keinezustaendigkeit', 'local_berufsbildung');
        }

        $ergebnis = [];
        foreach ($this->providers as $einzelprovider) {
            if (!$einzelprovider instanceof quelle_mit_ausstehenden) {
                continue;
            }

            $ausstehende = array_values($einzelprovider->get_ausstehende($lernendeid));
            if (!empty($ausstehende)) {
                $ergebnis[$einzelprovider->get_quelle_key()] = $ausstehende;
            }
        }

        return $ergebnis;
    }

    /**
     * Erfassen-Aktionen aller Quellen, die `erfassbare_quelle` zusaetzlich
     * implementieren - fuer "Meine Lehre", damit die lernende Person direkt
     * dorthin verlinkt einen neuen Eintrag anlegen kann.
     *
     * Nur fuer die eigene Person: anders als bei get_nachweise() gibt es
     * hier keine Zustaendigkeit einer/eines Berufsbildner/in zu pruefen,
     * weil niemand fuer eine andere Person erfasst. Die Identitaetspruefung
     * passiert dennoch hier und nicht im Provider - dieselbe Regel wie bei
     * der Zustaendigkeitspruefung in get_nachweise() (Architekturregel 7).
     *
     * $abrufendeid wird wie bei get_nachweise() explizit vom Aufrufer
     * uebergeben statt hier global $USER zu lesen - gleiches Muster, einfacher
     * zu testen.
     *
     * @param int $abrufendeid Wer fragt ab
     * @param int $lernendeid Fuer wen
     * @return erfassen_aktion[]
     */
    public function get_erfassen_aktionen(int $abrufendeid, int $lernendeid): array {
        if ($abrufendeid !== $lernendeid) {
            return [];
        }

        $aktionen = [];
        foreach ($this->providers as $einzelprovider) {
            if (!$einzelprovider instanceof erfassbare_quelle) {
                continue;
            }

            $url = $einzelprovider->get_erfassen_url($lernendeid);
            if ($url === null) {
                continue;
            }

            // Der Hinweis ist optional und wird erst nach der URL geholt:
            // ohne Erfassung gibt es keine Aktion, an der er haengen koennte
            // - und keinen Grund, die Quelle dafuer rechnen zu lassen.
            $hinweis = $einzelprovider instanceof quelle_mit_hinweis
                ? $einzelprovider->get_erfassen_hinweis($lernendeid)
                : null;

            $aktionen[] = new erfassen_aktion(
                $einzelprovider->get_quelle_key(),
                $einzelprovider->get_erfassen_label(),
                $url->out(false),
                $hinweis
            );
        }

        return $aktionen;
    }

    /**
     * Aktionen aller Quellen, die `quelle_mit_zustaendigen_aktion`
     * implementieren - fuer "Meine Lernenden", damit die zustaendige
     * Berufsbildner/in direkt dorthin verlinkt fuer die Person erfasst.
     *
     * Gespiegelt zu get_erfassen_aktionen(): dort nur fuer die eigene
     * Person, hier nur fuer eine, fuer die die abrufende Person heute
     * zustaendig ist - erfasst wird jetzt, nicht fuer einen vergangenen
     * Stichtag. Auch fuer sich selbst gibt es hier nichts: wer fuer sich
     * erfasst, nutzt get_erfassen_aktionen().
     *
     * @param int $abrufendeid Wer fragt ab
     * @param int $lernendeid Fuer wen
     * @return erfassen_aktion[]
     */
    public function get_zustaendigen_aktionen(int $abrufendeid, int $lernendeid): array {
        if ($abrufendeid === $lernendeid) {
            return [];
        }
        if (!api::is_zustaendig($abrufendeid, $lernendeid)) {
            throw new moodle_exception('error:keinezustaendigkeit', 'local_berufsbildung');
        }

        $aktionen = [];
        foreach ($this->providers as $einzelprovider) {
            if (!$einzelprovider instanceof quelle_mit_zustaendigen_aktion) {
                continue;
            }

            $aktion = $einzelprovider->get_zustaendigen_aktion($abrufendeid, $lernendeid);
            if ($aktion === null) {
                continue;
            }

            // Der Schluessel kommt von der registrierten Quelle, nicht aus
            // der Aktion - eine Quelle kann sich nicht als andere ausgeben.
            $aktionen[] = new erfassen_aktion(
                $einzelprovider->get_quelle_key(),
                $aktion->label,
                $aktion->url,
                $aktion->hinweis
            );
        }

        return $aktionen;
    }

    /**
     * Fälligkeiten einer Person aus allen Quellen. Die gleiche
     * Zuständigkeitsgrenze wie bei Leistungsnachweisen gilt auch für
     * Planungsdaten.
     *
     * @param int $abrufendeid
     * @param int $lernendeid
     * @return faelligkeit[]
     */
    public function get_faelligkeiten(int $abrufendeid, int $lernendeid): array {
        if ($abrufendeid !== $lernendeid && !api::is_zustaendig($abrufendeid, $lernendeid)) {
            throw new moodle_exception('error:keinezustaendigkeit', 'local_berufsbildung');
        }

        $ergebnis = [];
        foreach ($this->providers as $provider) {
            if ($provider instanceof quelle_mit_faelligkeiten) {
                $ergebnis = array_merge($ergebnis, $provider->get_faelligkeiten($lernendeid));
            }
        }
        usort($ergebnis, static fn (faelligkeit $a, faelligkeit $b): int => $a->datum <=> $b->datum);

        return $ergebnis;
    }
}

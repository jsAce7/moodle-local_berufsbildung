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
 * Sammelt die Abschnitte der Hilfeseite.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\hilfe;

use context_system;
use local_berufsbildung\hook_callbacks;

/**
 * Eine Hilfeseite fuer alle Plugins der Berufsbildung: zuerst die Abschnitte
 * dieses Plugins, danach die, die aufsetzende Plugins ueber den Callback
 * <plugin>_berufsbildung_hilfe() melden - die Zielgruppen in fester
 * Reihenfolge (abschnitt::ZIELGRUPPEN), darin nach abschnitt::$reihenfolge. Wer etwas nicht oeffnen kann,
 * bekommt dessen Anleitung nicht zu sehen; so bleibt die Seite fuer
 * Lernende kurz, und ein deinstalliertes Plugin faellt von selbst weg.
 */
final class sammlung {
    /** Callback in der lib.php der aufsetzenden Plugins. */
    private const CALLBACK = 'berufsbildung_hilfe';

    /**
     * Die Abschnitte fuer die angemeldete Person, je Zielgruppe. Zielgruppen
     * ohne Abschnitt fehlen.
     *
     * @param abschnitt[]|null $gemeldete Zum Testen von aussen vorgebbar;
     *        sonst die Abschnitte der aufsetzenden Plugins
     * @return array<string, abschnitt[]>
     */
    public static function je_zielgruppe(?array $gemeldete = null): array {
        $je = array_fill_keys(abschnitt::ZIELGRUPPEN, []);
        $anker = [];

        foreach (array_merge(self::eigene(), $gemeldete ?? self::gemeldete()) as $einzel) {
            // Doppelte Sprungmarken wuerden den Link "Hilfe zu dieser Seite"
            // auf den falschen Abschnitt fuehren; der erste gewinnt.
            if (isset($anker[$einzel->anker])) {
                debugging('Hilfe-Anker doppelt: ' . $einzel->anker, DEBUG_DEVELOPER);
                continue;
            }
            $anker[$einzel->anker] = true;
            $je[$einzel->zielgruppe][] = $einzel;
        }

        // Ab PHP 8.0 ist usort stabil: bei gleicher Reihenfolge bleibt die
        // Folge der Meldung, die eigenen Abschnitte also vorne.
        foreach ($je as &$abschnitte) {
            usort($abschnitte, static fn(abschnitt $a, abschnitt $b): int => $a->reihenfolge <=> $b->reihenfolge);
        }
        unset($abschnitte);

        return array_filter($je);
    }

    /**
     * Ob die Hilfeseite fuer die angemeldete Person etwas enthaelt - nur
     * dann erscheint sie im Benutzermenue. Laeuft auf jeder Seite, deshalb
     * zuerst die eigenen Abschnitte: Wer welche hat (Lernende,
     * Berufsbildner/innen, Verwaltung), loest die Callbacks der anderen
     * Plugins gar nicht erst aus.
     *
     * @return bool
     */
    public static function hat_abschnitte(): bool {
        return !empty(self::eigene()) || !empty(self::gemeldete());
    }

    /**
     * Die Abschnitte dieses Plugins.
     *
     * @return abschnitt[]
     */
    private static function eigene(): array {
        global $USER;

        if (!isloggedin() || isguestuser()) {
            return [];
        }

        $userid = (int) $USER->id;
        $system = context_system::instance();
        $lehre = hook_callbacks::sieht_meine_lehre($userid);
        $lernende = hook_callbacks::sieht_meine_lernenden($userid);
        $bloecke = has_capability('local/berufsbildung:manageblocks', $system);
        $zuordnung = has_capability('local/berufsbildung:viewzuordnung', $system);
        $abschnitte = [];

        if ($lehre || $lernende || $bloecke || $zuordnung) {
            $abschnitte[] = self::abschnitt(abschnitt::GRUNDLAGEN, 'ausbildungsstand');
            $abschnitte[] = self::abschnitt(abschnitt::GRUNDLAGEN, 'versetzungsplan');
            $abschnitte[] = self::abschnitt(abschnitt::GRUNDLAGEN, 'kompetenzraster');
        }
        if ($lehre) {
            $abschnitte[] = self::abschnitt(abschnitt::LERNENDE, 'meine_lehre');
        }
        if ($lernende) {
            $abschnitte[] = self::abschnitt(abschnitt::BERUFSBILDNER, 'meine_lernenden', ['bloecke' => $bloecke]);
        }
        if ($bloecke) {
            $abschnitte[] = self::abschnitt(abschnitt::PLANUNG, 'ausbildungsbloecke');
            $abschnitte[] = self::abschnitt(abschnitt::PLANUNG, 'kompetenzrahmen');
        }
        if (has_capability('local/berufsbildung:importplan', $system)) {
            $abschnitte[] = self::abschnitt(abschnitt::PLANUNG, 'planimport');
        }
        if ($zuordnung) {
            $abschnitte[] = self::abschnitt(abschnitt::LEITUNG, 'zuordnungen', [
                'darfaendern' => has_capability('local/berufsbildung:managezuordnung', $system),
            ]);
        }
        if (has_capability('local/berufsbildung:managezuordnung', $system)) {
            $abschnitte[] = self::abschnitt(abschnitt::LEITUNG, 'globale_gruppen');
        }
        if (has_capability('local/berufsbildung:manageaufbewahrung', $system)) {
            $abschnitte[] = self::abschnitt(abschnitt::LEITUNG, 'aufbewahrung');
        }
        if (has_capability('moodle/site:config', $system)) {
            $abschnitte[] = self::abschnitt(abschnitt::ADMINISTRATION, 'einrichtung');
        }

        return $abschnitte;
    }

    /**
     * Ein Abschnitt dieses Plugins: Template unter templates/hilfe/, Titel
     * im Sprach-String hilfe:<name>, Anker berufsbildung-<name>.
     *
     * @param string $zielgruppe
     * @param string $name
     * @param array $kontext
     * @return abschnitt
     */
    private static function abschnitt(string $zielgruppe, string $name, array $kontext = []): abschnitt {
        return new abschnitt(
            $zielgruppe,
            'berufsbildung-' . str_replace('_', '-', $name),
            get_string('hilfe:' . $name, 'local_berufsbildung'),
            'local_berufsbildung/hilfe/' . $name,
            $kontext
        );
    }

    /**
     * Die Abschnitte der aufsetzenden Plugins. Ein Plugin, dessen Callback
     * scheitert, faellt weg, statt die ganze Seite zu brechen.
     *
     * @return abschnitt[]
     */
    private static function gemeldete(): array {
        $abschnitte = [];
        foreach (get_plugins_with_function(self::CALLBACK) as $funktionenjetyp) {
            foreach ($funktionenjetyp as $plugin => $funktion) {
                try {
                    $gemeldet = $funktion();
                } catch (\Throwable $e) {
                    debugging("{$plugin}_" . self::CALLBACK . '(): ' . $e->getMessage(), DEBUG_DEVELOPER);
                    continue;
                }
                foreach ($gemeldet as $einzel) {
                    if ($einzel instanceof abschnitt) {
                        $abschnitte[] = $einzel;
                    }
                }
            }
        }

        return $abschnitte;
    }
}

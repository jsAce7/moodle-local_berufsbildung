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
 * Sortierung und Filterung des Lernenden-Rosters fuer meine_lernenden.php.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\output;

use core_text;
use local_berufsbildung\ausbildungsstand;
use local_berufsbildung\nachweis\station;

/**
 * Reine Array-Operationen auf dem Roster (id, name, Ausbildungsstand) -
 * keine DB-Zugriffe, deshalb ohne Weiteres unit-testbar.
 *
 * Eintraege haben die Form array{id: int, name: string, stand: ?ausbildungsstand,
 * istextern?: bool, handlungsbedarf?: int}.
 */
class lernenden_roster {
    /** @var int Etwas ist ueberfaellig. */
    public const HANDLUNGSBEDARF_UEBERFAELLIG = 0;

    /** @var int Etwas ist jetzt zu tun, die Frist laeuft noch. */
    public const HANDLUNGSBEDARF_OFFEN = 1;

    /** @var int Nichts zu tun. */
    public const HANDLUNGSBEDARF_KEINER = 2;

    /**
     * Sortiert reguläre Lernende nach laufendem Semester (aufsteigend),
     * dann nach Namen. Externe üK-Teilnehmende stehen immer zuletzt.
     *
     * Erwartet die Eintraege bereits alphabetisch nach Namen sortiert (siehe
     * core_collator::asort() in meine_lernenden.php) - PHP-usort ist seit
     * PHP 8 stabil, deshalb bleibt diese Reihenfolge innerhalb eines
     * Semesters unangetastet, ohne selbst eine locale-bewusste
     * Zeichenkettenvergleichsfunktion nachbauen zu muessen.
     *
     * Personen ohne Ausbildungsstand (Lehre noch nicht begonnen oder
     * bereits beendet - api::get_ausbildungsstand() liefert fuer beide
     * null, siehe docs/schnitt1.md) stehen zuletzt, untereinander
     * weiterhin alphabetisch.
     *
     * @param array $eintraege Struktur: array<int, array{id: int, name: string, stand: ?ausbildungsstand, istextern?: bool}>
     * @return array<int, array{id: int, name: string, stand: ?ausbildungsstand, istextern?: bool}>
     */
    public static function sortiere(array $eintraege): array {
        usort($eintraege, static function (array $a, array $b): int {
            $externa = !empty($a['istextern']);
            $externb = !empty($b['istextern']);
            if ($externa !== $externb) {
                return $externa <=> $externb;
            }

            $semestera = $a['stand']?->semester ?? PHP_INT_MAX;
            $semesterb = $b['stand']?->semester ?? PHP_INT_MAX;

            return $semestera <=> $semesterb;
        });

        return $eintraege;
    }

    /**
     * Stellt Personen mit Handlungsbedarf voran: zuerst wer etwas
     * Ueberfaelliges hat, dann wer etwas Offenes hat, dann alle uebrigen.
     * Bei 16 Lernenden sonst erst nach dem Scrollen sichtbar, wer wartet.
     *
     * Erwartet die Eintraege bereits nach sortiere() geordnet - usort ist
     * stabil, innerhalb einer Stufe bleibt diese Reihenfolge. Ohne
     * 'handlungsbedarf' gilt HANDLUNGSBEDARF_KEINER.
     *
     * @param array $eintraege Struktur: array<int, array{id: int, name: string, stand: ?ausbildungsstand,
     *        handlungsbedarf?: int}>
     * @return array
     */
    public static function nach_handlungsbedarf(array $eintraege): array {
        usort($eintraege, static fn (array $a, array $b): int =>
            ($a['handlungsbedarf'] ?? self::HANDLUNGSBEDARF_KEINER) <=> ($b['handlungsbedarf'] ?? self::HANDLUNGSBEDARF_KEINER));

        return $eintraege;
    }

    /**
     * Handlungsbedarf aus den aktuellen Stationen: ueberfaellig, wenn eine
     * ueberfaellig ist, sonst offen, wenn eine offen ist. Mehr als $stufe
     * wird es nie - so laesst sich Ueberfaelliges aus den Faelligkeiten
     * vorgeben.
     *
     * @param \local_berufsbildung\nachweis\stationen[] $stationen
     * @param int $stufe Handlungsbedarf aus anderen Quellen, sonst HANDLUNGSBEDARF_KEINER
     * @return int Eine der Konstanten HANDLUNGSBEDARF_*
     */
    public static function handlungsbedarf_aus_stationen(array $stationen, int $stufe = self::HANDLUNGSBEDARF_KEINER): int {
        foreach ($stationen as $einzelstationen) {
            $stufe = match ($einzelstationen->aktuelle()?->status) {
                station::STATUS_UEBERFAELLIG => self::HANDLUNGSBEDARF_UEBERFAELLIG,
                station::STATUS_OFFEN => min($stufe, self::HANDLUNGSBEDARF_OFFEN),
                default => $stufe,
            };
        }

        return $stufe;
    }

    /**
     * Filtert nach Namens-Teilstring (Gross-/Kleinschreibung egal),
     * optional exakt nach Beruf-Code und optional auf Personen mit
     * Handlungsbedarf. Leere Filter aendern nichts.
     *
     * @param array $eintraege Struktur: array<int, array{id: int, name: string, stand: ?ausbildungsstand,
     *        handlungsbedarf?: int}>
     * @param string $suchbegriff
     * @param string $beruf Exakter Beruf-Code, z.B. 'AU_EFZ' - leer = alle Berufe
     * @param bool $nurhandlungsbedarf Nur wer etwas Ueberfaelliges oder Offenes hat
     * @return array<int, array{id: int, name: string, stand: ?ausbildungsstand}>
     */
    public static function filtere(array $eintraege, string $suchbegriff, string $beruf, bool $nurhandlungsbedarf = false): array {
        $suchbegriff = trim($suchbegriff);

        if ($suchbegriff === '' && $beruf === '' && !$nurhandlungsbedarf) {
            return $eintraege;
        }

        $suchbegriffklein = core_text::strtolower($suchbegriff);

        return array_values(array_filter(
            $eintraege,
            static function (array $eintrag) use ($suchbegriffklein, $beruf, $nurhandlungsbedarf): bool {
                if ($beruf !== '' && $eintrag['stand']?->beruf !== $beruf) {
                    return false;
                }

                if (
                    $nurhandlungsbedarf
                    && ($eintrag['handlungsbedarf'] ?? self::HANDLUNGSBEDARF_KEINER) === self::HANDLUNGSBEDARF_KEINER
                ) {
                    return false;
                }

                if ($suchbegriffklein !== '' && !str_contains(core_text::strtolower($eintrag['name']), $suchbegriffklein)) {
                    return false;
                }

                return true;
            }
        ));
    }

    /**
     * Alle im Roster vorkommenden Beruf-Codes, eindeutig und sortiert -
     * fuer die Beruf-Auswahl der Toolbar. Personen ohne Ausbildungsstand
     * liefern keinen Beruf und tauchen hier nicht auf.
     *
     * @param array $eintraege Struktur: array<int, array{id: int, name: string, stand: ?ausbildungsstand}>
     * @return string[]
     */
    public static function berufe(array $eintraege): array {
        $berufe = [];
        foreach ($eintraege as $eintrag) {
            if ($eintrag['stand'] !== null) {
                $berufe[$eintrag['stand']->beruf] = true;
            }
        }

        $berufe = array_keys($berufe);
        sort($berufe);

        return $berufe;
    }
}

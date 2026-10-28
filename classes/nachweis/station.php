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
 * Wertobjekt fuer eine Station im Verlauf der Lehre aus einer registrierten Quelle.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\nachweis;

/**
 * Ein Schritt, den eine Quelle ueber die ganze Lehre hinweg erwartet, etwa
 * der Bildungsbericht eines Semesters - mit seinem Stand, ob erledigt,
 * offen oder erst geplant. Anders als `nachweis` (nur Erledigtes) und
 * `ausstehend` (nur Kommendes) steht hier beides in einer Folge, damit
 * "Meine Lernenden" die Schritte als Zeitleiste zeigen kann.
 *
 * Den Status deutet das Basis-Plugin nur fuer Symbol und Farbe; was er
 * bedeutet, sagt $statustext, von der Quelle formuliert ("freigegeben",
 * "in Arbeit") - Farbe allein traegt keine Information (WCAG 1.4.1).
 *
 * Einzelne readonly-Properties statt einer readonly-Klasse, damit die
 * Klasse auch unter PHP 8.1 (Moodle 4.5) funktioniert.
 */
class station {
    /** @var string Erledigt, z.B. ein freigegebener Bericht. */
    public const STATUS_ERLEDIGT = 'erledigt';

    /** @var string Jetzt zu erledigen, die Frist laeuft noch. */
    public const STATUS_OFFEN = 'offen';

    /** @var string Jetzt zu erledigen, die Frist ist vorbei. */
    public const STATUS_UEBERFAELLIG = 'ueberfaellig';

    /** @var string Kommt spaeter. */
    public const STATUS_GEPLANT = 'geplant';

    /** @var string Hier wird nichts (mehr) erwartet, etwa ein Semester vor der Erfassung der Person. */
    public const STATUS_ENTFAELLT = 'entfaellt';

    /**
     * Konstruktor.
     *
     * @param string $quellekey Schluessel der liefernden Quelle
     * @param string $bezeichnung z.B. "Bildungsbericht 3. Semester", fuer sich allein verstaendlich
     * @param string $status Eine der Konstanten STATUS_*
     * @param string $statustext z.B. "in Arbeit", von der Quelle formuliert
     * @param string|null $stand Weitere Angaben, z.B. "Fällig bis 31. Januar 2027 · 14 von 22 Angaben erfasst"
     * @param string|null $url Wohin ein Klick auf die Station fuehrt, null ohne Ziel
     * @param bool $aktuell Die Station, die fuer die Person gerade zaehlt - sie steht
     *        auch in der geschlossenen Kachel. Hoechstens eine je Quelle.
     * @param string|null $kurzbezeichnung z.B. "3. Semester", fuer die Zeitleiste unter dem
     *        Namen der Quelle; null nimmt dort $bezeichnung.
     */
    public function __construct(
        /** @var string Schluessel der liefernden Quelle. */
        public readonly string $quellekey,
        /** @var string z.B. "Bildungsbericht 3. Semester". */
        public readonly string $bezeichnung,
        /** @var string Eine der Konstanten STATUS_*. */
        public readonly string $status,
        /** @var string Bedeutung des Status in Worten. */
        public readonly string $statustext,
        /** @var string|null Weitere Angaben zum Stand. */
        public readonly ?string $stand = null,
        /** @var string|null Ziel eines Klicks auf die Station. */
        public readonly ?string $url = null,
        /** @var bool Die Station, die gerade zaehlt. */
        public readonly bool $aktuell = false,
        /** @var string|null Kurz fuer die Zeitleiste, z.B. "3. Semester". */
        public readonly ?string $kurzbezeichnung = null,
    ) {
    }

    /**
     * Dieselbe Station mit dem Schluessel der registrierten Quelle - der
     * Collector setzt ihn, damit sich eine Quelle nicht als andere ausgibt.
     *
     * @param string $quellekey
     * @return self
     */
    public function mit_quellekey(string $quellekey): self {
        return new self(
            $quellekey,
            $this->bezeichnung,
            $this->status,
            $this->statustext,
            $this->stand,
            $this->url,
            $this->aktuell,
            $this->kurzbezeichnung
        );
    }
}

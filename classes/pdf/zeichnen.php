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
 * Gemeinsame Zeichenbausteine aller PDF-Dokumente der Berufsbildung.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\pdf;

use pdf;

/**
 * Seitenmasse und die Bausteine, aus denen die Renderer der üK-Nachweise
 * (local_uekkn) und der Bildungsberichte (local_bildungsbericht) ihre
 * Dokumente setzen: Abschnittstitel, Tabellenkopf, Tabellenzeile,
 * Infofeld, Wasserzeichen. Was ein Dokument enthaelt und in welcher
 * Reihenfolge, entscheidet der Renderer; wie ein Baustein aussieht, steht
 * nur hier.
 *
 * Seitenraender 20 mm beidseitig, damit beide Seiten im Duplexdruck
 * gelocht werden koennen.
 */
class zeichnen {
    /** Seitenrand links und rechts, in mm. */
    public const RAND_SEITE = 20.0;

    /** Seitenrand oben, in mm. */
    public const RAND_OBEN = 15.0;

    /** Nutzbare Breite in mm, A4 minus beide Raender. */
    public const BREITE = 170.0;

    /** Grundzeilenhoehe in mm. */
    public const ZEILE = 5.0;

    /** Mindesthoehe einer Tabellenzeile in mm. */
    public const ZEILE_MIN = 6.0;

    /** Abstand des Laufkopfs vom oberen Rand, in mm. */
    public const KOPFRAND = 9.0;

    /**
     * Ein Feld des Infofelds: Beschriftung darueber, Wert darunter. Ein zu
     * langer Wert wird verkleinert statt in die Nachbarspalte zu laufen.
     *
     * @param pdf $pdf
     * @param float $x
     * @param float $y
     * @param float $breite
     * @param string $bezeichnung
     * @param string $wert
     */
    public static function infofeld(pdf $pdf, float $x, float $y, float $breite, string $bezeichnung, string $wert): void {
        $schrift = gestaltung::schrift();

        $pdf->SetXY($x, $y);
        $pdf->SetFont($schrift, '', 7.5);
        $pdf->SetFontSpacing(0.3);
        self::farbe_text($pdf, gestaltung::GRAU);
        $pdf->Cell($breite, 3.6, \core_text::strtoupper($bezeichnung), 0, 2, 'L');
        $pdf->SetFontSpacing(0);

        self::farbe_text($pdf, gestaltung::TEXT);
        $pdf->SetFont($schrift, 'B', 10.5);
        $pdf->Cell($breite, 5.4, $wert !== '' ? $wert : '—', 0, 2, 'L', false, '', 1);
    }

    /**
     * Ein Beschriftungs-Wert-Paar in einer Zeile.
     *
     * @param pdf $pdf
     * @param string $bezeichnung
     * @param string $wert
     * @param float $beschriftungsbreite Breite der Beschriftung in mm
     */
    public static function feldpaar(pdf $pdf, string $bezeichnung, string $wert, float $beschriftungsbreite = 45.0): void {
        $schrift = gestaltung::schrift();

        $pdf->SetFont($schrift, '', 10);
        self::farbe_text($pdf, gestaltung::GRAU);
        $pdf->Cell($beschriftungsbreite, self::ZEILE, $bezeichnung, 0, 0, 'L');
        self::farbe_text($pdf, gestaltung::TEXT);
        $pdf->SetFont($schrift, 'B', 10);
        $pdf->Cell(0, self::ZEILE, $wert !== '' ? $wert : '—', 0, 1, 'L');
    }

    /**
     * Ueberschrift eines Abschnitts, in der Akzentfarbe und leicht
     * gesperrt. Getrennt wird durch Farbe und Abstand, nicht durch eine
     * Linie - die Tabelle darunter beginnt mit einem eigenen Farbband.
     *
     * @param pdf $pdf
     * @param string $text
     */
    public static function abschnittstitel(pdf $pdf, string $text): void {
        $schrift = gestaltung::schrift();

        $pdf->SetFont($schrift, 'B', 11);
        $pdf->SetFontSpacing(0.25);
        self::farbe_text($pdf, gestaltung::akzent());
        $pdf->Cell(0, 6, $text, 0, 1, 'L');
        $pdf->SetFontSpacing(0);
        self::farbe_text($pdf, gestaltung::TEXT);
        $pdf->SetFont($schrift, '', 9);
        $pdf->Ln(1);
    }

    /**
     * Tabellenkopfzeile: ein Farbband mit ausgesparter Schrift.
     *
     * @param pdf $pdf
     * @param string[] $texte
     * @param float[] $breiten
     * @param string[]|null $ausrichtung
     */
    public static function kopfzeile(pdf $pdf, array $texte, array $breiten, ?array $ausrichtung = null): void {
        $schrift = gestaltung::schrift();
        $pdf->SetFont($schrift, 'B', 8);
        self::farbe_fuellung($pdf, gestaltung::akzent());
        self::farbe_text($pdf, [255, 255, 255]);

        foreach ($texte as $index => $text) {
            $letzte = $index === count($texte) - 1;
            $pdf->Cell($breiten[$index], 6.5, $text, 0, $letzte ? 1 : 0, $ausrichtung[$index] ?? 'L', true);
        }

        self::farbe_text($pdf, gestaltung::TEXT);
        $pdf->SetFont($schrift, '', 8);
    }

    /**
     * Zeile ueber die ganze Tabellenbreite, die Zeilen gruppiert - in einem
     * Ton der Akzentfarbe, ein Drittel des Wegs zum Kopfband. Eine
     * Gruppenueberschrift allein am Seitenende gehoert zu nichts; ohne
     * Platz fuer sie und eine erste zweizeilige Zeile wandert sie mit.
     *
     * @param pdf $pdf
     * @param string $text
     * @param float[] $breiten
     * @param string[]|null $kopf Nach einem Seitenumbruch wiederholt
     * @param string[]|null $ausrichtung
     */
    public static function gruppenzeile(
        pdf $pdf,
        string $text,
        array $breiten,
        ?array $kopf = null,
        ?array $ausrichtung = null
    ): void {
        if ($pdf->GetY() + 15.0 > $pdf->getPageHeight() - $pdf->getBreakMargin()) {
            self::seitenumbruch($pdf, $kopf, $breiten, $ausrichtung);
        }

        $schrift = gestaltung::schrift();
        $pdf->SetFont($schrift, 'B', 8);
        self::farbe_fuellung($pdf, gestaltung::ton(0.16));
        self::farbe_text($pdf, gestaltung::akzent());
        $pdf->Cell(array_sum($breiten), 5.5, $text, 0, 1, 'L', true);
        self::farbe_text($pdf, gestaltung::TEXT);
        $pdf->SetFont($schrift, '', 8);
    }

    /**
     * Seitenumbruch mit wiederholter Kopfzeile einer Tabelle - eine
     * Tabelle, die weiterlaeuft, verliert sonst am Falz die Bedeutung
     * ihrer Spalten.
     *
     * @param pdf $pdf
     * @param string[]|null $kopf
     * @param float[] $breiten
     * @param string[]|null $ausrichtung
     */
    public static function seitenumbruch(pdf $pdf, ?array $kopf, array $breiten, ?array $ausrichtung = null): void {
        $pdf->AddPage();
        if ($kopf) {
            self::kopfzeile($pdf, $kopf, $breiten, $ausrichtung);
        }
    }

    /**
     * Setzt einen Block so, dass ihn kein Seitenumbruch zerschneidet.
     *
     * TCPDF weiss erst nach dem Setzen, wie hoch ein Block wird. Deshalb
     * wird er in einer Transaktion gesetzt; laeuft er auf eine zweite Seite
     * ueber, wird der Versuch zurueckgenommen und der Block auf einer neuen
     * Seite noch einmal gesetzt.
     *
     * Zurueckgenommen wird auf demselben Objekt (rollbackTransaction(true)):
     * ohne das Argument zerstoert TCPDF das Objekt, das der Aufrufer in der
     * Hand haelt, und gibt eine Kopie zurueck - wer den Rueckgabewert nicht
     * weiterreichte, arbeitete danach auf einem leeren Dokument weiter.
     *
     * @param pdf $pdf
     * @param callable $block Setzt den Block auf das uebergebene Dokument
     * @return pdf Dasselbe Objekt
     */
    public static function zusammenhaengend(pdf $pdf, callable $block): pdf {
        $startseite = $pdf->getPage();
        $pdf->startTransaction();
        $block($pdf);

        if ($pdf->getPage() > $startseite) {
            $pdf->rollbackTransaction(true);
            $pdf->AddPage();
            $block($pdf);
        } else {
            $pdf->commitTransaction();
        }

        return $pdf;
    }

    /**
     * Tabellenzeile, deren Hoehe sich nach der hoechsten Zelle richtet, der
     * Inhalt senkrecht zentriert, darunter eine Haarlinie in einem Ton der
     * Akzentfarbe und keine Linien zwischen den Spalten.
     *
     * @param pdf $pdf
     * @param string[] $texte
     * @param float[] $breiten
     * @param string[] $ausrichtung
     * @param string[]|null $kopf Nach einem Seitenumbruch wiederholt
     */
    public static function zeile(pdf $pdf, array $texte, array $breiten, array $ausrichtung, ?array $kopf = null): void {
        $luft = 2 * gestaltung::LUFT_Y;
        $hoehe = self::ZEILE_MIN;
        foreach ($texte as $index => $text) {
            $hoehe = max($hoehe, $pdf->getStringHeight($breiten[$index], $text) + $luft);
        }

        if ($pdf->GetY() + $hoehe > $pdf->getPageHeight() - $pdf->getBreakMargin()) {
            self::seitenumbruch($pdf, $kopf, $breiten, $ausrichtung);
        }

        $xstart = $pdf->GetX();
        $y = $pdf->GetY();

        $x = $xstart;
        foreach ($texte as $index => $text) {
            $pdf->MultiCell(
                $breiten[$index],
                $hoehe,
                $text,
                0,
                $ausrichtung[$index] ?? 'L',
                false,
                0,
                $x,
                $y,
                true,
                0,
                false,
                true,
                $hoehe,
                'M'
            );
            $x += $breiten[$index];
        }

        self::haarlinie($pdf, $xstart, $y + $hoehe, array_sum($breiten));

        $pdf->SetXY(self::RAND_SEITE, $y + $hoehe);
    }

    /**
     * Haarlinie unter einer Tabellenzeile.
     *
     * @param pdf $pdf
     * @param float $x
     * @param float $y
     * @param float $breite
     */
    public static function haarlinie(pdf $pdf, float $x, float $y, float $breite): void {
        $strichbreite = $pdf->GetLineWidth();
        $pdf->SetLineWidth(0.1);
        self::farbe_linie($pdf, gestaltung::ton(0.28));
        $pdf->Line($x, $y, $x + $breite, $y);
        $pdf->SetLineWidth($strichbreite);
        self::farbe_linie($pdf, gestaltung::TEXT);
    }

    /**
     * Schraeg gestellter, blasser Schriftzug ueber jeder Seite eines
     * Dokuments, das nichts bescheinigt (Vorschau, Muster). Ueber den
     * Inhalt gelegt und blass gehalten: von weitem erkennbar, ohne eine
     * Zeile unlesbar zu machen. Erst nach dem Setzen aufzurufen, wenn die
     * Seitenzahl feststeht.
     *
     * @param pdf $pdf
     * @param string $text
     */
    public static function wasserzeichen(pdf $pdf, string $text): void {
        $seiten = $pdf->getNumPages();

        for ($seite = 1; $seite <= $seiten; $seite++) {
            $pdf->setPage($seite);
            $pdf->StartTransform();
            $pdf->SetAlpha(0.12);
            $pdf->SetTextColor(180, 0, 0);
            $pdf->SetFont(gestaltung::schrift(), 'B', 52);
            $pdf->Rotate(45, self::RAND_SEITE, 230);
            $pdf->SetXY(self::RAND_SEITE, 230);
            $pdf->Cell(self::BREITE, 20, $text, 0, 0, 'C');
            $pdf->StopTransform();
            $pdf->SetAlpha(1);
        }

        self::farbe_text($pdf, gestaltung::TEXT);
    }

    /**
     * Setzt die Textfarbe.
     *
     * @param pdf $pdf
     * @param int[] $rgb
     */
    public static function farbe_text(pdf $pdf, array $rgb): void {
        $pdf->SetTextColor($rgb[0], $rgb[1], $rgb[2]);
    }

    /**
     * Setzt die Fuellfarbe.
     *
     * @param pdf $pdf
     * @param int[] $rgb
     */
    public static function farbe_fuellung(pdf $pdf, array $rgb): void {
        $pdf->SetFillColor($rgb[0], $rgb[1], $rgb[2]);
    }

    /**
     * Setzt die Linienfarbe.
     *
     * @param pdf $pdf
     * @param int[] $rgb
     */
    public static function farbe_linie(pdf $pdf, array $rgb): void {
        $pdf->SetDrawColor($rgb[0], $rgb[1], $rgb[2]);
    }

    /**
     * Reiner Text aus einem gespeicherten Feld, HTML entfernt.
     *
     * @param string|null $wert
     * @return string
     */
    public static function text(?string $wert): string {
        if ($wert === null || $wert === '') {
            return '';
        }

        return trim(html_to_text($wert, 0, false));
    }

    /**
     * Ein Zeitstempel als lesbares Datum, oder ein Gedankenstrich.
     *
     * @param int|null $zeitstempel
     * @return string
     */
    public static function datum(?int $zeitstempel): string {
        if (empty($zeitstempel)) {
            return '—';
        }

        return userdate($zeitstempel, get_string('strftimedate', 'langconfig'));
    }
}

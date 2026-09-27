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
 * Das gerenderte Dokument mit Laufkopf und Laufffuss, fuer alle
 * PDF-Dokumente der Berufsbildung.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\pdf;

/**
 * TCPDF ruft Header() und Footer() selbst bei jedem Seitenwechsel auf,
 * deshalb werden Titel, Kennung und Laufkopf vor dem Rendern gesetzt statt
 * inline geschrieben.
 */
class dokument extends \pdf {
    /** @var string Art des Dokuments, links im Fuss jeder Seite. */
    private string $titel = '';

    /** @var string Kennung der Person, mittig im Fuss. */
    private string $kennung = '';

    /** @var string Schriftfamilie des ganzen Dokuments. */
    private string $schrift = 'helvetica';

    /** @var string Links im Laufkopf. */
    private string $laufkopflinks = '';

    /** @var string Rechts im Laufkopf. */
    private string $laufkopfrechts = '';

    /**
     * Setzt die Art des Dokuments.
     *
     * @param string $titel
     */
    public function titel_setzen(string $titel): void {
        $this->titel = $titel;
    }

    /**
     * Setzt die Kennung der Person.
     *
     * @param string $kennung
     */
    public function kennung_setzen(string $kennung): void {
        $this->kennung = $kennung;
    }

    /**
     * Setzt die Schriftfamilie des Laufkopfs und -fusses.
     *
     * @param string $schrift
     */
    public function schrift_setzen(string $schrift): void {
        $this->schrift = $schrift;
    }

    /**
     * Setzt den Inhalt des Laufkopfs ab der zweiten Seite.
     *
     * @param string $links
     * @param string $rechts
     */
    public function laufkopf_setzen(string $links, string $rechts): void {
        $this->laufkopflinks = $links;
        $this->laufkopfrechts = $rechts;
    }

    // TCPDF ruft diese beiden Methoden unter genau diesem Namen auf.
    // phpcs:disable moodle.NamingConventions.ValidFunctionName.LowercaseMethod

    /**
     * Druckt den Laufkopf der aktuellen Seite.
     */
    public function Header(): void {
        if ($this->getPage() < 2 || ($this->laufkopflinks === '' && $this->laufkopfrechts === '')) {
            return;
        }

        $breite = $this->getPageWidth() - $this->original_lMargin - $this->original_rMargin;
        $spalte = $breite / 2;
        $oben = $this->GetY();
        $akzent = gestaltung::akzent();
        $grau = gestaltung::GRAU;

        $this->SetFont($this->schrift, 'B', 8);
        $this->SetTextColor($akzent[0], $akzent[1], $akzent[2]);
        $this->Cell($spalte, 4, $this->laufkopflinks, 0, 0, 'L', false, '', 1);
        $this->SetFont($this->schrift, '', 8);
        $this->SetTextColor($grau[0], $grau[1], $grau[2]);
        $this->Cell($spalte, 4, $this->laufkopfrechts, 0, 0, 'R', false, '', 1);

        $linie = gestaltung::ton(0.35);
        $this->SetDrawColor($linie[0], $linie[1], $linie[2]);
        $this->Line($this->original_lMargin, $oben + 4.8, $this->original_lMargin + $breite, $oben + 4.8);

        $this->zuruecksetzen();
    }

    /**
     * Druckt den Fuss der aktuellen Seite.
     */
    public function Footer(): void {
        $breite = $this->getPageWidth() - $this->original_lMargin - $this->original_rMargin;
        $spalte = $breite / 3;
        $linie = gestaltung::ton(0.35);
        $grau = gestaltung::GRAU;

        $this->SetY(-15);
        $this->SetDrawColor($linie[0], $linie[1], $linie[2]);
        $this->Line($this->original_lMargin, $this->GetY(), $this->original_lMargin + $breite, $this->GetY());
        $this->Ln(1.5);

        $this->SetFont($this->schrift, '', 7);
        $this->SetTextColor($grau[0], $grau[1], $grau[2]);
        $seite = get_string('pdf:seite', 'local_berufsbildung', (object) [
            'nr' => $this->getAliasNumPage(),
            'gesamt' => $this->getAliasNbPages(),
        ]);
        $this->Cell($spalte, 4, $this->titel, 0, 0, 'L');
        $this->Cell($spalte, 4, $this->kennung, 0, 0, 'C');
        $this->Cell($spalte, 4, $seite, 0, 0, 'R');

        $this->zuruecksetzen();
    }

    // phpcs:enable moodle.NamingConventions.ValidFunctionName.LowercaseMethod

    /**
     * Setzt die Farben auf den Stand des Fliesstexts zurueck.
     */
    private function zuruecksetzen(): void {
        $text = gestaltung::TEXT;

        $this->SetTextColor($text[0], $text[1], $text[2]);
        $this->SetDrawColor($text[0], $text[1], $text[2]);
    }
}

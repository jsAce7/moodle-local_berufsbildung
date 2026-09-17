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
 * Optionale Zusatzschnittstelle fuer Quellen mit einem Hinweis zur Erfassung.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\nachweis;

/**
 * Zusaetzlich zu `erfassbare_quelle` implementierbar von Quellen, die neben
 * der Schaltflaeche eine kurze Angabe dazu machen koennen, bis wann oder
 * wofuer zu erfassen ist - "Meine Lehre" zeigt sie unter der Schaltflaeche.
 *
 * Bewusst von `erfassbare_quelle` getrennt und nicht als zusaetzliche
 * Methode dort: eine Quelle kann eine Erfassung anbieten, ohne dazu eine
 * Frist oder sonst etwas Sinnvolles sagen zu koennen. Getrennt gehalten
 * bleibt zudem eine Quelle lauffaehig, die dieses Plugin in einer aelteren
 * Fassung kennt.
 *
 * Wirkt nur zusammen mit `erfassbare_quelle` - ohne Erfassungs-URL gibt es
 * keine Aktion, an der ein Hinweis haengen koennte.
 */
interface quelle_mit_hinweis {
    /**
     * Kurzer Hinweis zur Erfassung, z.B. "Naechster Eintrag faellig bis
     * 1. Oktober 2026", oder null, wenn aktuell nichts zu sagen ist. Der
     * Text steht unter der Schaltflaeche und traegt seine Bedeutung selbst:
     * das Basis-Plugin hebt ihn nicht hervor und deutet ihn nicht - eine
     * ueberschrittene Frist muss die Quelle also ausschreiben (WCAG 1.4.1,
     * Farbe allein darf keine Information tragen).
     *
     * Wird nur fuer die eigene Person aufgerufen, und nur wenn die Quelle
     * zuvor eine Erfassungs-URL geliefert hat - siehe
     * collector::get_erfassen_aktionen().
     *
     * @param int $lernendeid
     * @return string|null
     */
    public function get_erfassen_hinweis(int $lernendeid): ?string;
}

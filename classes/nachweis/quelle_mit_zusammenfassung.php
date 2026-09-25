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
 * Optionale Zusatzschnittstelle fuer Quellen mit einer Zusammenfassung ihrer Nachweise.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\nachweis;

/**
 * Zusaetzlich zu `provider` implementierbar von Quellen, die ueber ihre
 * Nachweise eine kurze Angabe machen koennen - etwa den Schnitt der
 * ueK-Noten. Die Taetigkeitenliste zeigt sie im Kopf der Gruppe dieser
 * Quelle (siehe nachweis_liste).
 *
 * Die Rechnung liegt bewusst in der Quelle: `nachweis` kennt keine
 * Notenlogik und keine Skalen, ein Ergebnis darf auch "bestanden" sein.
 * Nur die Quelle weiss, ob und wie sich ihre Ergebnisse zusammenfassen
 * lassen und wie gerundet wird. Das Basis-Plugin gibt den Text unveraendert
 * aus und deutet ihn nicht.
 *
 * Die Quelle erhaelt die Nachweise, die der Collector der abrufenden Person
 * bereits ausgeliefert hat, und keine Personen-ID, mit der sie selbst
 * nachladen koennte. Damit fasst sie genau das zusammen, was daneben in der
 * Liste steht - bei der lernenden Person also nur, was sie selbst sehen
 * darf -, und die Zustaendigkeitspruefung bleibt im Collector
 * (Architekturregel 7).
 *
 * Getrennt von `provider` gehalten, damit eine Quelle ohne Zusammenfassung
 * nichts implementieren muss und eine Quelle mit Zusammenfassung pruefen
 * kann, ob dieses Plugin die Schnittstelle schon kennt.
 */
interface quelle_mit_zusammenfassung {
    /**
     * Kurze Zusammenfassung, z.B. "Schnitt 5.5", oder null, wenn es nichts
     * zusammenzufassen gibt - etwa weil keiner der Nachweise ein Ergebnis
     * traegt.
     *
     * @param nachweis[] $nachweise Nachweise dieser Quelle, nie leer
     * @return string|null
     */
    public function get_zusammenfassung(array $nachweise): ?string;
}

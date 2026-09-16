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
 * Tests fuer Sortierung und Filterung des Lernenden-Rosters.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\output;

use advanced_testcase;
use local_berufsbildung\ausbildungsstand;

/**
 * Tests fuer lernenden_roster.
 *
 * @covers \local_berufsbildung\output\lernenden_roster
 */
final class lernenden_roster_test extends advanced_testcase {
    /**
     * @param int $semester
     * @param string $beruf
     */
    private function stand(int $semester, string $beruf = 'PM_EFZ'): ausbildungsstand {
        return new ausbildungsstand(
            beruf: $beruf,
            jahrgang: 2024,
            semester: $semester,
            lehrjahr: (int) ceil($semester / 2),
            semestervon: 1000,
            semesterbis: 2000,
            gesamtsemester: 8,
        );
    }

    /**
     * @param string $name
     * @param ?ausbildungsstand $stand
     * @return array{id: int, name: string, stand: ?ausbildungsstand}
     */
    private function eintrag(int $id, string $name, ?ausbildungsstand $stand): array {
        return ['id' => $id, 'name' => $name, 'stand' => $stand];
    }

    /**
     * Hauptfall: Sortierung nach Semester, dann nach Namen. Die Eintraege
     * kommen hier bereits alphabetisch sortiert herein (wie es
     * meine_lernenden.php ueber core_collator::asort() liefert) - innerhalb
     * desselben Semesters muss diese Reihenfolge erhalten bleiben.
     */
    public function test_sortiert_nach_semester_dann_nach_namen(): void {
        $this->resetAfterTest();

        $eintraege = [
            $this->eintrag(1, 'Anna', $this->stand(2)),
            $this->eintrag(2, 'Ben', $this->stand(1)),
            $this->eintrag(3, 'Clara', $this->stand(2)),
            $this->eintrag(4, 'Dora', $this->stand(1)),
        ];

        $sortiert = lernenden_roster::sortiere($eintraege);

        $this->assertSame(['Ben', 'Dora', 'Anna', 'Clara'], array_column($sortiert, 'name'));
    }

    /**
     * Randfall: Personen ohne Ausbildungsstand (Lehre noch nicht begonnen
     * oder bereits beendet - beide liefern null, siehe
     * api::get_ausbildungsstand()) stehen zuletzt, nicht irgendwo
     * dazwischen.
     */
    public function test_ohne_ausbildungsstand_steht_zuletzt(): void {
        $this->resetAfterTest();

        $eintraege = [
            $this->eintrag(1, 'Aaron', null),
            $this->eintrag(2, 'Ben', $this->stand(3)),
            $this->eintrag(3, 'Zoe', null),
        ];

        $sortiert = lernenden_roster::sortiere($eintraege);

        $this->assertSame(['Ben', 'Aaron', 'Zoe'], array_column($sortiert, 'name'));
    }

    public function test_leerer_filter_liefert_alle_unveraendert(): void {
        $this->resetAfterTest();

        $eintraege = [
            $this->eintrag(1, 'Anna', $this->stand(2)),
            $this->eintrag(2, 'Ben', $this->stand(1)),
        ];

        $this->assertSame($eintraege, lernenden_roster::filtere($eintraege, '', ''));
    }

    public function test_filtert_nach_suchbegriff_gross_klein_und_umlaut_egal(): void {
        $this->resetAfterTest();

        $eintraege = [
            $this->eintrag(1, 'Chiara Bühler', $this->stand(2)),
            $this->eintrag(2, 'Ben Egli', $this->stand(1)),
        ];

        $gefiltert = lernenden_roster::filtere($eintraege, 'BÜHLER', '');

        $this->assertSame(['Chiara Bühler'], array_column($gefiltert, 'name'));
    }

    public function test_filtert_nach_beruf_exakt(): void {
        $this->resetAfterTest();

        $eintraege = [
            $this->eintrag(1, 'Anna', $this->stand(2, 'AU_EFZ')),
            $this->eintrag(2, 'Ben', $this->stand(1, 'PM_EFZ')),
            $this->eintrag(3, 'Clara', null),
        ];

        $gefiltert = lernenden_roster::filtere($eintraege, '', 'PM_EFZ');

        $this->assertSame(['Ben'], array_column($gefiltert, 'name'));
    }

    public function test_berufe_liefert_eindeutige_sortierte_liste(): void {
        $this->resetAfterTest();

        $eintraege = [
            $this->eintrag(1, 'Anna', $this->stand(2, 'PM_EFZ')),
            $this->eintrag(2, 'Ben', $this->stand(1, 'AU_EFZ')),
            $this->eintrag(3, 'Clara', $this->stand(3, 'PM_EFZ')),
            $this->eintrag(4, 'Dora', null),
        ];

        $this->assertSame(['AU_EFZ', 'PM_EFZ'], lernenden_roster::berufe($eintraege));
    }
}

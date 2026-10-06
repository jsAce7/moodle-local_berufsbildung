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
 * Tests fuer die Darstellung des Kompetenzrasters.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\output;

use advanced_testcase;
use local_berufsbildung\raster_bereich;
use local_berufsbildung\raster_kompetenz;

/**
 * Tests fuer kompetenzraster.
 *
 * @covers \local_berufsbildung\output\kompetenzraster
 */
final class kompetenzraster_test extends advanced_testcase {
    /**
     * Ein Bereich mit den uebergebenen Staenden. Die Kompetenzen entstehen
     * im Rahmen, weil das Raster ihre Bezeichnungen aus core_competency
     * nachlaedt - berechnet wird hier nichts, nur dargestellt.
     *
     * @param array $staende Liste von [status, istwahlpflicht]
     * @return raster_bereich
     */
    private function bereich(array $staende): raster_bereich {
        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $rahmen = $generator->create_framework();
        $hkb = $generator->create_competency(['competencyframeworkid' => $rahmen->get('id')]);

        $kompetenzen = [];
        foreach ($staende as [$status, $istwahlpflicht]) {
            $hk = $generator->create_competency([
                'competencyframeworkid' => $rahmen->get('id'),
                'parentid' => $hkb->get('id'),
            ]);
            $kompetenzen[] = new raster_kompetenz((int) $hk->get('id'), $status, $istwahlpflicht);
        }

        return new raster_bereich((int) $hkb->get('id'), $kompetenzen);
    }

    /**
     * Der haeufigste Stand ist der leiseste: "nicht im Plan" trifft auf die
     * grosse Mehrheit der Zellen zu. Sichtbar ist er deshalb nicht - im
     * Dokument bleibt er stehen, damit Screenreader und der Titel beim
     * Darueberfahren vollstaendig bleiben.
     */
    public function test_offene_zelle_zeigt_kein_zeichen_bleibt_aber_lesbar(): void {
        $this->resetAfterTest();

        $html = kompetenzraster::render([$this->bereich([
            [raster_kompetenz::STATUS_OFFEN, false],
        ])]);

        $statustext = get_string('raster:status_offen', 'local_berufsbildung');

        // Im Dokument vorhanden - aber in der Huelle, die styles.css
        // visuell ausblendet.
        $this->assertStringContainsString($statustext, $html);
        $this->assertStringContainsString('local-berufsbildung-nurtext', $html);
        // Und ohne Symbol in der Zelle.
        $this->assertStringNotContainsString('fa-minus', $html);
    }

    /**
     * Ein Stand, der im gezeigten Raster gar nicht vorkommt, taucht auch
     * nicht in der Legende auf - sonst laesst sie den Blick nach etwas
     * suchen, das es nicht gibt.
     */
    public function test_legende_erklaert_nur_vorkommende_staende(): void {
        $this->resetAfterTest();

        $html = kompetenzraster::render([$this->bereich([
            [raster_kompetenz::STATUS_ABGEDECKT, false],
            [raster_kompetenz::STATUS_OFFEN, false],
        ])]);

        $this->assertStringContainsString(
            get_string('raster:legende_abgedeckt', 'local_berufsbildung'),
            $html
        );
        $this->assertStringNotContainsString(
            get_string('raster:legende_eingeplant', 'local_berufsbildung'),
            $html
        );
        // Ohne Wahlpflicht-HK im Raster erklaert die Legende auch deren
        // Farbe nicht.
        $this->assertStringNotContainsString(
            get_string('raster:legende_wahlpflicht', 'local_berufsbildung'),
            $html
        );
    }

    /**
     * Die Zusammenfassung ersetzt die Lueckenliste auf meine_lehre.php und
     * muss deshalb dieselbe Bezugsgroesse verwenden: Wahlpflicht-HK zaehlen
     * nicht mit, auch wenn sie im Raster sichtbar sind.
     */
    public function test_zusammenfassung_zaehlt_nur_pflichtkompetenzen(): void {
        $this->resetAfterTest();

        $html = kompetenzraster::render([$this->bereich([
            [raster_kompetenz::STATUS_ABGEDECKT, false],
            [raster_kompetenz::STATUS_OFFEN, false],
            [raster_kompetenz::STATUS_ABGEDECKT, true],
        ])]);

        $this->assertStringContainsString(
            get_string('raster:zusammenfassung', 'local_berufsbildung', (object) [
                'soll' => 2,
                'abgedeckt' => 1,
                'eingeplant' => 0,
                'offen' => 1,
            ]),
            $html
        );
    }

    /**
     * In der Roster-Kachel traegt bereits ein Badge die Zahl - die
     * Zusammenfassung waere dort dieselbe Angabe ein zweites Mal.
     */
    public function test_kompaktes_raster_bleibt_ohne_zusammenfassung(): void {
        $this->resetAfterTest();

        $html = kompetenzraster::render(
            [$this->bereich([[raster_kompetenz::STATUS_ABGEDECKT, false]])],
            null,
            kompakt: true
        );

        $this->assertStringNotContainsString(
            get_string('raster:zusammenfassung', 'local_berufsbildung', (object) [
                'soll' => 1,
                'abgedeckt' => 1,
                'eingeplant' => 0,
                'offen' => 0,
            ]),
            $html
        );
    }

    /**
     * Die Zusammenfassung trennt "bereits vorgekommen" von "spaeter
     * eingeplant" - "im Plan" allein liesse offen, was gemeint ist.
     */
    public function test_zusammenfassung_trennt_vorgekommen_und_eingeplant(): void {
        $this->resetAfterTest();

        $html = kompetenzraster::render([$this->bereich([
            [raster_kompetenz::STATUS_ABGEDECKT, false],
            [raster_kompetenz::STATUS_EINGEPLANT, false],
            [raster_kompetenz::STATUS_EINGEPLANT, false],
            [raster_kompetenz::STATUS_OFFEN, false],
            [raster_kompetenz::STATUS_EINGEPLANT, true],
        ])], time());

        $this->assertStringContainsString(
            get_string('raster:zusammenfassung', 'local_berufsbildung', (object) [
                'soll' => 4,
                'abgedeckt' => 1,
                'eingeplant' => 2,
                'offen' => 1,
            ]),
            $html
        );
        $this->assertStringNotContainsString(
            get_string('raster:hinweis_nichts_im_plan', 'local_berufsbildung'),
            $html
        );
    }

    /**
     * Kommt keine Handlungskompetenz im Plan vor, sagt das Raster warum,
     * statt "0 von 14" zu zeigen. Den Link zu den Ausbildungsbloecken gibt
     * es nur fuer Personen, die sie pflegen duerfen.
     */
    public function test_ohne_kompetenz_im_plan_steht_ein_hinweis(): void {
        $this->resetAfterTest();
        $raster = [$this->bereich([
            [raster_kompetenz::STATUS_OFFEN, false],
            [raster_kompetenz::STATUS_OFFEN, true],
        ])];

        $this->setUser($this->getDataGenerator()->create_user());
        $html = kompetenzraster::render($raster, time());

        $this->assertStringContainsString(
            get_string('raster:hinweis_nichts_im_plan', 'local_berufsbildung'),
            $html
        );
        $this->assertStringNotContainsString('bloecke.php', $html);
        $this->assertStringNotContainsString(
            get_string('raster:zusammenfassung', 'local_berufsbildung', (object) [
                'soll' => 1,
                'abgedeckt' => 0,
                'eingeplant' => 0,
                'offen' => 1,
            ]),
            $html
        );

        $this->setAdminUser();
        $this->assertStringContainsString('bloecke.php', kompetenzraster::render($raster, time()));
    }

    /**
     * Randfall: ohne Versetzungsplan fehlt nicht die Kompetenzzuordnung,
     * sondern der Plan selbst - der Hinweis sagt das, und ein Link zu den
     * Ausbildungsbloecken wuerde nicht weiterhelfen.
     */
    public function test_ohne_plan_steht_der_hinweis_auf_den_fehlenden_plan(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $html = kompetenzraster::render([$this->bereich([
            [raster_kompetenz::STATUS_OFFEN, false],
        ])], null);

        $this->assertStringContainsString(
            get_string('raster:hinweis_kein_plan', 'local_berufsbildung'),
            $html
        );
        $this->assertStringNotContainsString('bloecke.php', $html);
    }

    /**
     * Ohne Raster gibt es nichts darzustellen - auch keine Ueberschrift mit
     * leerer Tabelle darunter.
     */
    public function test_ohne_raster_bleibt_leer(): void {
        $this->resetAfterTest();

        $this->assertSame('', kompetenzraster::render([]));
    }
}

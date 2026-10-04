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

namespace local_berufsbildung\pdf;

/**
 * Tests for what the printed document is allowed to look like.
 *
 * Everything here is read from outside the plugin — a colour somebody typed, a file
 * somebody uploaded, a font the site happens to have. What is pinned is therefore not
 * the look but the failure behaviour: none of these answers may ever be the reason a
 * competence record cannot be issued.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_berufsbildung\pdf\gestaltung
 */
final class gestaltung_test extends \advanced_testcase {
    /** @var string A one pixel PNG, small enough to be carried here rather than generated. */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVR4nGNgYGAAAAAEAAH2FzhVAA'
        . 'AAAElFTkSuQmCC';

    /**
     * A site that set no accent gets the one the plugin ships with.
     */
    public function test_akzent_ohne_einstellung(): void {
        $this->resetAfterTest();

        $this->assertSame([31, 74, 109], gestaltung::akzent());
    }

    /**
     * A colour is read whether or not it was typed with a hash, and in either case.
     *
     * @dataProvider akzent_provider
     * @param string $eingabe what stands in the setting
     * @param int[] $erwartet the colour it means
     */
    public function test_akzent_liest_hexwerte(string $eingabe, array $erwartet): void {
        $this->resetAfterTest();
        set_config('akzentfarbe', $eingabe, 'local_berufsbildung');

        $this->assertSame($erwartet, gestaltung::akzent());
    }

    /**
     * Values a colour picker can produce, and values a person can.
     *
     * @return array<string, array{string, int[]}>
     */
    public static function akzent_provider(): array {
        return [
            'mit Raute' => ['#123456', [18, 52, 86]],
            'ohne Raute' => ['123456', [18, 52, 86]],
            'Kurzform' => ['#abc', [170, 187, 204]],
            'Grossbuchstaben' => ['#FF8000', [255, 128, 0]],
            'mit Leerzeichen' => ['  #123456  ', [18, 52, 86]],
        ];
    }

    /**
     * Anything that is not a colour leaves the document in the colour it had.
     *
     * @dataProvider akzent_unbrauchbar_provider
     * @param string $eingabe what stands in the setting
     */
    public function test_akzent_faellt_zurueck(string $eingabe): void {
        $this->resetAfterTest();
        set_config('akzentfarbe', $eingabe, 'local_berufsbildung');

        $this->assertSame([31, 74, 109], gestaltung::akzent());
    }

    /**
     * Values that are not a colour, however they got into the setting.
     *
     * @return array<string, array{string}>
     */
    public static function akzent_unbrauchbar_provider(): array {
        return [
            'leer' => [''],
            'Wort' => ['dunkelblau'],
            'zu kurz' => ['#12'],
            'zu lang' => ['#1234567'],
            'keine Hexziffern' => ['#gggggg'],
        ];
    }

    /**
     * The tints run from white to the accent and stay inside it at every step.
     */
    public function test_ton_mischt_gegen_weiss(): void {
        $this->resetAfterTest();
        set_config('akzentfarbe', '#000000', 'local_berufsbildung');

        $this->assertSame([255, 255, 255], gestaltung::ton(0.0));
        $this->assertSame([0, 0, 0], gestaltung::ton(1.0));
        $this->assertSame([128, 128, 128], gestaltung::ton(0.5));

        // A share outside the range is a mistake in the caller, not a reason to emit
        // a colour that no longer exists.
        $this->assertSame([255, 255, 255], gestaltung::ton(-2.0));
        $this->assertSame([0, 0, 0], gestaltung::ton(7.0));
    }

    /**
     * Whatever font is chosen, it is installed in every style the document sets.
     */
    public function test_schrift_ist_vollstaendig_vorhanden(): void {
        global $CFG;
        require_once($CFG->libdir . '/pdflib.php');

        $familie = gestaltung::schrift();
        $this->assertNotEmpty($familie);

        foreach (['', 'b', 'i', 'bi'] as $stil) {
            $this->assertNotEmpty(
                \TCPDF_FONTS::getFontFullPath($familie . $stil . '.php'),
                'Die gewählte Schrift ' . $familie . ' hat keinen Schnitt ' . $stil . '.'
            );
        }
    }

    /**
     * Without an upload the heading is simply drawn without an emblem.
     */
    public function test_logo_ohne_datei(): void {
        $this->resetAfterTest();

        $this->assertNull(gestaltung::logo());
    }

    /**
     * An uploaded picture comes back in the form TCPDF reads image data in.
     */
    public function test_logo_liefert_bilddaten(): void {
        $this->resetAfterTest();
        $this->logo_ablegen(base64_decode(self::PNG), 'logo.png');

        $logo = gestaltung::logo();
        $this->assertNotNull($logo);
        $this->assertSame('@', $logo[0]);
        $this->assertSame(base64_decode(self::PNG), substr($logo, 1));
    }

    /**
     * A logo with an alpha channel arrives without one, and in its own colours.
     *
     * The transparency is what used to turn the emblem grey in the finished document:
     * TCPDF hands a PNG carrying an alpha channel to Imagick or GD to be split into a
     * picture and a mask, and that detour drops the colour wherever ImageMagick 7 is
     * installed. Almost every logo is exported transparent, so this is the normal case
     * rather than an exotic one.
     */
    public function test_logo_verliert_den_alphakanal(): void {
        $this->resetAfterTest();

        if (!function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('Ohne GD lässt sich kein Testbild erzeugen.');
        }

        $this->logo_ablegen(self::farbiges_png_mit_alpha(), 'logo.png');

        $logo = gestaltung::logo();
        $this->assertNotNull($logo);
        $daten = substr($logo, 1);

        // Byte 25 of a PNG is the colour type in its header: 2 is plain RGB, 6 carries
        // an alpha channel.
        $this->assertSame(2, ord($daten[25]), 'Das Logo trägt weiterhin einen Alphakanal.');

        $bild = imagecreatefromstring($daten);
        $this->assertNotFalse($bild);
        $this->assertSame(0x1F4A6D, imagecolorat($bild, 1, 1) & 0xFFFFFF, 'Das Logo hat seine Farbe verloren.');
        imagedestroy($bild);
    }

    /**
     * A JPEG is passed through untouched, having no transparency to resolve.
     */
    public function test_logo_jpeg_wird_nicht_neu_kodiert(): void {
        $this->resetAfterTest();

        if (!function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('Ohne GD lässt sich kein Testbild erzeugen.');
        }

        $jpeg = self::farbiges_jpeg();
        $this->logo_ablegen($jpeg, 'logo.jpg');

        $this->assertSame('@' . $jpeg, gestaltung::logo());
    }

    /**
     * A small picture in one opaque colour, saved with an alpha channel.
     *
     * @return string the PNG
     */
    private static function farbiges_png_mit_alpha(): string {
        $bild = imagecreatetruecolor(8, 8);
        imagesavealpha($bild, true);
        imagefill($bild, 0, 0, imagecolorallocatealpha($bild, 31, 74, 109, 0));

        ob_start();
        imagepng($bild);
        $daten = (string)ob_get_clean();
        imagedestroy($bild);

        return $daten;
    }

    /**
     * The same picture as a JPEG.
     *
     * @return string the JPEG
     */
    private static function farbiges_jpeg(): string {
        $bild = imagecreatetruecolor(8, 8);
        imagefill($bild, 0, 0, imagecolorallocate($bild, 31, 74, 109));

        ob_start();
        imagejpeg($bild);
        $daten = (string)ob_get_clean();
        imagedestroy($bild);

        return $daten;
    }

    /**
     * A file that is not a picture is reported as no picture at all.
     *
     * The release must not fail over an upload. The document is issued without the
     * emblem instead, which is a blemish rather than an incident.
     */
    public function test_logo_bei_beschaedigter_datei(): void {
        $this->resetAfterTest();
        $this->logo_ablegen('Dies ist kein Bild.', 'logo.png');

        $this->assertNull(gestaltung::logo());
    }

    /**
     * Put a file where the setting stores what an administrator uploaded.
     *
     * @param string $inhalt the file contents
     * @param string $dateiname the file name
     * @return void
     */
    private function logo_ablegen(string $inhalt, string $dateiname): void {
        get_file_storage()->create_file_from_string([
            'contextid' => \context_system::instance()->id,
            'component' => 'local_berufsbildung',
            'filearea' => gestaltung::LOGO_BEREICH,
            'itemid' => 0,
            'filepath' => '/',
            'filename' => $dateiname,
        ], $inhalt);
    }

    /**
     * Die Einstellungen von local_uekkn haben Vorrang vor denen von
     * local_bildungsbericht, und das Logo kommt samt Einstellungswert mit.
     */
    public function test_uebernahme_bevorzugt_uekkn(): void {
        $this->resetAfterTest();
        set_config('akzentfarbe', '#123456', 'local_uekkn');
        set_config('akzentfarbe', '#654321', 'local_bildungsbericht');
        $this->altes_logo_ablegen('local_bildungsbericht', 'bb.png');
        $this->altes_logo_ablegen('local_uekkn', 'uek.png');

        gestaltung::uebernehme_bisherige_einstellungen();

        $this->assertSame('#123456', get_config('local_berufsbildung', gestaltung::AKZENT_EINSTELLUNG));
        $this->assertSame('/uek.png', get_config('local_berufsbildung', gestaltung::LOGO_BEREICH));
        $this->assertSame('@' . base64_decode(self::PNG), gestaltung::logo());
        // Kopiert, nicht verschoben: jedes Plugin raeumt seine Werte selbst weg.
        $this->assertSame('#123456', get_config('local_uekkn', 'akzentfarbe'));
    }

    /**
     * Ohne Werte in local_uekkn greift local_bildungsbericht.
     */
    public function test_uebernahme_rueckfall_bildungsbericht(): void {
        $this->resetAfterTest();
        set_config('akzentfarbe', 'keine farbe', 'local_uekkn');
        set_config('akzentfarbe', '#654321', 'local_bildungsbericht');
        $this->altes_logo_ablegen('local_bildungsbericht', 'bb.png');

        gestaltung::uebernehme_bisherige_einstellungen();

        $this->assertSame('#654321', get_config('local_berufsbildung', gestaltung::AKZENT_EINSTELLUNG));
        $this->assertSame('/bb.png', get_config('local_berufsbildung', gestaltung::LOGO_BEREICH));
    }

    /**
     * Was hier bereits eingestellt ist, bleibt - auch bei einem zweiten
     * Aufruf aus dem Upgrade eines anderen Plugins.
     */
    public function test_uebernahme_ueberschreibt_nichts(): void {
        $this->resetAfterTest();
        set_config(gestaltung::AKZENT_EINSTELLUNG, '#000000', 'local_berufsbildung');
        $this->logo_ablegen(base64_decode(self::PNG), 'eigenes.png');
        set_config('akzentfarbe', '#123456', 'local_uekkn');
        $this->altes_logo_ablegen('local_uekkn', 'uek.png');

        gestaltung::uebernehme_bisherige_einstellungen();
        gestaltung::uebernehme_bisherige_einstellungen();

        $this->assertSame('#000000', get_config('local_berufsbildung', gestaltung::AKZENT_EINSTELLUNG));
        $dateien = get_file_storage()->get_area_files(
            \context_system::instance()->id,
            'local_berufsbildung',
            gestaltung::LOGO_BEREICH,
            0,
            'filename',
            false
        );
        $this->assertSame(['eigenes.png'], array_values(array_map(static fn($d) => $d->get_filename(), $dateien)));
    }

    /**
     * Legt ein Logo dort ab, wo ein Plugin es bisher selbst gefuehrt hat.
     *
     * @param string $component
     * @param string $dateiname
     */
    private function altes_logo_ablegen(string $component, string $dateiname): void {
        get_file_storage()->create_file_from_string([
            'contextid' => \context_system::instance()->id,
            'component' => $component,
            'filearea' => 'logo',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => $dateiname,
        ], base64_decode(self::PNG));
    }
}

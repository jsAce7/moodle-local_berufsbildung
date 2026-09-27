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
 * Aussehen aller PDF-Dokumente der Berufsbildung: Farbe, Schrift und Logo.
 *
 * Ein einheitliches Erscheinungsbild fuer die üK-Kompetenznachweise
 * (local_uekkn) und die Bildungsberichte (local_bildungsbericht) - frueher
 * je eine Kopie dieser Klasse mit eigener Farbe und eigenem Logo.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\pdf;

/**
 * Getrennt von den PDF-Renderern, die entscheiden, wo etwas hinkommt.
 * Farbe, Logo und Schrift kommen von aussen und muessen ohne Konfiguration
 * genauso funktionieren wie mit.
 */
class gestaltung {
    /** Einstellung und Filearea des Logos, Systemkontext von local_berufsbildung. */
    public const LOGO_BEREICH = 'logo';

    /** Einstellung der Akzentfarbe. */
    public const AKZENT_EINSTELLUNG = 'akzentfarbe';

    /** Standardfarbe, falls keine gesetzt ist: ein dunkles Blau, das weisse Schrift traegt. */
    public const AKZENT_STANDARD = '#1F4A6D';

    /** Waagrechter Freiraum in einer Tabellenzelle, in mm. */
    public const LUFT_X = 1.8;

    /** Senkrechter Freiraum in einer Tabellenzelle, in mm. */
    public const LUFT_Y = 1.3;

    /** Grau fuer Beschriftungen. */
    public const GRAU = [110, 110, 110];

    /** Beinahe Schwarz fuer Fliesstext. */
    public const TEXT = [26, 26, 26];

    /** Bevorzugte Schriften, beste zuerst. */
    private const SCHRIFTEN = ['freesans', 'dejavusans'];

    /** Rueckfallschrift, immer vorhanden. */
    private const SCHRIFT_RUECKFALL = 'helvetica';

    /** Benoetigte Schriftstile. */
    private const STILE = ['', 'b', 'i', 'bi'];

    /** @var string|null Aufgeloeste Schriftfamilie, fuer die Anfrage zwischengespeichert. */
    private static ?string $schrift = null;

    /**
     * Die Schriftfamilie des Dokuments.
     *
     * @return string
     */
    public static function schrift(): string {
        global $CFG;

        if (self::$schrift !== null) {
            return self::$schrift;
        }

        $kandidaten = [];
        if (!empty($CFG->pdfexportfont)) {
            $kandidaten[] = (string) $CFG->pdfexportfont;
        }
        if (get_string_manager()->string_exists('thispdffont', 'langconfig')) {
            $ausdersprache = trim(get_string('thispdffont', 'langconfig'));
            if ($ausdersprache !== '') {
                $kandidaten[] = $ausdersprache;
            }
        }
        $kandidaten = array_merge($kandidaten, self::SCHRIFTEN);

        foreach ($kandidaten as $kandidat) {
            if (self::schrift_vollstaendig($kandidat)) {
                return self::$schrift = $kandidat;
            }
        }

        return self::$schrift = self::SCHRIFT_RUECKFALL;
    }

    /**
     * Ob eine Schriftfamilie in allen benoetigten Stilen installiert ist.
     *
     * @param string $familie
     * @return bool
     */
    private static function schrift_vollstaendig(string $familie): bool {
        $familie = strtolower(preg_replace('/[^a-z0-9]/i', '', $familie));
        if ($familie === '') {
            return false;
        }

        foreach (self::STILE as $stil) {
            if (!self::schriftdatei($familie . $stil . '.php')) {
                return false;
            }
        }

        return true;
    }

    /**
     * Ob TCPDF eine Schriftdefinitionsdatei findet.
     *
     * @param string $datei
     * @return bool
     */
    private static function schriftdatei(string $datei): bool {
        global $CFG;

        if (class_exists('\TCPDF_FONTS')) {
            return (bool) \TCPDF_FONTS::getFontFullPath($datei);
        }

        return file_exists($CFG->libdir . '/tcpdf/fonts/' . $datei);
    }

    /**
     * Die Akzentfarbe des Dokuments.
     *
     * @return int[]
     */
    public static function akzent(): array {
        $farbe = self::rgb((string) get_config('local_berufsbildung', self::AKZENT_EINSTELLUNG));

        return $farbe ?? self::rgb(self::AKZENT_STANDARD);
    }

    /**
     * Die Akzentfarbe in Richtung Weiss abgetoent - fuer Kopfbaender,
     * Trennlinien und das getoente Infofeld.
     *
     * @param float $anteil 0 fuer Weiss, 1 fuer die volle Akzentfarbe
     * @return int[]
     */
    public static function ton(float $anteil): array {
        $anteil = max(0.0, min(1.0, $anteil));

        return array_map(
            static fn(int $wert): int => (int) round(255 - ((255 - $wert) * $anteil)),
            self::akzent()
        );
    }

    /**
     * Eine Farbe aus einem Hex-Wert, oder null wenn keine gueltige.
     *
     * @param string $wert
     * @return int[]|null
     */
    private static function rgb(string $wert): ?array {
        $wert = ltrim(trim($wert), '#');

        if (preg_match('/^[0-9a-f]{3}$/i', $wert)) {
            $wert = $wert[0] . $wert[0] . $wert[1] . $wert[1] . $wert[2] . $wert[2];
        }
        if (!preg_match('/^[0-9a-f]{6}$/i', $wert)) {
            return null;
        }

        return [
            (int) hexdec(substr($wert, 0, 2)),
            (int) hexdec(substr($wert, 2, 2)),
            (int) hexdec(substr($wert, 4, 2)),
        ];
    }

    /**
     * Uebernimmt Logo und Akzentfarbe aus den Plugins, die sie bisher selbst
     * gefuehrt haben - zuerst aus local_uekkn, sonst aus
     * local_bildungsbericht. Kopiert nur, solange hier noch nichts gesetzt
     * ist, und loescht nichts: jedes der beiden Plugins raeumt seine
     * eigenen Werte in seinem eigenen Upgrade weg und ruft diese Methode
     * vorher selbst auf. Moodle aktualisiert Plugins nicht in der
     * Reihenfolge ihrer Abhaengigkeiten, so geht in keiner Reihenfolge
     * etwas verloren.
     */
    public static function uebernehme_bisherige_einstellungen(): void {
        $quellen = ['local_uekkn', 'local_bildungsbericht'];

        // Der Standardwert gilt als nicht eingestellt: Moodle schreibt ihn
        // nach jedem Upgrade-Lauf in die Konfiguration, und ein Plugin, das
        // erst in einem spaeteren Lauf aktualisiert wird, faende sonst eine
        // vermeintlich gewaehlte Farbe vor und verloere seine eigene.
        $farbe = trim((string) get_config('local_berufsbildung', self::AKZENT_EINSTELLUNG));
        if ($farbe === '' || strcasecmp($farbe, self::AKZENT_STANDARD) === 0) {
            foreach ($quellen as $quelle) {
                $alt = trim((string) get_config($quelle, 'akzentfarbe'));
                if (self::rgb($alt) !== null) {
                    set_config(self::AKZENT_EINSTELLUNG, $alt, 'local_berufsbildung');
                    break;
                }
            }
        }

        $fs = get_file_storage();
        $kontextid = \context_system::instance()->id;
        if (!$fs->is_area_empty($kontextid, 'local_berufsbildung', self::LOGO_BEREICH, 0)) {
            return;
        }
        foreach ($quellen as $quelle) {
            $dateien = $fs->get_area_files($kontextid, $quelle, self::LOGO_BEREICH, 0, 'itemid, filepath, filename', false);
            if (!$dateien) {
                continue;
            }
            $datei = reset($dateien);
            $fs->create_file_from_storedfile(['component' => 'local_berufsbildung'], $datei);
            // Der Wert der Einstellung ist der Pfad der Datei, wie ihn
            // admin_setting_configstoredfile::write_setting() schreibt.
            set_config(self::LOGO_BEREICH, $datei->get_filepath() . $datei->get_filename(), 'local_berufsbildung');
            return;
        }
    }

    /**
     * Das Logo, bereit fuer TCPDF - oder null, wenn keines hochgeladen
     * oder die Datei beschaedigt ist.
     *
     * @return string|null
     */
    public static function logo(): ?string {
        global $CFG;
        // Hier statt beim Aufrufer: config.php laedt die Dateibibliothek
        // nicht, und ein Dokument kann von einer Anfrage gezeichnet werden,
        // die keine Seite ausgibt - das Muster einer Vorlage etwa.
        require_once($CFG->libdir . '/filelib.php');

        $dateien = get_file_storage()->get_area_files(
            \context_system::instance()->id,
            'local_berufsbildung',
            self::LOGO_BEREICH,
            0,
            'itemid',
            false
        );
        if (!$dateien) {
            return null;
        }

        $inhalt = reset($dateien)->get_content();
        $masse = @getimagesizefromstring($inhalt);
        if (!$masse || !in_array($masse[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG], true)) {
            return null;
        }

        return '@' . self::ohne_alphakanal($inhalt, (int) $masse[2]);
    }

    /**
     * Dasselbe Bild ohne Transparenz, auf Weiss gelegt - TCPDF kann eine
     * PNG mit Alphakanal nicht immer korrekt einbetten (ImageMagick 7
     * und der GD-Rueckfall trennen die Kanaele in Graustufen auf).
     *
     * @param string $inhalt
     * @param int $typ Eine der IMAGETYPE_*-Konstanten
     * @return string
     */
    private static function ohne_alphakanal(string $inhalt, int $typ): string {
        if ($typ !== IMAGETYPE_PNG || strlen($inhalt) < 26 || !in_array(ord($inhalt[25]), [4, 6], true)) {
            return $inhalt;
        }
        if (!function_exists('imagecreatefromstring')) {
            return $inhalt;
        }

        $bild = @imagecreatefromstring($inhalt);
        if ($bild === false) {
            return $inhalt;
        }

        $flach = @imagecreatetruecolor(imagesx($bild), imagesy($bild));
        if ($flach === false) {
            imagedestroy($bild);
            return $inhalt;
        }

        imagefilledrectangle($flach, 0, 0, imagesx($bild) - 1, imagesy($bild) - 1, imagecolorallocate($flach, 255, 255, 255));
        imagecopy($flach, $bild, 0, 0, 0, 0, imagesx($bild), imagesy($bild));

        ob_start();
        $geschrieben = imagepng($flach, null, 9);
        $flachbild = (string) ob_get_clean();

        imagedestroy($bild);
        imagedestroy($flach);

        return $geschrieben && $flachbild !== '' ? $flachbild : $inhalt;
    }
}

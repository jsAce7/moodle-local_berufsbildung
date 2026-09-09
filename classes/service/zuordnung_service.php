<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Schreiboperationen fuer Zuordnungen.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\service;

use local_berufsbildung\persistent\zuordnung;

/** Stellt widerspruchsfreie Zeitraeume je lernender Person und Rolle sicher. */
class zuordnung_service {

    /** Legt eine Zuordnung an und beendet dabei nur einen vorherigen Zeitraum derselben Rolle. */
    public function anlegen(
        int $berufsbildnerid,
        int $lernendeid,
        string $beruf,
        int $gueltigvon,
        string $rolle,
        ?int $kohortenlinkid
    ): zuordnung {
        global $DB;

        if ($berufsbildnerid === $lernendeid) {
            throw new \moodle_exception('zuordnung:fehler_gleiche_person', 'local_berufsbildung');
        }

        $ueberschneidend = zuordnung::get_records_select(
            'lernendeid = :lernendeid AND rolle = :rolle
             AND (gueltig_bis IS NULL OR gueltig_bis >= :gueltigvon)',
            ['lernendeid' => $lernendeid, 'rolle' => $rolle, 'gueltigvon' => $gueltigvon]
        );
        foreach ($ueberschneidend as $einzelne) {
            if ((int) $einzelne->get('gueltig_von') > $gueltigvon) {
                throw new \moodle_exception('zuordnung:fehler_ueberschneidung', 'local_berufsbildung');
            }
        }

        $transaktion = $DB->start_delegated_transaction();
        foreach ($ueberschneidend as $einzelne) {
            $einzelne->set('gueltig_bis', $gueltigvon - 1);
            $einzelne->update();
        }
        $neue = new zuordnung(0, (object) [
            'berufsbildnerid' => $berufsbildnerid,
            'lernendeid' => $lernendeid,
            'beruf' => $beruf,
            'rolle' => $rolle,
            'gueltig_von' => $gueltigvon,
            'gueltig_bis' => null,
            'kohorten_link_id' => $kohortenlinkid,
        ]);
        $neue->create();
        $transaktion->allow_commit();

        (new role_sync_service())->synchronisiere_paar($berufsbildnerid, $lernendeid);
        return $neue;
    }

    /** Setzt ein Enddatum oder oeffnet einen zuvor beendeten Zeitraum wieder. */
    public function beenden(int $zuordnungid, ?int $gueltigbis): void {
        $zuordnung = new zuordnung($zuordnungid);
        if ($gueltigbis !== null && $gueltigbis < (int) $zuordnung->get('gueltig_von')) {
            throw new \moodle_exception('zuordnung:fehler_enddatum', 'local_berufsbildung');
        }
        if ($gueltigbis === null && zuordnung::record_exists_select(
            'id <> :id AND lernendeid = :lernendeid AND rolle = :rolle
             AND (gueltig_bis IS NULL OR gueltig_bis >= :gueltigvon)',
            [
                'id' => $zuordnungid,
                'lernendeid' => $zuordnung->get('lernendeid'),
                'rolle' => $zuordnung->get('rolle'),
                'gueltigvon' => $zuordnung->get('gueltig_von'),
            ]
        )) {
            throw new \moodle_exception('zuordnung:fehler_ueberschneidung', 'local_berufsbildung');
        }
        $zuordnung->set('gueltig_bis', $gueltigbis);
        $zuordnung->update();

        // Auch beim Beenden abgleichen, nicht nur beim Anlegen und Loeschen:
        // sonst behaelt eine Person die systemweite Planungsrolle bis zum
        // naechsten stuendlichen Task-Lauf. Das Wiederoeffnen (gueltig_bis =
        // null) setzt sie ueber denselben Aufruf zurueck.
        (new role_sync_service())->synchronisiere_paar(
            (int) $zuordnung->get('berufsbildnerid'),
            (int) $zuordnung->get('lernendeid')
        );
    }

    /** Loescht eine nachweislich falsch erfasste Zuordnung endgueltig. */
    public function loeschen(int $zuordnungid): void {
        $zuordnung = new zuordnung($zuordnungid);
        $berufsbildnerid = (int) $zuordnung->get('berufsbildnerid');
        $lernendeid = (int) $zuordnung->get('lernendeid');
        $zuordnung->delete();
        (new role_sync_service())->synchronisiere_paar($berufsbildnerid, $lernendeid);
    }
}

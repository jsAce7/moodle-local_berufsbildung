<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Rendert Fälligkeiten im fachlichen Kontext der jeweiligen Übersicht.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\output;

use html_writer;

/**
 * Eine nach Datum vorbereitete Liste von fälligen Aufgaben.
 */
final class faelligkeiten_liste {
    /**
     * @param object[] $zeilen name ist optional (bei eigenen Fälligkeiten).
     * @param string $titel Abschnittsüberschrift
     */
    public static function render(array $zeilen, string $titel): string {
        if (empty($zeilen)) {
            return '';
        }

        $inhalt = html_writer::tag('h2', $titel, ['class' => 'h4']);
        $inhalt .= html_writer::start_tag('div', ['class' => 'list-group']);
        foreach ($zeilen as $zeile) {
            $status = $zeile->ueberfaellig
                ? get_string('faelligkeiten:ueberfaellig', 'local_berufsbildung') . ' · ' . $zeile->datum
                : $zeile->datum;
            $beschreibung = !empty($zeile->name)
                ? html_writer::tag('strong', s($zeile->name)) . html_writer::div(s($zeile->bezeichnung))
                : html_writer::tag('strong', s($zeile->bezeichnung));
            $inhalt .= html_writer::link(
                new \moodle_url($zeile->url),
                $beschreibung . html_writer::div(
                    $status,
                    $zeile->ueberfaellig ? 'text-danger small' : 'text-muted small'
                ),
                ['class' => 'list-group-item list-group-item-action']
            );
        }
        $inhalt .= html_writer::end_tag('div');

        return $inhalt;
    }
}

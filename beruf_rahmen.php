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
 * Kompetenzrahmen je Beruf zuordnen - Verwaltungsseite fuer die Einstellung
 * beruf_rahmen_mapping.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use core_competency\competency_framework;
use local_berufsbildung\form\beruf_rahmen_form;
use local_berufsbildung\service\rahmen_resolver;

admin_externalpage_setup('local_berufsbildung_berufrahmen');

$PAGE->set_url(new moodle_url('/local/berufsbildung/beruf_rahmen.php'));
$titel = get_string('berufrahmen:uebersicht', 'local_berufsbildung');
$PAGE->set_title($titel);
$PAGE->set_heading($titel);
$PAGE->navbar->add($titel);

$returnurl = new moodle_url('/local/berufsbildung/beruf_rahmen.php');

$resolver = new rahmen_resolver();
$konfiguration = get_config('local_berufsbildung', 'beruf_rahmen_mapping');
$paare = $resolver->alle_paare($konfiguration !== false ? (string) $konfiguration : '');

$rahmenoptionen = [];
if (get_config('core_competency', 'enabled')) {
    foreach (competency_framework::get_records([], 'shortname', 'ASC') as $framework) {
        $rahmenoptionen[(string) $framework->get('idnumber')] =
            format_string($framework->get('shortname')) . ' (' . $framework->get('idnumber') . ')';
    }
}

$form = new beruf_rahmen_form(null, [
    'rahmenoptionen' => $rahmenoptionen,
    'bestehende_berufe' => array_keys($paare),
]);

if (!empty($rahmenoptionen) && $data = $form->get_data()) {
    $paare[$data->beruf] = $data->rahmenidnumber;
    set_config('beruf_rahmen_mapping', $resolver->serialisiere($paare), 'local_berufsbildung');

    redirect($returnurl, get_string('berufrahmen:hinzugefuegt', 'local_berufsbildung'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();

echo html_writer::tag('p', get_string('berufrahmen:einleitung', 'local_berufsbildung'));

if (empty($paare)) {
    echo $OUTPUT->notification(get_string('berufrahmen:keine', 'local_berufsbildung'), 'info');
} else {
    $table = new html_table();
    $table->head = [
        get_string('berufrahmen:beruf', 'local_berufsbildung'),
        get_string('berufrahmen:rahmen', 'local_berufsbildung'),
        '',
    ];

    foreach ($paare as $beruf => $idnumber) {
        $framework = competency_framework::get_record(['idnumber' => $idnumber]);
        $bezeichnung = $framework
            ? format_string($framework->get('shortname')) . ' (' . s($idnumber) . ')'
            : '#' . s($idnumber);

        $entfernenurl = new moodle_url('/local/berufsbildung/beruf_rahmen_entfernen.php', [
            'beruf' => $beruf,
            'sesskey' => sesskey(),
        ]);

        $table->data[] = [
            s($beruf),
            $bezeichnung,
            html_writer::link($entfernenurl, get_string('berufrahmen:entfernen', 'local_berufsbildung')),
        ];
    }

    echo html_writer::table($table);
}

if (empty($rahmenoptionen)) {
    echo $OUTPUT->notification(get_string('berufrahmen:keine_rahmen', 'local_berufsbildung'), 'info');
} else {
    $form->display();
}

echo $OUTPUT->footer();

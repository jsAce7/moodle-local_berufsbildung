<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Central overview of own and supervised trainees' due actions.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_berufsbildung\api;
use local_berufsbildung\nachweis\collector;

require_login();

$userid = (int) $USER->id;
$PAGE->set_context(context_user::instance($userid));
$PAGE->set_url(new moodle_url('/local/berufsbildung/faelligkeiten.php'));
$titel = get_string('faelligkeiten:titel', 'local_berufsbildung');
$PAGE->set_title($titel);
$PAGE->set_heading($titel);

$personids = api::get_ausbildungsstand($userid) !== null ? [$userid] : [];
$personids = array_values(array_unique(array_merge($personids, api::get_lernende_for($userid))));
$collector = new collector();
$zeilen = [];
foreach ($personids as $lernendeid) {
    foreach ($collector->get_faelligkeiten($userid, $lernendeid) as $faelligkeit) {
        $zeilen[] = (object) [
            'name' => $lernendeid === $userid ? get_string('faelligkeiten:ich', 'local_berufsbildung') : fullname(core_user::get_user($lernendeid)),
            'bezeichnung' => $lernendeid === $userid ? $faelligkeit->eigene_bezeichnung : $faelligkeit->bezeichnung,
            'datum' => userdate($faelligkeit->datum, get_string('strftimedate', 'langconfig')),
            'zeitpunkt' => $faelligkeit->datum,
            'ueberfaellig' => $faelligkeit->datum < time(),
            'url' => $faelligkeit->url,
        ];
    }
}
usort($zeilen, static fn (object $a, object $b): int => $a->zeitpunkt <=> $b->zeitpunkt);

echo $OUTPUT->header();
if (empty($zeilen)) {
    echo $OUTPUT->notification(get_string('faelligkeiten:leer', 'local_berufsbildung'), 'info');
} else {
    echo html_writer::start_tag('div', ['class' => 'list-group']);
    foreach ($zeilen as $zeile) {
        $status = $zeile->ueberfaellig ? get_string('faelligkeiten:ueberfaellig', 'local_berufsbildung') . ' · ' . $zeile->datum : $zeile->datum;
        echo html_writer::link(
            new moodle_url($zeile->url),
            html_writer::tag('strong', s($zeile->name)) . html_writer::div(s($zeile->bezeichnung))
                . html_writer::div($status, $zeile->ueberfaellig ? 'text-danger small' : 'text-muted small'),
            ['class' => 'list-group-item list-group-item-action']
        );
    }
    echo html_writer::end_tag('div');
}
echo $OUTPUT->footer();

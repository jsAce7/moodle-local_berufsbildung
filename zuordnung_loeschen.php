<?php
// This file is part of Moodle - http://moodle.org/

require_once(__DIR__ . '/../../config.php');

use local_berufsbildung\api;
use local_berufsbildung\persistent\zuordnung;

admin_externalpage_setup('local_berufsbildung_zuordnung');
require_capability('local/berufsbildung:managezuordnung', context_system::instance());

$id = required_param('id', PARAM_INT);
$zuordnung = new zuordnung($id);
$returnurl = new moodle_url('/local/berufsbildung/zuordnung.php');
$PAGE->set_url(new moodle_url('/local/berufsbildung/zuordnung_loeschen.php', ['id' => $id]));
$PAGE->set_title(get_string('zuordnung:loeschen', 'local_berufsbildung'));
$PAGE->set_heading(get_string('zuordnung:loeschen', 'local_berufsbildung'));

if (optional_param('bestaetigt', 0, PARAM_BOOL) && confirm_sesskey()) {
    api::loesche_zuordnung($id);
    redirect($returnurl, get_string('zuordnung:geloescht', 'local_berufsbildung'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$beschreibung = fullname(core_user::get_user((int) $zuordnung->get('berufsbildnerid')))
    . ' → ' . fullname(core_user::get_user((int) $zuordnung->get('lernendeid')));
echo $OUTPUT->header();
echo $OUTPUT->confirm(
    get_string('zuordnung:loeschen_bestaetigung', 'local_berufsbildung', $beschreibung),
    new moodle_url('/local/berufsbildung/zuordnung_loeschen.php', ['id' => $id, 'bestaetigt' => 1, 'sesskey' => sesskey()]),
    $returnurl
);
echo $OUTPUT->footer();

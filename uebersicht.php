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
 * Central overview of the vocational training administration.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

admin_externalpage_setup('local_berufsbildung_uebersicht');

$context = context_system::instance();
$title = get_string('admin:uebersicht', 'local_berufsbildung');
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/berufsbildung/uebersicht.php'));
$PAGE->set_title($title);
$PAGE->set_heading($title);

/**
 * Renders a group of administration links.
 *
 * @param string $title Group title.
 * @param array $links Links with a title and URL.
 * @return string HTML for the group, or an empty string when it has no links.
 */
function local_berufsbildung_admin_group(string $title, array $links): string {
    if (empty($links)) {
        return '';
    }

    $items = '';
    foreach ($links as $link) {
        $items .= html_writer::tag('li', html_writer::link($link['url'], $link['title']));
    }

    return html_writer::tag('section',
        html_writer::tag('h3', $title) . html_writer::tag('ul', $items),
        ['class' => 'mb-4']
    );
}

$organisation = [];
if (has_capability('local/berufsbildung:viewzuordnung', $context)) {
    $organisation[] = [
        'title' => get_string('zuordnung:uebersicht', 'local_berufsbildung'),
        'url' => new moodle_url('/local/berufsbildung/zuordnung.php'),
    ];
}
if (has_capability('local/berufsbildung:managezuordnung', $context)) {
    $organisation[] = [
        'title' => get_string('kohortenlink:uebersicht', 'local_berufsbildung'),
        'url' => new moodle_url('/local/berufsbildung/kohorten_links.php'),
    ];
}
if (has_capability('local/berufsbildung:manageblocks', $context)) {
    $organisation[] = [
        'title' => get_string('bloecke:uebersicht', 'local_berufsbildung'),
        'url' => new moodle_url('/local/berufsbildung/bloecke.php'),
    ];
}
if (has_capability('local/berufsbildung:importplan', $context)) {
    $organisation[] = [
        'title' => get_string('planimport:titel', 'local_berufsbildung'),
        'url' => new moodle_url('/local/berufsbildung/import_plan.php'),
    ];
}

$lernbegleitung = [];
if (core_component::get_plugin_directory('local', 'uekkn') !== null) {
    if (has_capability('local/uekkn:managevorlagen', $context)) {
        $lernbegleitung[] = [
            'title' => get_string('vorlagen', 'local_uekkn'),
            'url' => new moodle_url('/local/uekkn/manage.php'),
        ];
        $lernbegleitung[] = [
            'title' => get_string('textbausteine', 'local_uekkn'),
            'url' => new moodle_url('/local/uekkn/manage.php', ['bereich' => 'textbausteine']),
        ];
    }
    if (has_capability('local/uekkn:managedurchfuehrungen', $context)) {
        $lernbegleitung[] = [
            'title' => get_string('durchfuehrungen', 'local_uekkn'),
            'url' => new moodle_url('/local/uekkn/manage.php', ['bereich' => 'durchfuehrungen']),
        ];
    }
    if (has_capability('local/uekkn:export', $context)) {
        $lernbegleitung[] = [
            'title' => get_string('export', 'local_uekkn'),
            'url' => new moodle_url('/local/uekkn/export.php'),
        ];
    }
}

$system = [[
    'title' => get_string('settings:einstellungen', 'local_berufsbildung'),
    'url' => new moodle_url('/admin/settings.php', ['section' => 'local_berufsbildung_settings']),
]];
if (core_component::get_plugin_directory('local', 'lerndokumentation') !== null) {
    $system[] = [
        'title' => get_string('pluginname', 'local_lerndokumentation'),
        'url' => new moodle_url('/admin/settings.php', ['section' => 'local_lerndokumentation']),
    ];
}
if (core_component::get_plugin_directory('local', 'uekkn') !== null) {
    $system[] = [
        'title' => get_string('einstellungen', 'local_uekkn'),
        'url' => new moodle_url('/admin/settings.php', ['section' => 'local_uekkn_settings']),
    ];
}
if (has_capability('local/berufsbildung:manageaufbewahrung', $context)) {
    $system[] = [
        'title' => get_string('aufbewahrung:uebersicht', 'local_berufsbildung'),
        'url' => new moodle_url('/local/berufsbildung/aufbewahrung.php'),
    ];
}

echo $OUTPUT->header();
echo $OUTPUT->heading($title);
echo html_writer::tag('p', get_string('admin:uebersicht_beschreibung', 'local_berufsbildung'));
echo local_berufsbildung_admin_group(get_string('admin:organisation', 'local_berufsbildung'), $organisation);
echo local_berufsbildung_admin_group(get_string('admin:lernbegleitung', 'local_berufsbildung'), $lernbegleitung);
echo local_berufsbildung_admin_group(get_string('admin:system', 'local_berufsbildung'), $system);
echo $OUTPUT->footer();

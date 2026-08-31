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
 * Plugin-Einstellungen.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    global $DB;

    if (!$ADMIN->locate('ausbildungsverwaltung')) {
        $ADMIN->add('root', new admin_category(
            'ausbildungsverwaltung',
            new lang_string('admin:uebersicht', 'local_berufsbildung')
        ));
    }

    $adminbereiche = [
        'ausbildungsverwaltung_organisation' => 'admin:organisation',
        'ausbildungsverwaltung_planung' => 'admin:planung',
        'ausbildungsverwaltung_lernbegleitung' => 'admin:lernbegleitung',
        'ausbildungsverwaltung_system' => 'admin:system',
    ];
    foreach ($adminbereiche as $name => $string) {
        if (!$ADMIN->locate($name)) {
            $ADMIN->add('ausbildungsverwaltung', new admin_category(
                $name,
                new lang_string($string, 'local_berufsbildung')
            ));
        }
    }

    $settings = new admin_settingpage(
        'local_berufsbildung_settings',
        new lang_string('settings:einstellungen', 'local_berufsbildung')
    );
    $ADMIN->add('ausbildungsverwaltung_system', $settings);

    // Auswahlliste aus den vorhandenen benutzerdefinierten Profilfeldern,
    // statt den Kurznamen frei eintippen zu lassen.
    $profilefields = $DB->get_records_menu('user_info_field', null, 'name ASC', 'shortname, name');
    if (empty($profilefields)) {
        $profilefields = ['' => get_string('settings:profilefield_none', 'local_berufsbildung')];
    }

    $settings->add(new admin_setting_configselect(
        'local_berufsbildung/profilefield_beruf',
        new lang_string('settings:profilefield_beruf', 'local_berufsbildung'),
        new lang_string('settings:profilefield_beruf_desc', 'local_berufsbildung'),
        'beruf',
        $profilefields
    ));

    $settings->add(new admin_setting_configselect(
        'local_berufsbildung/profilefield_jahrgang',
        new lang_string('settings:profilefield_jahrgang', 'local_berufsbildung'),
        new lang_string('settings:profilefield_jahrgang_desc', 'local_berufsbildung'),
        'jahrgang',
        $profilefields
    ));

    $settings->add(new admin_setting_configselect(
        'local_berufsbildung/startmonat',
        new lang_string('settings:startmonat', 'local_berufsbildung'),
        new lang_string('settings:startmonat_desc', 'local_berufsbildung'),
        '8',
        array_combine(range(1, 12), range(1, 12))
    ));

    $settings->add(new admin_setting_configselect(
        'local_berufsbildung/lehrdauer_semester',
        new lang_string('settings:lehrdauer_semester', 'local_berufsbildung'),
        new lang_string('settings:lehrdauer_semester_desc', 'local_berufsbildung'),
        '8',
        array_combine(range(1, 8), range(1, 8))
    ));

    $settings->add(new admin_setting_configtextarea(
        'local_berufsbildung/beruf_dauer',
        new lang_string('settings:beruf_dauer', 'local_berufsbildung'),
        new lang_string('settings:beruf_dauer_desc', 'local_berufsbildung'),
        '',
        PARAM_RAW
    ));

    $settings->add(new admin_setting_configtextarea(
        'local_berufsbildung/beruf_rahmen_mapping',
        new lang_string('settings:beruf_rahmen_mapping', 'local_berufsbildung'),
        new lang_string('settings:beruf_rahmen_mapping_desc', 'local_berufsbildung'),
        '',
        PARAM_RAW
    ));

    $settings->add(new admin_setting_configtextarea(
        'local_berufsbildung/beruf_wahlpflicht_hk',
        new lang_string('settings:beruf_wahlpflicht_hk', 'local_berufsbildung'),
        new lang_string('settings:beruf_wahlpflicht_hk_desc', 'local_berufsbildung'),
        'AU_EFZ=7777 a.04,7777 a.05,7777 a.06,7777 b.06,7777 b.07,7777 c.04,' .
            '7777 c.05,7777 c.06,7777 d.04,7777 d.05,7777 d.06,7777 d.07',
        PARAM_RAW
    ));

    $settings->add(new admin_setting_configtext(
        'local_berufsbildung/versetzungsplan_schwelle_prozent',
        new lang_string('settings:versetzungsplan_schwelle_prozent', 'local_berufsbildung'),
        new lang_string('settings:versetzungsplan_schwelle_prozent_desc', 'local_berufsbildung'),
        '20',
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_berufsbildung/versetzungsplan_alterung_tage',
        new lang_string('settings:versetzungsplan_alterung_tage', 'local_berufsbildung'),
        new lang_string('settings:versetzungsplan_alterung_tage_desc', 'local_berufsbildung'),
        '10',
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_berufsbildung/retention_monate',
        new lang_string('settings:retention_monate', 'local_berufsbildung'),
        new lang_string('settings:retention_monate_desc', 'local_berufsbildung'),
        '12',
        PARAM_INT
    ));

    $ADMIN->add('ausbildungsverwaltung_organisation', new admin_externalpage(
        'local_berufsbildung_zuordnung',
        new lang_string('zuordnung:uebersicht', 'local_berufsbildung'),
        new moodle_url('/local/berufsbildung/zuordnung.php'),
        'local/berufsbildung:viewzuordnung'
    ));

    $ADMIN->add('ausbildungsverwaltung_organisation', new admin_externalpage(
        'local_berufsbildung_kohortenlinks',
        new lang_string('kohortenlink:uebersicht', 'local_berufsbildung'),
        new moodle_url('/local/berufsbildung/kohorten_links.php'),
        'local/berufsbildung:managezuordnung'
    ));

    $ADMIN->add('ausbildungsverwaltung_planung', new admin_externalpage(
        'local_berufsbildung_bloecke',
        new lang_string('bloecke:uebersicht', 'local_berufsbildung'),
        new moodle_url('/local/berufsbildung/bloecke.php'),
        'local/berufsbildung:manageblocks'
    ));

    $ADMIN->add('ausbildungsverwaltung_planung', new admin_externalpage(
        'local_berufsbildung_importplan',
        new lang_string('planimport:titel', 'local_berufsbildung'),
        new moodle_url('/local/berufsbildung/import_plan.php'),
        'local/berufsbildung:importplan'
    ));

    $ADMIN->add('ausbildungsverwaltung_system', new admin_externalpage(
        'local_berufsbildung_aufbewahrung',
        new lang_string('aufbewahrung:uebersicht', 'local_berufsbildung'),
        new moodle_url('/local/berufsbildung/aufbewahrung.php'),
        'local/berufsbildung:manageaufbewahrung'
    ));
}

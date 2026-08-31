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
 * Uebersicht aller Zuordnungen, mit Filtern, Sortierung und CSV/Excel-Export.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\reportbuilder\local\systemreports;

use context_system;
use lang_string;
use moodle_url;
use pix_icon;
use stdClass;
use core_reportbuilder\local\entities\user;
use core_reportbuilder\local\report\action;
use core_reportbuilder\local\report\column;
use core_reportbuilder\system_report;
use local_berufsbildung\api;
use local_berufsbildung\reportbuilder\local\entities\zuordnung;

class zuordnungen extends system_report {

    protected function initialise(): void {
        $zuordnungentity = new zuordnung();
        $mainalias = $zuordnungentity->get_table_alias('local_berufsbildung_zuordnung');

        $this->set_main_table('local_berufsbildung_zuordnung', $mainalias);
        $this->add_entity($zuordnungentity);

        // Zwei getrennte Instanzen derselben core-user-Entity - je einmal
        // fuer die Berufsbildner- und einmal fuer die Lernenden-Seite der
        // Zuordnung (Muster: core_notes\reportbuilder\local\entities zeigt
        // dasselbe fuer "Empfaenger" vs. "Verfasser").
        $berufsbildnerentity = (new user())
            ->set_entity_name('berufsbildner')
            ->set_entity_title(new lang_string('zuordnung:berufsbildner', 'local_berufsbildung'));
        $berufsbildneralias = $berufsbildnerentity->get_table_alias('user');
        $this->add_entity($berufsbildnerentity->add_join(
            "LEFT JOIN {user} {$berufsbildneralias} ON {$berufsbildneralias}.id = {$mainalias}.berufsbildnerid"
        ));

        $lernendeentity = (new user())
            ->set_entity_name('lernende')
            ->set_entity_title(new lang_string('zuordnung:lernende', 'local_berufsbildung'));
        $lernendealias = $lernendeentity->get_table_alias('user');
        $this->add_entity($lernendeentity->add_join(
            "LEFT JOIN {user} {$lernendealias} ON {$lernendealias}.id = {$mainalias}.lernendeid"
        ));

        // Von den Aktionen und der Lehrjahr-Spalte benoetigt, unabhaengig
        // davon, ob sie als sichtbare Spalten ausgewaehlt sind.
        $this->add_base_fields("{$mainalias}.id, {$mainalias}.lernendeid, {$mainalias}.gueltig_bis");

        $this->add_columns();
        $this->add_filters();
        $this->add_actions();

        $this->set_downloadable(true);
        $this->set_initial_sort_column('lernende:fullname', SORT_ASC);
    }

    /**
     * @return bool
     */
    protected function can_view(): bool {
        return has_any_capability(
            ['local/berufsbildung:managezuordnung', 'local/berufsbildung:viewzuordnung'],
            $this->get_context()
        );
    }

    protected function add_columns(): void {
        $this->add_column_from_entity('lernende:fullname')
            ->set_title(new lang_string('zuordnung:lernende', 'local_berufsbildung'));
        $this->add_column_from_entity('berufsbildner:fullname')
            ->set_title(new lang_string('zuordnung:berufsbildner', 'local_berufsbildung'));
        $this->add_column_from_entity('zuordnung:rolle');
        $this->add_column_from_entity('zuordnung:beruf');

        // Berechnet ueber api::get_ausbildungsstand(), nicht ueber SQL - die
        // Semesterberechnung (inkl. Lehrdauer je Beruf) lebt bewusst nur an
        // dieser einen Stelle, keine zweite Implementierung in einem
        // Filterausdruck. Deshalb informativ, nicht filterbar.
        $this->add_column((new column(
            'lehrjahr',
            new lang_string('zuordnung:lehrjahr', 'local_berufsbildung'),
            $this->get_entity('zuordnung')->get_entity_name()
        ))
            ->add_fields("{$this->get_entity('zuordnung')->get_table_alias('local_berufsbildung_zuordnung')}.lernendeid")
            ->set_type(column::TYPE_TEXT)
            ->add_callback(static function ($unused, stdClass $row): string {
                $stand = api::get_ausbildungsstand((int) $row->lernendeid);

                return $stand !== null ? (string) $stand->lehrjahr : '-';
            }));

        $this->add_column_from_entity('zuordnung:status');
        $this->add_column_from_entity('zuordnung:gueltig_von');
        $this->add_column_from_entity('zuordnung:gueltig_bis');
    }

    protected function add_filters(): void {
        $this->add_filters_from_entity('zuordnung', ['rolle', 'beruf', 'status', 'gueltig_von']);
        $this->add_filters_from_entity('lernende', ['fullname']);
        $this->add_filters_from_entity('berufsbildner', ['fullname']);
    }

    protected function add_actions(): void {
        // Laufende Zuordnung: beenden.
        $this->add_action((new action(
            new moodle_url('/local/berufsbildung/zuordnung_beenden.php', ['id' => ':id']),
            new pix_icon('t/delete', '', 'core'),
            [],
            false,
            new lang_string('zuordnung:beenden', 'local_berufsbildung')
        ))->add_callback(static function (stdClass $row): bool {
            return $row->gueltig_bis === null
                && has_capability('local/berufsbildung:managezuordnung', context_system::instance());
        }));

        // Beendete Zuordnung: Enddatum bearbeiten oder leeren, um die
        // Zuordnung wieder laufend zu machen - dieselbe Seite wie oben.
        $this->add_action((new action(
            new moodle_url('/local/berufsbildung/zuordnung_beenden.php', ['id' => ':id']),
            new pix_icon('t/edit', '', 'core'),
            [],
            false,
            new lang_string('zuordnung:bearbeiten', 'local_berufsbildung')
        ))->add_callback(static function (stdClass $row): bool {
            return $row->gueltig_bis !== null
                && has_capability('local/berufsbildung:managezuordnung', context_system::instance());
        }));

        $this->add_action((new action(
            new moodle_url('/local/berufsbildung/zuordnung_loeschen.php', ['id' => ':id']),
            new pix_icon('t/delete', '', 'core'),
            [],
            false,
            new lang_string('zuordnung:loeschen', 'local_berufsbildung')
        ))->add_callback(static function (): bool {
            return has_capability('local/berufsbildung:managezuordnung', context_system::instance());
        }));
    }
}

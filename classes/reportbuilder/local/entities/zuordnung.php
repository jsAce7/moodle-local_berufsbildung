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
 * Report-Builder-Entity fuer die Zuordnungstabelle.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\reportbuilder\local\entities;

use lang_string;
use core_reportbuilder\local\entities\base;
use core_reportbuilder\local\filters\{date, select, text};
use core_reportbuilder\local\helpers\format;
use core_reportbuilder\local\report\{column, filter};

/**
 * Spalten und Filter fuer local_berufsbildung_zuordnung, unabhaengig davon,
 * ob sie ueber die Berufsbildner- oder die Lernenden-Seite verknuepft wird -
 * das erledigt die Datasource ueber zwei separate user-Entities.
 */
class zuordnung extends base {

    /**
     * @return string[]
     */
    protected function get_default_tables(): array {
        return ['local_berufsbildung_zuordnung'];
    }

    /**
     * @return lang_string
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('zuordnung:uebersicht', 'local_berufsbildung');
    }

    /**
     * @return base
     */
    public function initialise(): base {
        foreach ($this->get_all_columns() as $column) {
            $this->add_column($column);
        }

        // Alle Filter sind auch als Bedingungen nutzbar - Standardmuster,
        // siehe core_notes\reportbuilder\local\entities\note.
        foreach ($this->get_all_filters() as $filter) {
            $this
                ->add_filter($filter)
                ->add_condition($filter);
        }

        return $this;
    }

    /**
     * @return column[]
     */
    protected function get_all_columns(): array {
        $alias = $this->get_table_alias('local_berufsbildung_zuordnung');
        $kohortenlinkalias = 'lbkl';
        $kohortenalias = 'lbc';

        $columns[] = (new column(
            'rolle',
            new lang_string('zuordnung:rolle', 'local_berufsbildung'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_fields("{$alias}.rolle")
            ->set_is_sortable(true);

        $columns[] = (new column(
            'beruf',
            new lang_string('zuordnung:beruf', 'local_berufsbildung'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_fields("{$alias}.beruf")
            ->set_is_sortable(true);

        $columns[] = (new column(
            'herkunft',
            new lang_string('zuordnung:herkunft', 'local_berufsbildung'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_join("LEFT JOIN {local_berufsbildung_kohorten_link} {$kohortenlinkalias}
                ON {$kohortenlinkalias}.id = {$alias}.kohorten_link_id")
            ->add_join("LEFT JOIN {cohort} {$kohortenalias} ON {$kohortenalias}.id = {$kohortenlinkalias}.cohortid")
            ->set_type(column::TYPE_TEXT)
            ->add_fields("{$alias}.kohorten_link_id, {$kohortenalias}.name AS kohortenname")
            ->set_is_sortable(true)
            ->add_callback(static function (?int $kohortenlinkid, \stdClass $row): string {
                if ($kohortenlinkid === null) {
                    return get_string('zuordnung:herkunft_manuell', 'local_berufsbildung');
                }

                return get_string('zuordnung:herkunft_kohorte', 'local_berufsbildung',
                    format_string($row->kohortenname ?? '-'));
            });

        $columns[] = (new column(
            'status',
            new lang_string('zuordnung:status', 'local_berufsbildung'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_fields("{$alias}.gueltig_bis")
            ->set_is_sortable(true)
            ->add_callback(static function (?string $gueltigbis): string {
                return $gueltigbis === null
                    ? get_string('zuordnung:status_laufend', 'local_berufsbildung')
                    : get_string('zuordnung:status_beendet', 'local_berufsbildung');
            });

        $columns[] = (new column(
            'gueltig_von',
            new lang_string('zuordnung:gueltig_von', 'local_berufsbildung'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TIMESTAMP)
            ->add_fields("{$alias}.gueltig_von")
            ->set_is_sortable(true)
            ->add_callback([format::class, 'userdate']);

        $columns[] = (new column(
            'gueltig_bis',
            new lang_string('zuordnung:gueltig_bis', 'local_berufsbildung'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TIMESTAMP)
            ->add_fields("{$alias}.gueltig_bis")
            ->set_is_sortable(true)
            ->add_callback(static function (?int $gueltigbis): string {
                if ($gueltigbis === null) {
                    return get_string('zuordnung:status_laufend', 'local_berufsbildung');
                }

                return userdate($gueltigbis, get_string('strftimedate', 'langconfig'));
            });

        return $columns;
    }

    /**
     * @return filter[]
     */
    protected function get_all_filters(): array {
        $alias = $this->get_table_alias('local_berufsbildung_zuordnung');

        $filters[] = (new filter(
            text::class,
            'rolle',
            new lang_string('zuordnung:rolle', 'local_berufsbildung'),
            $this->get_entity_name(),
            "{$alias}.rolle"
        ))->add_joins($this->get_joins());

        $filters[] = (new filter(
            text::class,
            'beruf',
            new lang_string('zuordnung:beruf', 'local_berufsbildung'),
            $this->get_entity_name(),
            "{$alias}.beruf"
        ))->add_joins($this->get_joins());

        // 1 = laufend (gueltig_bis IS NULL), 0 = beendet - als Ausdruck statt
        // eigener Spalte, damit sich auch danach filtern laesst.
        $filters[] = (new filter(
            select::class,
            'status',
            new lang_string('zuordnung:status', 'local_berufsbildung'),
            $this->get_entity_name(),
            "CASE WHEN {$alias}.gueltig_bis IS NULL THEN 1 ELSE 0 END"
        ))
            ->add_joins($this->get_joins())
            ->set_options([
                1 => get_string('zuordnung:status_laufend', 'local_berufsbildung'),
                0 => get_string('zuordnung:status_beendet', 'local_berufsbildung'),
            ]);

        $filters[] = (new filter(
            date::class,
            'gueltig_von',
            new lang_string('zuordnung:gueltig_von', 'local_berufsbildung'),
            $this->get_entity_name(),
            "{$alias}.gueltig_von"
        ))->add_joins($this->get_joins());

        return $filters;
    }
}

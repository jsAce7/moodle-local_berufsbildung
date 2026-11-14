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
use core_reportbuilder\local\helpers\database;
use core_reportbuilder\local\helpers\format;
use core_reportbuilder\local\report\{column, filter};

/**
 * Spalten und Filter fuer local_berufsbildung_zuordnung, unabhaengig davon,
 * ob sie ueber die Berufsbildner- oder die Lernenden-Seite verknuepft wird -
 * das erledigt die Datasource ueber zwei separate user-Entities.
 */
class zuordnung extends base {
    /** Status: noch nicht begonnen. */
    public const STATUS_GEPLANT = 2;

    /** Status: laeuft heute. */
    public const STATUS_LAUFEND = 1;

    /** Status: beendet. */
    public const STATUS_BEENDET = 0;

    /**
     * Tabellen, auf denen diese Entity aufbaut.
     *
     * @return string[]
     */
    protected function get_default_tables(): array {
        return ['local_berufsbildung_zuordnung'];
    }

    /**
     * Titel dieser Entity.
     *
     * @return lang_string
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('zuordnung:uebersicht', 'local_berufsbildung');
    }

    /**
     * Initialisiert die Entity.
     *
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
     * Alle Spalten, die diese Entity anbietet.
     *
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
            ->add_callback(static function (?string $kohortenlinkid, \stdClass $row): string {
                if ($kohortenlinkid === null) {
                    return get_string('zuordnung:herkunft_manuell', 'local_berufsbildung');
                }

                return get_string(
                    'zuordnung:herkunft_kohorte',
                    'local_berufsbildung',
                    format_string($row->kohortenname ?? '-')
                );
            });

        [$statussql, $statusparams] = $this->status_sql($alias);
        $columns[] = (new column(
            'status',
            new lang_string('zuordnung:status', 'local_berufsbildung'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_INTEGER)
            ->add_field($statussql, 'status', $statusparams)
            ->set_is_sortable(true)
            ->add_callback(static function (?int $status): string {
                return self::status_optionen()[(int) $status] ?? '';
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
     * Alle Filter, die diese Entity anbietet.
     *
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

        // Als Ausdruck statt eigener Spalte, damit sich auch danach filtern
        // laesst - siehe status_sql().
        [$statussql, $statusparams] = $this->status_sql($alias);
        $filters[] = (new filter(
            select::class,
            'status',
            new lang_string('zuordnung:status', 'local_berufsbildung'),
            $this->get_entity_name(),
            $statussql,
            $statusparams
        ))
            ->add_joins($this->get_joins())
            ->set_options(self::status_optionen());

        $filters[] = (new filter(
            date::class,
            'gueltig_von',
            new lang_string('zuordnung:gueltig_von', 'local_berufsbildung'),
            $this->get_entity_name(),
            "{$alias}.gueltig_von"
        ))->add_joins($this->get_joins());

        return $filters;
    }

    /**
     * Status zum jetzigen Zeitpunkt, dieselbe Bedingung wie
     * api::is_zustaendig(): eine Zuordnung mit kuenftigem Enddatum laeuft
     * noch, eine noch nicht begonnene ist geplant.
     *
     * @param string $alias
     * @return array{0: string, 1: array} [SQL, Parameter]
     */
    private function status_sql(string $alias): array {
        $jetzt = time();
        $von = database::generate_param_name();
        $bis = database::generate_param_name();

        return [
            "CASE WHEN {$alias}.gueltig_von > :{$von} THEN " . self::STATUS_GEPLANT . "
                  WHEN {$alias}.gueltig_bis IS NOT NULL AND {$alias}.gueltig_bis < :{$bis} THEN " . self::STATUS_BEENDET . "
                  ELSE " . self::STATUS_LAUFEND . " END",
            [$von => $jetzt, $bis => $jetzt],
        ];
    }

    /**
     * Anzeigenamen der Status.
     *
     * @return array<int, string>
     */
    private static function status_optionen(): array {
        return [
            self::STATUS_GEPLANT => get_string('zuordnung:status_geplant', 'local_berufsbildung'),
            self::STATUS_LAUFEND => get_string('zuordnung:status_laufend', 'local_berufsbildung'),
            self::STATUS_BEENDET => get_string('zuordnung:status_beendet', 'local_berufsbildung'),
        ];
    }
}

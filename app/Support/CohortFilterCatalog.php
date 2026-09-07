<?php

namespace App\Support;

use App\Models\SchemaLegend;
use App\Models\SchemaLegendColumn;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Cataloga i campi filtrabili delle tabelle documentate della coorte
 * (patients, patient_visits) a partire dalla legenda: campi data con i relativi
 * preset di intervallo, campi flag (tinyint) e campi collegati a tabelle di
 * lookup con i valori ammessi.
 *
 * Alimenta il form dello studio (ProjectForm) e la descrizione dei filtri.
 */
class CohortFilterCatalog
{
    /** Tabelle documentate su cui è consentito costruire i filtri di coorte. */
    public const TABLES = ['patients', 'patient_visits'];

    /** @var array<int, string> tipi numerici trattati come flag 0/1 */
    protected const FLAG_TYPES = ['tinyint', 'smallint', 'bit', 'bool', 'boolean'];

    /** @var Collection<int, SchemaLegendColumn>|null */
    protected static ?Collection $columns = null;

    /**
     * @return Collection<int, SchemaLegendColumn>
     */
    protected static function columns(): Collection
    {
        if (static::$columns !== null) {
            return static::$columns;
        }

        $legends = SchemaLegend::query()
            ->whereIn('table_name', static::TABLES)
            ->with(['columns' => fn ($query) => $query->orderBy('position')])
            ->get()
            ->keyBy('id');

        return static::$columns = $legends
            ->flatMap(fn (SchemaLegend $legend): Collection => $legend->columns
                ->each(fn (SchemaLegendColumn $column) => $column->setRelation('legend', $legend)))
            ->values();
    }

    /**
     * Svuota la cache statica (utile nei test dopo un re-sync della legenda).
     */
    public static function flush(): void
    {
        static::$columns = null;
    }

    protected static function qualified(SchemaLegendColumn $column): string
    {
        return $column->legend->table_name.'.'.$column->name;
    }

    protected static function label(SchemaLegendColumn $column): string
    {
        $human = $column->comment !== null && trim($column->comment) !== ''
            ? Str::limit(trim($column->comment), 60)
            : Str::headline($column->name);

        return $column->legend->table_name.' · '.$human;
    }

    /**
     * Opzioni per il select di un filtro data: campo qualificato => etichetta.
     *
     * @return array<string, string>
     */
    public static function dateColumnOptions(): array
    {
        return static::columns()
            ->filter(fn (SchemaLegendColumn $column): bool => $column->date_category !== null)
            ->mapWithKeys(fn (SchemaLegendColumn $column): array => [
                static::qualified($column) => static::label($column),
            ])
            ->all();
    }

    /**
     * Preset di intervallo per ciascun campo data: campo => [value => label].
     *
     * @return array<string, array<string, string>>
     */
    public static function datePresetOptions(): array
    {
        return static::columns()
            ->filter(fn (SchemaLegendColumn $column): bool => ! empty($column->date_ranges))
            ->mapWithKeys(fn (SchemaLegendColumn $column): array => [
                static::qualified($column) => collect($column->date_ranges)
                    ->mapWithKeys(fn (array $range): array => [
                        (string) ($range['value'] ?? '') => (string) ($range['label'] ?? $range['value'] ?? ''),
                    ])
                    ->all(),
            ])
            ->all();
    }

    /**
     * Intervallo (from/to) di un preset di un campo data.
     *
     * @return array{from: ?string, to: ?string}
     */
    public static function presetRange(?string $column, ?string $preset): array
    {
        $none = ['from' => null, 'to' => null];

        if ($column === null || $preset === null || $preset === '') {
            return $none;
        }

        $match = static::columns()
            ->first(fn (SchemaLegendColumn $c): bool => static::qualified($c) === $column);

        if ($match === null) {
            return $none;
        }

        foreach ((array) $match->date_ranges as $range) {
            if (($range['value'] ?? null) === $preset) {
                return [
                    'from' => $range['from'] ?? null,
                    'to' => $range['to'] ?? null,
                ];
            }
        }

        return $none;
    }

    /**
     * Campi filtrabili per valore (lookup o flag): campo qualificato => etichetta.
     *
     * @return array<string, string>
     */
    public static function valueColumnOptions(): array
    {
        return static::columns()
            ->filter(fn (SchemaLegendColumn $column): bool => static::isValueFilterable($column))
            ->mapWithKeys(fn (SchemaLegendColumn $column): array => [
                static::qualified($column) => static::label($column)
                    .($column->lookup_table !== null ? '  ['.$column->lookup_table.']' : '  [flag]'),
            ])
            ->all();
    }

    /**
     * Valori ammessi per ciascun campo filtrabile: campo => [valore => etichetta].
     *
     * @return array<string, array<string, string>>
     */
    public static function valueOptions(): array
    {
        return static::columns()
            ->filter(fn (SchemaLegendColumn $column): bool => static::isValueFilterable($column))
            ->mapWithKeys(fn (SchemaLegendColumn $column): array => [
                static::qualified($column) => static::optionsFor($column),
            ])
            ->all();
    }

    protected static function isValueFilterable(SchemaLegendColumn $column): bool
    {
        if ($column->date_category !== null) {
            return false;
        }

        if ($column->lookup_table !== null) {
            return true;
        }

        return in_array(strtolower((string) $column->data_type), static::FLAG_TYPES, true);
    }

    /**
     * @return array<string, string>
     */
    protected static function optionsFor(SchemaLegendColumn $column): array
    {
        if (! empty($column->lookup_values)) {
            return collect($column->lookup_values)
                ->mapWithKeys(function (array $value): array {
                    $raw = (string) ($value['value'] ?? '');
                    $label = (string) ($value['label'] ?? $value['value'] ?? '');

                    return [$raw => $label === '' ? '∅ (vuoto)' : $label];
                })
                ->all();
        }

        return ['1' => 'Sì (1)', '0' => 'No (0)'];
    }
}

<?php

namespace App\Console\Commands;

use App\Models\LookupTable;
use App\Models\SchemaLegend;
use App\Models\SchemaLegendColumn;
use App\Services\DateFieldSemanticsMap;
use App\Services\ResearchDateRangeResolver;
use App\Services\TableSchemaInspector;
use App\Support\DataNavigatorProfile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Costruisce/aggiorna la legenda ispezionando lo schema dbai: per ogni campo
 * memorizza tipo, commento, categoria/range di data semantico oppure i valori
 * della tabella di lookup collegata tramite indice secondario (foreign key).
 * Cataloga inoltre tutte le altre tabelle come lookup e le collega ai campi.
 */
class SyncSchemaLegend extends Command
{
    protected $signature = 'legend:sync {tables?* : Tabelle legenda da sincronizzare (default: tabelle del profilo DataNavigator attivo)}
        {--no-lookups : Non aggiornare il catalogo delle tabelle lookup}';

    protected $description = 'Sincronizza la legenda delle tabelle principali e il catalogo delle tabelle lookup';

    public function handle(
        TableSchemaInspector $inspector,
        DateFieldSemanticsMap $semantics,
        ResearchDateRangeResolver $research,
    ): int {
        $connection = config('legend.connection', 'dbai');
        $database = DB::connection($connection)->getDatabaseName();
        $limit = (int) config('legend.lookup_value_limit', 500);
        $enumMax = (int) config('legend.lookup_enum_max', 150);

        // Tabelle principali: dal profilo DataNavigator del database collegato,
        // con fallback su config/legend.tables.
        $entityTables = $this->profileTables($database);
        $tables = $this->argument('tables') ?: $entityTables;

        $this->info("Legenda per il database '{$database}': ".implode(', ', $tables).'.');

        foreach ($tables as $order => $table) {
            if (! Schema::connection($connection)->hasTable($table)) {
                $this->warn("Tabella '{$table}' non trovata su '{$connection}', saltata.");

                continue;
            }

            $legend = SchemaLegend::query()->updateOrCreate(
                ['connection' => $connection, 'table_name' => $table],
                [
                    'database' => $database,
                    'label' => Str::headline($table),
                    'description' => $inspector->getTableComment($table, $connection),
                    'order' => $order,
                    'synced_at' => now(),
                ],
            );

            $foreignKeys = $inspector->getForeignKeys($table, $connection);
            $keptColumnIds = [];

            foreach ($inspector->getAllColumnsWithComments($table, $connection) as $column) {
                $name = $column['name'];

                $category = $semantics->categoryFor($table, $name)
                    ?? $semantics->categoryFor($table, strtolower($name));

                $lookup = $this->resolveLookup($table, $name, $foreignKeys, $connection, $inspector, $limit, $enumMax, $entityTables);

                $record = SchemaLegendColumn::query()->updateOrCreate(
                    ['schema_legend_id' => $legend->id, 'name' => $name],
                    [
                        'data_type' => $column['type'],
                        'nullable' => (bool) $column['nullable'],
                        'position' => $column['position'],
                        'comment' => $column['comment'],
                        'date_category' => $category?->value,
                        'date_ranges' => $category !== null ? $research->presetsFor($category) : null,
                        'foreign_key_name' => $lookup['name'] ?? null,
                        'lookup_table' => $lookup['table'] ?? null,
                        'lookup_key' => $lookup['key'] ?? null,
                        'lookup_label' => $lookup['label'] ?? null,
                        'lookup_values' => $lookup['values'] ?? null,
                    ],
                );

                $keptColumnIds[] = $record->id;
            }

            $legend->columns()->whereNotIn('id', $keptColumnIds)->delete();
            $legend->update(['columns_count' => count($keptColumnIds)]);

            $this->info("{$table}: ".count($keptColumnIds).' campi sincronizzati.');
        }

        // Sincronizzazione completa (nessun argomento `tables`): rimuove le
        // legende di questo database non più fra le tabelle principali del
        // profilo — es. una codifica spostata dai `tables` alle lookup. Colonne
        // e collegamenti sul pivot vanno via in cascata.
        if ($this->argument('tables') === []) {
            SchemaLegend::query()
                ->where('connection', $connection)
                ->where('database', $database)
                ->whereNotIn('table_name', $tables)
                ->get()
                ->each(function (SchemaLegend $legend): void {
                    $legend->delete();
                    $this->warn("Legenda '{$legend->table_name}' rimossa (non più tra le tabelle principali).");
                });
        }

        if (! $this->option('no-lookups')) {
            $this->syncLookups($inspector, $connection, $limit, $enumMax, $entityTables);
        }

        return self::SUCCESS;
    }

    /**
     * Tabelle principali del profilo DataNavigator che serve il database
     * indicato; fallback su config/legend.tables.
     *
     * @return list<string>
     */
    protected function profileTables(string $database): array
    {
        foreach ((array) config('data_navigator.profiles', []) as $profile) {
            if (in_array($database, (array) ($profile['databases'] ?? []), true)) {
                return array_values(array_filter((array) ($profile['tables'] ?? []), 'is_string'));
            }
        }

        $default = config('data_navigator.profiles.'.config('data_navigator.default').'.tables');

        return array_values(array_filter(
            (array) ($default ?: config('legend.tables', ['patients', 'patient_visits'])),
            'is_string',
        ));
    }

    /**
     * Cataloga tutte le tabelle dbai diverse dalle entità come tabelle lookup e
     * ne aggancia i valori; collega poi ogni lookup ai campi che vi puntano
     * (senza rimuovere i collegamenti aggiunti manualmente).
     *
     * @param  list<string>  $entityTables
     */
    protected function syncLookups(
        TableSchemaInspector $inspector,
        string $connection,
        int $limit,
        int $enumMax,
        array $entityTables,
    ): void {
        $exclude = (array) config('legend.lookup_exclude', []);
        $database = DB::connection($connection)->getDatabaseName();

        // Le tabelle indicate come lookup da un hint del profilo restano nel
        // catalogo anche se sono tabelle principali (es. `pratiches_statos`),
        // così i campi che vi puntano ottengono il collegamento sul pivot.
        $hintTargets = $this->hintLookupTables();

        $allTables = collect(DB::connection($connection)->select(
            "SELECT TABLE_NAME AS name FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME",
            [$database],
        ))
            ->pluck('name')
            ->reject(fn (string $name): bool => (in_array($name, $entityTables, true) && ! in_array($name, $hintTargets, true))
                || Str::is($exclude, $name))
            ->values();

        $catalogued = 0;

        foreach ($allTables as $name) {
            try {
                $columns = $inspector->getAllColumnsWithComments($name, $connection);
                $columnNames = collect($columns)->pluck('name');

                $key = $columnNames->first(fn (string $c): bool => strtolower($c) === 'id') ?? ($columnNames->first() ?? 'id');
                $label = $columnNames->first(fn (string $c): bool => strtolower($c) !== strtolower($key));

                $rowCount = (int) DB::connection($connection)->table($name)->count();
                $isDictionary = $rowCount <= $enumMax;

                LookupTable::query()->updateOrCreate(
                    ['connection' => $connection, 'table_name' => $name],
                    [
                        'label' => Str::headline($name),
                        'description' => $inspector->getTableComment($name, $connection),
                        'key_column' => $key,
                        'label_column' => $label !== $key ? $label : null,
                        'row_count' => $rowCount,
                        'is_dictionary' => $isDictionary,
                        'values' => $isDictionary ? $inspector->getLookupValues($name, $key, $connection, $limit) : null,
                        'synced_at' => now(),
                    ],
                );

                $catalogued++;
            } catch (\Throwable $e) {
                $this->warn("lookup '{$name}' saltata: ".$e->getMessage());
            }
        }

        // Aggancio automatico: ogni campo con hint lookup_table punta alla lookup omonima.
        $lookupsByName = LookupTable::query()
            ->where('connection', $connection)
            ->pluck('id', 'table_name');

        $linked = 0;

        SchemaLegendColumn::query()
            ->whereNotNull('lookup_table')
            ->each(function (SchemaLegendColumn $column) use ($lookupsByName, &$linked): void {
                $lookupId = $lookupsByName[$column->lookup_table] ?? null;

                if ($lookupId !== null) {
                    $column->lookupTables()->syncWithoutDetaching([$lookupId]);
                    $linked++;
                }
            });

        $this->info("lookup: {$catalogued} tabelle catalogate, {$linked} collegamenti automatici ai campi.");
    }

    /**
     * Tabelle indicate come lookup dagli hint `lookups` del profilo attivo.
     *
     * @return list<string>
     */
    protected function hintLookupTables(): array
    {
        return collect((array) (DataNavigatorProfile::forCurrentDatabase()['lookups'] ?? []))
            ->map(fn ($hint): ?string => is_array($hint) ? ($hint['table'] ?? null) : (is_string($hint) ? $hint : null))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Determina la lookup collegata a un campo, in ordine di priorità:
     *  1. hint esplicito `lookups` del profilo (`config/data_navigator.php`),
     *     con chiave `tabella.campo` o solo `campo`;
     *  2. vincolo di foreign key reale;
     *  3. euristica `<nome>_id` → tabella al plurale.
     * I valori vengono elencati se la tabella è piccola (<= enum max) e non è
     * un'entità principale, oppure sempre se dichiarata via hint.
     *
     * @param  array<string, array{table: string, column: string, name: ?string}>  $foreignKeys
     * @param  list<string>  $entityTables
     * @return array{table?: string, key?: string, label?: ?string, name?: ?string, values?: array}
     */
    protected function resolveLookup(
        string $table,
        string $column,
        array $foreignKeys,
        string $connection,
        TableSchemaInspector $inspector,
        int $limit,
        int $enumMax,
        array $entityTables,
    ): array {
        $target = null;
        $key = 'id';
        $constraintName = null;
        $declaredByHint = false;

        $hints = (array) (DataNavigatorProfile::forCurrentDatabase()['lookups'] ?? []);
        $hint = $hints["{$table}.{$column}"] ?? $hints[$column] ?? null;

        if ($hint !== null) {
            $target = is_array($hint) ? ($hint['table'] ?? null) : $hint;
            $key = is_array($hint) ? ($hint['key'] ?? 'id') : 'id';
            $declaredByHint = true;
        } elseif (isset($foreignKeys[$column])) {
            $target = $foreignKeys[$column]['table'];
            $key = $foreignKeys[$column]['column'];
            $constraintName = $foreignKeys[$column]['name'];
        } elseif (str_ends_with(strtolower($column), '_id')) {
            $guess = Str::plural(substr($column, 0, -3));

            if (Schema::connection($connection)->hasTable($guess)) {
                $target = $guess;
            }
        }

        if ($target === null || ! Schema::connection($connection)->hasTable($target)) {
            return [];
        }

        $label = collect($inspector->getAllColumnsWithComments($target, $connection))
            ->pluck('name')
            ->first(fn (string $name): bool => strtolower($name) !== strtolower($key));

        $rowCount = (int) DB::connection($connection)->table($target)->count();
        $listValues = $rowCount <= $enumMax && ($declaredByHint || ! in_array($target, $entityTables, true));

        return [
            'table' => $target,
            'key' => $key,
            'label' => $label !== $key ? $label : null,
            'name' => $constraintName,
            'values' => $listValues ? $inspector->getLookupValues($target, $key, $connection, $limit) : null,
        ];
    }
}

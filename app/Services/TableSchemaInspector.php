<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TableSchemaInspector
{
    protected array $dateTypes = ['date', 'datetime', 'timestamp', 'datetimetz', 'timestamptz'];

    public function getDateFilterableColumns(string $table, ?string $connection = null): array
    {
        $suffix = $connection ? "{$connection}." : '';

        return Cache::remember(
            "schema.date_columns.{$suffix}{$table}",
            now()->addHours(6),
            fn () => $this->buildDateFilterableColumns($table, $connection)
        );
    }

    protected function buildDateFilterableColumns(string $table, ?string $connection = null): array
    {
        $columns = Schema::connection($connection)->getColumns($table);
        $comments = $this->getColumnComments($table, $connection);

        return collect($columns)
            ->filter(fn ($col) => $this->isDateType($col['type_name'] ?? $col['type']))
            ->map(fn ($col) => [
                'column' => $col['name'],
                'type' => $col['type_name'] ?? $col['type'],
                'nullable' => $col['nullable'],
                'label' => $comments[$col['name']] ?? $this->humanize($col['name']),
                'granularity' => ($col['type_name'] ?? $col['type']) === 'date' ? 'day' : 'datetime',
            ])
            ->values()
            ->all();
    }

    public function getAllColumnsWithComments(string $table, ?string $connection = null): array
    {
        $columns = Schema::connection($connection)->getColumns($table);
        $comments = $this->getColumnComments($table, $connection);

        return collect($columns)->values()->map(fn ($col, $index) => [
            'name' => $col['name'],
            'type' => $col['type_name'] ?? $col['type'],
            'nullable' => $col['nullable'],
            'position' => $index,
            'comment' => $comments[$col['name']] ?? null,
        ])->all();
    }

    /**
     * Commento della tabella (TABLE_COMMENT su MySQL).
     */
    public function getTableComment(string $table, ?string $connection = null): ?string
    {
        $suffix = $connection ? "{$connection}." : '';

        return Cache::remember("schema.table_comment.{$suffix}{$table}", now()->addHours(6), function () use ($table, $connection) {
            $db = DB::connection($connection);

            if ($db->getDriverName() !== 'mysql') {
                return null;
            }

            $row = $db->selectOne(
                'SELECT TABLE_COMMENT AS c FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
                [$db->getDatabaseName(), $table],
            );

            $comment = trim((string) ($row->c ?? ''));

            return $comment === '' ? null : $comment;
        });
    }

    /**
     * Chiavi esterne (indici secondari) della tabella, indicizzate per colonna.
     *
     * @return array<string, array{table: string, column: string, name: ?string}>
     */
    public function getForeignKeys(string $table, ?string $connection = null): array
    {
        $suffix = $connection ? "{$connection}." : '';

        return Cache::remember("schema.foreign_keys.{$suffix}{$table}", now()->addHours(6), function () use ($table, $connection) {
            try {
                $foreignKeys = Schema::connection($connection)->getForeignKeys($table);
            } catch (\Throwable) {
                return [];
            }

            $map = [];

            foreach ($foreignKeys as $foreignKey) {
                $localColumn = $foreignKey['columns'][0] ?? null;
                $foreignTable = $foreignKey['foreign_table'] ?? null;

                if ($localColumn === null || $foreignTable === null) {
                    continue;
                }

                $map[$localColumn] ??= [
                    'table' => $foreignTable,
                    'column' => $foreignKey['foreign_columns'][0] ?? 'id',
                    'name' => $foreignKey['name'] ?? null,
                ];
            }

            return $map;
        });
    }

    /**
     * Valori di una tabella di lookup: [{value, label}]. `label` è la prima
     * colonna testuale diversa dalla chiave, altrimenti coincide con `value`.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function getLookupValues(string $table, string $keyColumn = 'id', ?string $connection = null, int $limit = 500): array
    {
        $suffix = $connection ? "{$connection}." : '';

        return Cache::remember("schema.lookup.{$suffix}{$table}.{$keyColumn}.{$limit}", now()->addHours(6), function () use ($table, $keyColumn, $connection, $limit) {
            $db = DB::connection($connection);

            try {
                if (! Schema::connection($connection)->hasTable($table)) {
                    return [];
                }

                $columnNames = collect(Schema::connection($connection)->getColumns($table))->pluck('name');
                $labelColumn = $columnNames->first(fn (string $name): bool => strtolower($name) !== strtolower($keyColumn));

                $rows = $db->table($table)
                    ->orderBy($keyColumn)
                    ->limit($limit)
                    ->get([$keyColumn, ...($labelColumn && $labelColumn !== $keyColumn ? [$labelColumn] : [])]);
            } catch (\Throwable) {
                return [];
            }

            return $rows->map(fn ($row): array => [
                'value' => (string) $row->{$keyColumn},
                'label' => (string) ($labelColumn && $labelColumn !== $keyColumn ? ($row->{$labelColumn} ?? $row->{$keyColumn}) : $row->{$keyColumn}),
            ])->all();
        });
    }

    protected function getColumnComments(string $table, ?string $connection = null): array
    {
        $driver = DB::connection($connection)->getDriverName();

        return match ($driver) {
            'mysql' => $this->getMysqlComments($table, $connection),
            'pgsql' => $this->getPgsqlComments($table, $connection),
            default => [],
        };
    }

    protected function getMysqlComments(string $table, ?string $connection = null): array
    {
        $database = DB::connection($connection)->getDatabaseName();

        $rows = DB::connection($connection)->select("
            SELECT COLUMN_NAME as column_name, COLUMN_COMMENT as comment
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_COMMENT != ''
        ", [$database, $table]);

        return collect($rows)->pluck('comment', 'column_name')->all();
    }

    protected function getPgsqlComments(string $table, ?string $connection = null): array
    {
        $rows = DB::connection($connection)->select('
            SELECT a.attname as column_name,
                   col_description(a.attrelid, a.attnum) as comment
            FROM pg_attribute a
            JOIN pg_class c ON a.attrelid = c.oid
            WHERE c.relname = ?
              AND a.attnum > 0
              AND NOT a.attisdropped
              AND col_description(a.attrelid, a.attnum) IS NOT NULL
        ', [$table]);

        return collect($rows)->pluck('comment', 'column_name')->all();
    }

    protected function isDateType(string $type): bool
    {
        $type = strtolower($type);

        return collect($this->dateTypes)->contains(fn ($t) => str_contains($type, $t));
    }

    protected function humanize(string $column): string
    {
        return ucfirst(str_replace('_', ' ', $column));
    }
}

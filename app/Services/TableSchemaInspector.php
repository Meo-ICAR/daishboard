<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TableSchemaInspector
{
    protected array $dateTypes = ['date', 'datetime', 'timestamp', 'datetimetz', 'timestamptz'];

    public function getDateFilterableColumns(string $table): array
    {
        return Cache::remember(
            "schema.date_columns.{$table}",
            now()->addHours(6),
            fn () => $this->buildDateFilterableColumns($table)
        );
    }

    protected function buildDateFilterableColumns(string $table): array
    {
        $columns = Schema::getColumns($table);
        $comments = $this->getColumnComments($table);

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

    public function getAllColumnsWithComments(string $table): array
    {
        $columns = Schema::getColumns($table);
        $comments = $this->getColumnComments($table);

        return collect($columns)->map(fn ($col) => [
            'name' => $col['name'],
            'type' => $col['type_name'] ?? $col['type'],
            'nullable' => $col['nullable'],
            'comment' => $comments[$col['name']] ?? null,
        ])->all();
    }

    protected function getColumnComments(string $table): array
    {
        $driver = DB::connection()->getDriverName();

        return match ($driver) {
            'mysql' => $this->getMysqlComments($table),
            'pgsql' => $this->getPgsqlComments($table),
            default => [],
        };
    }

    protected function getMysqlComments(string $table): array
    {
        $database = DB::connection()->getDatabaseName();

        $rows = DB::select("
            SELECT COLUMN_NAME as column_name, COLUMN_COMMENT as comment
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_COMMENT != ''
        ", [$database, $table]);

        return collect($rows)->pluck('comment', 'column_name')->all();
    }

    protected function getPgsqlComments(string $table): array
    {
        $rows = DB::select('
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

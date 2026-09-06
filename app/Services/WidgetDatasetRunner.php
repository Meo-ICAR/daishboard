<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Esegue la query SQL di un DashboardWidget sulla connessione DBAI applicando,
 * quando richiesto, uno o più filtri data di coorte (patients / patient_visits)
 * iniettati nel SQL prima del raggruppamento.
 *
 * Unico punto di verità condiviso dalla UI Filament e dalla vista pubblica.
 */
class WidgetDatasetRunner
{
    /** Tabelle della coorte pazienti su cui è consentito il filtro data. */
    public const COHORT_TABLES = ['patients', 'patient_visits'];

    public function __construct(
        protected TableSchemaInspector $inspector,
        protected DateFieldSemanticsMap $semantics,
        protected ResearchDateRangeResolver $research,
        protected DateRangeResolver $generic,
    ) {}

    /**
     * @param  array<int, array{column?: ?string, from?: ?string, to?: ?string}>  $columnFilters
     * @return array{columns: list<string>, rows: array<int, array<string, mixed>>, numericColumns: list<string>, error: ?string}
     */
    public function run(?string $sql, array $columnFilters = []): array
    {
        $empty = ['columns' => [], 'rows' => [], 'numericColumns' => [], 'error' => null];

        $query = trim((string) $sql);

        if ($query === '') {
            return [...$empty, 'error' => 'Nessuna query definita per questo widget.'];
        }

        if (! preg_match('/^\s*SELECT\b/i', $query)) {
            return [...$empty, 'error' => 'Sono consentite solo istruzioni SELECT.'];
        }

        [$query, $bindings] = $this->applyFilters($query, $columnFilters);

        try {
            $results = DB::connection('dbai')->select($query, $bindings);
        } catch (Throwable $e) {
            return [...$empty, 'error' => 'Errore nell\'esecuzione della query: '.$e->getMessage()];
        }

        if ($results === []) {
            return $empty;
        }

        $columns = array_keys((array) $results[0]);
        $rows = array_values(array_map(static fn ($row): array => (array) $row, $results));

        $numericColumns = array_values(array_filter(
            $columns,
            fn (string $column): bool => $this->columnIsNumeric($column, $rows),
        ));

        return ['columns' => $columns, 'rows' => $rows, 'numericColumns' => $numericColumns, 'error' => null];
    }

    /**
     * Metadati delle colonne data filtrabili della coorte referenziata nella query.
     *
     * @return array<string, array{expr: string, label: string, presets: array<int, array{value: string, label: string, from: ?string, to: ?string}>}>
     */
    public function dateFilterMetadata(?string $sql): array
    {
        $aliases = $this->resolveCohortTableAliases((string) $sql);
        $metadata = [];

        foreach ($aliases as $table => $alias) {
            try {
                $columns = $this->inspector->getDateFilterableColumns($table, 'dbai');
            } catch (Throwable) {
                continue;
            }

            foreach ($columns as $meta) {
                $column = $meta['column'];
                $category = $this->semantics->categoryFor($table, $column)
                    ?? $this->semantics->categoryFor($table, strtolower($column));

                $presets = $category !== null
                    ? $this->research->presetsFor($category)
                    : $this->generic->allPresetsWithRanges();

                $metadata["{$table}.{$column}"] = [
                    'expr' => '`'.str_replace('`', '', $alias).'`.`'.str_replace('`', '', $column).'`',
                    'label' => ($meta['label'] ?? $column)." · {$table}",
                    'presets' => collect($presets)
                        ->reject(fn (array $preset): bool => isset($preset['special']))
                        ->map(fn (array $preset): array => [
                            'value' => (string) $preset['value'],
                            'label' => (string) $preset['label'],
                            'from' => $preset['from'] ?? null,
                            'to' => $preset['to'] ?? null,
                        ])
                        ->values()
                        ->all(),
                ];
            }
        }

        return $metadata;
    }

    /**
     * Descrizione testuale dei filtri data attivi, o null se nessuno.
     *
     * @param  array<int, array{column?: ?string, from?: ?string, to?: ?string}>  $columnFilters
     */
    public function describeFilters(?string $sql, array $columnFilters): ?string
    {
        $metadata = $this->dateFilterMetadata($sql);
        $parts = [];

        foreach ($this->normalizeColumnFilters($columnFilters, $metadata) as $filter) {
            $label = $metadata[$filter['column']]['label'] ?? $filter['column'];

            $range = match (true) {
                $filter['from'] !== null && $filter['to'] !== null => "dal {$filter['from']} al {$filter['to']}",
                $filter['from'] !== null => "dal {$filter['from']}",
                default => "fino al {$filter['to']}",
            };

            $parts[] = "{$label} {$range}";
        }

        return $parts === [] ? null : 'Coorte filtrata · '.implode('   ·   ', $parts);
    }

    /**
     * @param  array<int, array{column?: ?string, from?: ?string, to?: ?string}>  $columnFilters
     * @return array{0: string, 1: array<int, string>}
     */
    protected function applyFilters(string $query, array $columnFilters): array
    {
        if ($columnFilters === []) {
            return [$query, []];
        }

        $metadata = $this->dateFilterMetadata($query);
        $bindings = [];
        $predicates = [];

        foreach ($this->normalizeColumnFilters($columnFilters, $metadata) as $filter) {
            $expr = $metadata[$filter['column']]['expr'];

            if ($filter['from'] !== null) {
                $predicates[] = "{$expr} >= ?";
                $bindings[] = $filter['from'];
            }

            if ($filter['to'] !== null) {
                $predicates[] = "{$expr} <= ?";
                $bindings[] = $filter['to'].' 23:59:59';
            }
        }

        if ($predicates === []) {
            return [$query, []];
        }

        return [$this->injectCohortPredicate($query, implode(' AND ', $predicates)), $bindings];
    }

    /**
     * Filtra e normalizza le voci: colonna nota e almeno un estremo valorizzato.
     *
     * @param  array<int, array{column?: ?string, from?: ?string, to?: ?string}>  $columnFilters
     * @param  array<string, mixed>  $metadata
     * @return array<int, array{column: string, from: ?string, to: ?string}>
     */
    protected function normalizeColumnFilters(array $columnFilters, array $metadata): array
    {
        $normalized = [];

        foreach ($columnFilters as $filter) {
            $column = $filter['column'] ?? null;
            $from = $this->normalizeDate($filter['from'] ?? null);
            $to = $this->normalizeDate($filter['to'] ?? null);

            if (is_string($column) && isset($metadata[$column]) && ($from !== null || $to !== null)) {
                $normalized[] = ['column' => $column, 'from' => $from, 'to' => $to];
            }
        }

        return $normalized;
    }

    protected function normalizeDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    protected function columnIsNumeric(string $column, array $rows): bool
    {
        $hasValue = false;

        foreach ($rows as $row) {
            $value = $row[$column] ?? null;

            if ($value === null || $value === '') {
                continue;
            }

            if (! is_numeric($value)) {
                return false;
            }

            $hasValue = true;
        }

        return $hasValue;
    }

    /**
     * @return array<string, string> tabella => alias
     */
    public function resolveCohortTableAliases(string $sql): array
    {
        $masked = $this->maskNestedSql($sql);
        $tableList = implode('|', array_map('preg_quote', self::COHORT_TABLES));

        preg_match_all(
            '/\b(?:from|join)\s+`?('.$tableList.')`?(?:\s+(?:as\s+)?`?([a-z_][a-z0-9_]*)`?)?/i',
            $masked,
            $matches,
            PREG_SET_ORDER,
        );

        $reserved = [
            'on', 'where', 'group', 'order', 'having', 'limit', 'inner', 'left',
            'right', 'outer', 'join', 'cross', 'union', 'using', 'natural', 'straight_join',
        ];

        $aliases = [];

        foreach ($matches as $match) {
            $table = strtolower($match[1]);
            $alias = $match[2] ?? '';

            if ($alias === '' || in_array(strtolower($alias), $reserved, true)) {
                $alias = $table;
            }

            $aliases[$table] ??= $alias;
        }

        return $aliases;
    }

    protected function maskNestedSql(string $sql): string
    {
        $out = '';
        $depth = 0;
        $quote = null;
        $length = strlen($sql);

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];

            if ($quote !== null) {
                $out .= ' ';

                if ($char === $quote && ($i === 0 || $sql[$i - 1] !== '\\')) {
                    $quote = null;
                }

                continue;
            }

            if ($char === "'" || $char === '"' || $char === '`') {
                $quote = $char;
                $out .= ' ';

                continue;
            }

            if ($char === '(') {
                $depth++;
                $out .= ' ';

                continue;
            }

            if ($char === ')') {
                $depth = max(0, $depth - 1);
                $out .= ' ';

                continue;
            }

            $out .= $depth === 0 ? $char : ' ';
        }

        return $out;
    }

    protected function injectCohortPredicate(string $sql, string $predicate): string
    {
        $base = rtrim(trim($sql), "; \t\n\r\0\x0B");
        $masked = $this->maskNestedSql($base);

        $boundary = strlen($base);

        if (preg_match(
            '/\b(?:group\s+by|having|order\s+by|limit|union|window|offset)\b/i',
            $masked,
            $match,
            PREG_OFFSET_CAPTURE,
        )) {
            $boundary = $match[0][1];
        }

        $hasMainWhere = preg_match('/\bwhere\b/i', $masked, $whereMatch, PREG_OFFSET_CAPTURE)
            && $whereMatch[0][1] < $boundary;

        $clause = $hasMainWhere ? " AND ({$predicate}) " : " WHERE ({$predicate}) ";

        return substr($base, 0, $boundary).$clause.substr($base, $boundary);
    }
}

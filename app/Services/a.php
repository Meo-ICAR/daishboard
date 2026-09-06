<?php

namespace App\Services;

use App\Enums\DateRangePreset;
use Illuminate\Contracts\Database\Query\Builder;

class QueryFilterService
{
    // colonne che richiedono sempre esclusione di un valore di default noto
    protected array $excludeDefaults = [
        'patients.datahiv' => '2000-01-01',
    ];

    public function __construct(protected DateRangeResolver $rangeResolver) {}

    /**
     * $filters = [
     *   ['column' => 'visite.data_visita', 'preset' => 'last_30_days'],
     *   ['column' => 'pazienti.data_nascita', 'from' => '1990-01-01', 'to' => null],
     * ]
     */
    public function applyDateFilters(Builder $query, array $filters): Builder
    {
        foreach ($filters as $filter) {
            $column = $filter['column'];
            $preset = isset($filter['preset']) ? DateRangePreset::tryFrom($filter['preset']) : null;

            if ($preset && $preset !== DateRangePreset::Custom) {
                $range = $this->rangeResolver->resolve($preset);
                $from = $range['from'];
                $to = $range['to'];
            } else {
                $from = $filter['from'] ?? null;
                $to = $filter['to'] ?? null;
            }

            $query->when($from, fn ($q) => $q->whereDate($column, '>=', $from))
                ->when($to, fn ($q) => $q->whereDate($column, '<=', $to));

            // escludi automaticamente i valori di default noti (es. datahiv)
            if (isset($this->excludeDefaults[$column])) {
                $query->where($column, '!=', $this->excludeDefaults[$column]);
            }
        }

        return $query;
    }

    /**
     * Whitelist di sicurezza: valida che una colonna richiesta dal frontend
     * sia effettivamente una colonna data filtrabile nota per quella tabella.
     */
    public function validateColumn(TableSchemaInspector $inspector, string $table, string $column): void
    {
        $allowed = collect($inspector->getDateFilterableColumns($table))->pluck('column');

        if (! $allowed->contains($column)) {
            abort(422, "Colonna '{$column}' non filtrabile o non esistente in {$table}.");
        }
    }
}

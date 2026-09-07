<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Un campo documentato nella legenda: comment dal DB, eventuale range di date
 * semantico oppure i valori della tabella di lookup collegata (indice secondario).
 */
class SchemaLegendColumn extends Model
{
    use HasFactory;

    protected $fillable = [
        'schema_legend_id',
        'name',
        'data_type',
        'nullable',
        'position',
        'comment',
        'date_category',
        'date_ranges',
        'foreign_key_name',
        'lookup_table',
        'lookup_key',
        'lookup_label',
        'lookup_values',
    ];

    protected function casts(): array
    {
        return [
            'nullable' => 'boolean',
            'position' => 'integer',
            'date_ranges' => 'array',
            'lookup_values' => 'array',
        ];
    }

    public function legend(): BelongsTo
    {
        return $this->belongsTo(SchemaLegend::class, 'schema_legend_id');
    }

    /**
     * Tabelle di lookup collegate a questo campo.
     */
    public function lookupTables(): BelongsToMany
    {
        return $this->belongsToMany(LookupTable::class, 'lookup_table_column')
            ->withTimestamps();
    }

    public function isDate(): bool
    {
        return $this->date_category !== null;
    }

    public function isLookup(): bool
    {
        return $this->lookup_table !== null;
    }

    /**
     * Riepilogo testuale del range di date o dei valori di lookup.
     */
    public function summary(): ?string
    {
        if ($this->isDate() && $this->date_ranges) {
            return collect($this->date_ranges)
                ->map(fn (array $range): string => $range['label'] ?? ($range['value'] ?? ''))
                ->filter()
                ->implode(' · ');
        }

        if ($this->isLookup() && $this->lookup_values) {
            return collect($this->lookup_values)
                ->map(fn (array $value): string => (string) ($value['label'] ?? $value['value'] ?? ''))
                ->map(fn (string $value): string => $value === '' ? '∅' : $value)
                ->implode(', ');
        }

        return null;
    }
}

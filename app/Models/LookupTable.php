<?php

namespace App\Models;

use App\Services\TableSchemaInspector;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Una tabella di lookup/dizionario del database (dbai), con i suoi valori e i
 * campi di patients / patient_visits a cui è collegata.
 */
class LookupTable extends Model
{
    use HasFactory;

    protected $fillable = [
        'connection',
        'table_name',
        'label',
        'description',
        'key_column',
        'label_column',
        'row_count',
        'is_dictionary',
        'values',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'row_count' => 'integer',
            'is_dictionary' => 'boolean',
            'values' => 'array',
            'synced_at' => 'datetime',
        ];
    }

    /**
     * Campi della legenda (patients / patient_visits) collegati a questa lookup.
     */
    public function columns(): BelongsToMany
    {
        return $this->belongsToMany(SchemaLegendColumn::class, 'lookup_table_column')
            ->withTimestamps();
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->label ?: $this->table_name;
    }

    /**
     * Valori letti dal vivo dal database (per tabelle troppo grandi per essere memorizzate).
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function liveValues(int $limit = 1000): array
    {
        return app(TableSchemaInspector::class)->getLookupValues(
            $this->table_name,
            $this->key_column ?: 'id',
            $this->connection,
            $limit,
        );
    }
}

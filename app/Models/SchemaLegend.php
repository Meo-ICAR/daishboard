<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Legenda di una tabella del database (dbai): campi, comment, range di date e
 * valori delle lookup collegate.
 */
class SchemaLegend extends Model
{
    use HasFactory;

    protected $fillable = [
        'connection',
        'database',
        'table_name',
        'label',
        'description',
        'columns_count',
        'order',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'columns_count' => 'integer',
            'order' => 'integer',
            'synced_at' => 'datetime',
        ];
    }

    public function columns(): HasMany
    {
        return $this->hasMany(SchemaLegendColumn::class)->orderBy('position');
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->label ?: $this->table_name;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DashboardWidget extends Model
{
    use HasFactory;

    protected $fillable = [
        'dashboard_id',
        'chat_history_id',
        'master_widget_id',
        'master_filter_column',
        'title',
        'type',
        'query',
        'settings',
        'grid_position',
        'order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'grid_position' => 'array',
            'order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function dashboard(): BelongsTo
    {
        return $this->belongsTo(Dashboard::class);
    }

    public function chatHistory(): BelongsTo
    {
        return $this->belongsTo(ChatHistory::class);
    }

    /**
     * Widget master di cui questo widget è un dettaglio/drill-down.
     */
    public function masterWidget(): BelongsTo
    {
        return $this->belongsTo(self::class, 'master_widget_id');
    }

    /**
     * Widget di dettaglio che puntano a questo widget come master.
     */
    public function detailWidgets(): HasMany
    {
        return $this->hasMany(self::class, 'master_widget_id');
    }
}

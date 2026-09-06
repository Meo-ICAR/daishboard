<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DashboardWidget extends Model
{
    use HasFactory;

    protected $fillable = [
        'dashboard_id',
        'chat_history_id',
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
}

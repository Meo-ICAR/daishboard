<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'thread_id',
        'messages',
    ];

    protected function casts(): array
    {
        return [
            'messages' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('owned', function (Builder $builder): void {
            $userId = auth()->id();

            $builder->where(function (Builder $query) use ($userId): void {
                $query->whereNull('user_id')
                      ->orWhere('user_id', $userId);
            });
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function widgets(): HasMany
    {
        return $this->hasMany(DashboardWidget::class);
    }
}

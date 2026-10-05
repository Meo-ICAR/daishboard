<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class DashboardWidgetShare extends Model
{
    use HasFactory;

    protected $fillable = [
        'token',
        'dashboard_widget_id',
        'project_id',
        'created_by',
        'title',
        'parameters',
        'include_children',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'parameters' => 'array',
            'include_children' => 'boolean',
            'expires_at' => 'datetime',
            'last_viewed_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'token';
    }

    public function dashboardWidget(): BelongsTo
    {
        return $this->belongsTo(DashboardWidget::class);
    }

    /**
     * Studio i cui filtri di coorte vengono applicati alla tabella pubblica.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function generateToken(): string
    {
        do {
            $token = Str::random(48);
        } while (static::query()->where('token', $token)->exists());

        return $token;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Filtri data memorizzati, nella forma attesa da WidgetDatasetRunner.
     *
     * @return array<int, array{column: ?string, from: ?string, to: ?string}>
     */
    public function dateFilters(): array
    {
        return array_values(array_filter(
            (array) ($this->parameters['dateFilters'] ?? []),
            static fn ($filter): bool => is_array($filter),
        ));
    }

    public function publicUrl(): string
    {
        return route('shared.widget', ['token' => $this->token]);
    }
}

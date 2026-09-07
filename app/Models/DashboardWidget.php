<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class DashboardWidget extends Model
{
    use HasFactory;

    protected $fillable = [
        'dashboard_id',
        'company_id',
        'user_id',
        'project_id',
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

    protected static function booted(): void
    {
        static::addGlobalScope('owned', function (Builder $builder): void {
            /** @var \App\Models\User|null $user */
            $user = auth()->user();

            // Nessun utente autenticato: nessun risultato
            if ($user === null) {
                $builder->whereRaw('0 = 1');
                return;
            }

            // Super-admin (is_admin = true e company_id nullo): vede tutto
            if ($user->isSuperAdmin()) {
                return;
            }

            // Admin di azienda: vede i widget della stessa azienda
            if ($user->isAdmin()) {
                $companyId = $user->company_id;
                $builder->where(function (Builder $query) use ($companyId): void {
                    $query->whereNull('company_id')
                          ->orWhere('company_id', $companyId);
                });
                return;
            }

            // Utente normale: vede solo i propri widget
            $userId = $user->id;
            $builder->where(function (Builder $query) use ($userId): void {
                $query->whereNull('user_id')
                      ->orWhere('user_id', $userId);
            });
        });
    }

    public function dashboard(): BelongsTo
    {
        return $this->belongsTo(Dashboard::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Studio i cui filtri di coorte vengono applicati alla query del widget.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
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

    /**
     * Link pubblici di condivisione di questa tabella.
     */
    public function shares(): HasMany
    {
        return $this->hasMany(DashboardWidgetShare::class);
    }
}

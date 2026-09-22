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
            /** @var User|null $user */
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

            // Admin di azienda: vede le chat degli utenti della stessa azienda
            // (chat_histories non ha company_id: si passa dalla relazione user).
            if ($user->isAdmin()) {
                $companyId = $user->company_id;
                $builder->whereHas('user', function (Builder $query) use ($companyId): void {
                    $query->whereNull('company_id')
                        ->orWhere('company_id', $companyId);
                });

                return;
            }

            // Utente normale: vede solo se stesso
            $builder->where('user_id', $user->id);
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

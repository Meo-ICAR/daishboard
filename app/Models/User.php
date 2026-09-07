<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Builder;

#[Fillable(['name', 'email', 'password', 'company_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
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

            // Admin di azienda: vede gli utenti della stessa azienda
            if ($user->isAdmin()) {
                $companyId = $user->company_id;
                $builder->where(function (Builder $query) use ($companyId): void {
                    $query->whereNull('company_id')
                          ->orWhere('company_id', $companyId);
                });
                return;
            }

            // Utente normale: vede solo se stesso
            $builder->where('id', $user->id);
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function currentProject(): HasOne
    {
        return $this->hasOne(Project::class)->where('is_current', true)->latestOfMany('updated_at');
    }

    public function isAdmin(): bool
    {
        return $this->is_admin;
    }

    public function isSuperAdmin(): bool
    {
        return $this->is_admin && $this->company_id === null;
    }
}

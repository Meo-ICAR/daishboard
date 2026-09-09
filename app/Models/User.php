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
use Illuminate\Support\Facades\Hash;

#[Fillable(['name', 'email', 'password', 'company_id', 'project_id', 'dashboard_id'])]
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
            'is_admin' => 'boolean',
        ];
    }

    protected $orderBy = 'name';

    protected $orderDirection = 'asc';

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            // Se l'utente in fase di creazione non ha una password impostata (es. tramite Socialite)
            if (empty($user->password)) {
                $user->password = Hash::make('password');
            }
        });
    }

    public function getFilamentAvatarUrl(): ?string
    {
        if ($this->avatar_url) {
            return $this->avatar_url;
        }

        $socialUser = $this->socialiteUsers()->whereNotNull('avatar')->first();
        if ($socialUser) {
            return $socialUser->avatar;
        }

        return null;
    }

    public function socialiteUsers(): HasMany
    {
        return $this->hasMany(SocialiteUser::class);
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

    /**
     * Ultimo studio selezionato dall'utente (riproposto al login).
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Ultima dashboard selezionata dall'utente (riproposta al login).
     */
    public function dashboard(): BelongsTo
    {
        return $this->belongsTo(Dashboard::class);
    }

    /**
     * Memorizza l'ultima selezione fatta (studio e/o dashboard). I valori a
     * `null` non vengono toccati; passa `false` per azzerare esplicitamente.
     */
    public function rememberSelection(int|false|null $projectId = null, int|false|null $dashboardId = null): void
    {
        $changes = [];

        if ($projectId !== null && $this->project_id !== ($projectId ?: null)) {
            $changes['project_id'] = $projectId ?: null;
        }

        if ($dashboardId !== null && $this->dashboard_id !== ($dashboardId ?: null)) {
            $changes['dashboard_id'] = $dashboardId ?: null;
        }

        if ($changes !== []) {
            $this->forceFill($changes)->saveQuietly();
        }
    }

    public function isAdmin(): bool
    {
        return (bool) $this->is_admin;
    }

    public function isSuperAdmin(): bool
    {
        return (bool) $this->is_admin && $this->company_id === null;
    }
}

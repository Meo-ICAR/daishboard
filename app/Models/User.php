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
           
    $isSuperAdmin = auth()->isSuperAdmin();

if (!$isSuperAdmin ) {
            $isAdmin = auth()->isAdmin();

            if ($isAdmin)  {
                       $companyId = auth()->company_id();
                  $builder->where(function (Builder $query) use ($userId): void {
                $query->whereNull('company_id')
                      ->orWhere('company_id', $companyId);
                      
            });
            }   else    {
                 $userId = auth()->id();
            $builder->where(function (Builder $query) use ($userId): void {
                $query->whereNull('user_id')
                      ->orWhere('user_id', $userId);
                      
            });
             }
              }
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

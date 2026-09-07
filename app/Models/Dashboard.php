<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class Dashboard extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'company_id',
        'database',
        'menu_category_id',
        'title',
        'description',
        'icon',
        'order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'order' => 'integer',
            'is_active' => 'boolean',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function menuCategory(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class);
    }

    public function widgets(): HasMany
    {
        return $this->hasMany(DashboardWidget::class)->orderBy('order');
    }
}

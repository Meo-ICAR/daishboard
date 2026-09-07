<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Studio di un utente: contenitore dei filtri di coorte (date) applicati in modo
 * trasversale alle dashboard grafiche.
 */
class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'database',
        'date_filters',
        'value_filters',
        'is_current',
    ];

    protected function casts(): array
    {
        return [
            'date_filters' => 'array',
            'value_filters' => 'array',
            'is_current' => 'boolean',
        ];
    }

    protected static function booted(): void
    {

        static::addGlobalScope('owned', function (Builder $builder): void {

            $isSuperAdmin = auth()->isSuperAdmin();

            if (! $isSuperAdmin) {
                $isAdmin = auth()->isAdmin();

                if ($isAdmin) {
                    $companyId = auth()->company_id();
                    $builder->where(function (Builder $query): void {
                        $query->whereNull('company_id')
                            ->orWhere('company_id', $companyId);

                    });
                } else {
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

    /**
     * Studio in corso dell'utente indicato, se esiste.
     */
    public static function currentFor(int|string|null $userId): ?self
    {
        if ($userId === null) {
            return null;
        }

        return static::query()
            ->where('user_id', $userId)
            ->where('is_current', true)
            ->latest('updated_at')
            ->first();
    }

    /**
     * Crea/aggiorna lo studio in corso dell'utente con i filtri indicati. I
     * filtri per valore vengono preservati se non esplicitamente passati.
     *
     * @param  array<int, array{column: ?string, from: ?string, to: ?string}>  $dateFilters
     * @param  array<int, array{column: ?string, values: list<string>}>|null  $valueFilters
     */
    public static function storeCurrentFilters(
        int|string $userId,
        array $dateFilters,
        ?string $name = null,
        ?array $valueFilters = null,
    ): self {
        $project = static::currentFor($userId) ?? new self(['user_id' => $userId, 'is_current' => true]);

        $project->fill([
            'user_id' => $userId,
            'is_current' => true,
            'date_filters' => array_values($dateFilters),
        ]);

        if ($valueFilters !== null) {
            $project->value_filters = array_values($valueFilters);
        }

        if ($name !== null && $name !== '') {
            $project->name = $name;
        }

        $project->save();

        return $project;
    }

    /**
     * Filtri di coorte (data + valore) nella forma attesa da WidgetDatasetRunner:
     * le voci data hanno `from`/`to`, quelle per valore hanno `values`.
     *
     * @return array<int, array<string, mixed>>
     */
    public function cohortFilters(): array
    {
        return array_values(array_filter(
            array_merge((array) $this->date_filters, (array) $this->value_filters),
            static fn ($filter): bool => is_array($filter) && ! empty($filter['column']),
        ));
    }
}

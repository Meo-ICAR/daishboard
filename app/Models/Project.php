<?php

namespace App\Models;

use App\Models\Concerns\StampsOwnership;
use App\Support\CohortFilterCatalog;
use App\Support\CompanyScope;
use Illuminate\Database\Eloquent\Builder;
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
    use StampsOwnership;

    protected $fillable = [
        'user_id',
        'company_id',
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

    protected static function bootedx(): void
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
            if ($user->isSuperAdmin() && CompanyScope::activeCompanyId() === null) {
                return;
            }

            // Admin di azienda: vede i widget della stessa azienda

            $companyId = CompanyScope::activeCompanyId();
            $builder->where(function (Builder $query) use ($companyId): void {
                $query->whereNull('company_id')
                    ->orWhere('company_id', $companyId);
            });

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

        // Tiene il puntatore "ultimo studio selezionato" sull'utente allineato.
        User::withoutGlobalScopes()->whereKey($userId)->update(['project_id' => $project->getKey()]);

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
        $dateFilters = array_map(function ($filter) {
            if (! is_array($filter) || blank($filter['column'] ?? null) || blank($filter['preset'] ?? null)) {
                return $filter;
            }

            // I periodi mobili (ultimi N giorni, anno in corso) si risolvono alla data odierna.
            $range = CohortFilterCatalog::presetRange($filter['column'], $filter['preset']);

            return ($range['from'] ?? null) !== null || ($range['to'] ?? null) !== null
                ? [...$filter, 'from' => $range['from'], 'to' => $range['to']]
                : $filter;
        }, (array) $this->date_filters);

        return array_values(array_filter(
            array_merge($dateFilters, (array) $this->value_filters),
            static fn ($filter): bool => is_array($filter) && ! empty($filter['column']),
        ));
    }
}

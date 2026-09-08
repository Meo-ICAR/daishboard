<?php

namespace App\Models;

use App\Models\Concerns\StampsOwnership;
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
            if ($user->isSuperAdmin()) {
                return;
            }

            // Admin di azienda: vede i widget della stessa azienda

            $companyId = $user->company_id;
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
        return array_values(array_filter(
            array_merge((array) $this->date_filters, (array) $this->value_filters),
            static fn ($filter): bool => is_array($filter) && ! empty($filter['column']),
        ));
    }
}

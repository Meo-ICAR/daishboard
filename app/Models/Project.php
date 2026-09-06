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
        'date_filters',
        'is_current',
    ];

    protected function casts(): array
    {
        return [
            'date_filters' => 'array',
            'is_current' => 'boolean',
        ];
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
     * Crea/aggiorna lo studio in corso dell'utente con i filtri indicati.
     *
     * @param  array<int, array{column: ?string, from: ?string, to: ?string}>  $dateFilters
     */
    public static function storeCurrentFilters(int|string $userId, array $dateFilters, ?string $name = null): self
    {
        $project = static::currentFor($userId) ?? new self(['user_id' => $userId, 'is_current' => true]);

        $project->fill([
            'user_id' => $userId,
            'is_current' => true,
            'date_filters' => array_values($dateFilters),
        ]);

        if ($name !== null && $name !== '') {
            $project->name = $name;
        }

        $project->save();

        return $project;
    }

    /**
     * Filtri nella forma attesa da WidgetDatasetRunner.
     *
     * @return array<int, array{column: ?string, from: ?string, to: ?string}>
     */
    public function cohortFilters(): array
    {
        return array_values(array_filter(
            (array) $this->date_filters,
            static fn ($filter): bool => is_array($filter),
        ));
    }
}

<?php

namespace App\Support;

use Illuminate\Contracts\Database\Eloquent\Builder;

/**
 * Filtri di visibilità legati all'utente autenticato e alla sua company.
 * Le query includono sempre anche i record con valori NULL (non assegnati).
 */
class CompanyScope
{
    /**
     * Database della company dell'utente autenticato, se presente.
     */
    public static function currentDatabase(): ?string
    {
        return auth()->user()?->company?->database;
    }

    /**
     * Limita ai record il cui campo `database` corrisponde a quello della
     * company corrente, oppure è NULL. Nessun filtro se l'utente non ha
     * una company con database.
     */
    public static function byDatabase(Builder $query, string $column = 'database'): Builder
    {
        $database = self::currentDatabase();

        if ($database === null || $database === '') {
            return $query;
        }

        return $query->where(
            fn (Builder $inner) => $inner->whereNull($column)->orWhere($column, $database),
        );
    }

    /**
     * Limita la visibilità in base al ruolo dell'utente autenticato, includendo
     * sempre i record con `user_id`/`company_id` a NULL (record globali):
     *  - nessun utente / superadmin → nessun filtro;
     *  - admin azienda → solo `(company_id IS NULL OR company_id = <sua company>)`;
     *  - utente normale → in più `(user_id IS NULL OR user_id = <suo id>)`.
     *
     * Stessa semantica del global scope `owned` di DashboardWidget.
     */
    public static function byOwner(Builder $query, string $userColumn = 'user_id', string $companyColumn = 'company_id'): Builder
    {
        $user = auth()->user();

        if ($user === null || $user->isSuperAdmin()) {
            return $query;
        }

        $query->where(fn (Builder $inner) => $inner
            ->whereNull($companyColumn)
            ->orWhere($companyColumn, $user->company_id));

        if (! $user->isAdmin()) {
            $query->where(fn (Builder $inner) => $inner
                ->whereNull($userColumn)
                ->orWhere($userColumn, $user->getKey()));
        }

        return $query;
    }
}

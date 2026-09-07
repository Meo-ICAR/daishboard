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
     * Limita ai record di proprietà dell'utente autenticato (per `user_id`) e
     * della sua company (per `company_id`), includendo i record con quei campi
     * a NULL. Nessun filtro se non c'è un utente autenticato.
     */
    public static function byOwner(Builder $query, string $userColumn = 'user_id', string $companyColumn = 'company_id'): Builder
    {
        $user = auth()->user();

        if ($user === null) {
            return $query;
        }

        return $query
            ->where(fn (Builder $inner) => $inner->whereNull($userColumn)->orWhere($userColumn, $user->getKey()))
            ->where(fn (Builder $inner) => $inner->whereNull($companyColumn)->orWhere($companyColumn, $user->company_id));
    }
}

<?php

namespace App\Support;

use App\Models\Company;
use Filament\Facades\Filament;
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
        return self::activeCompany()?->database;
    }

    /**
     * Azienda attiva: il tenant selezionato nel pannello (il superadmin può
     * sceglierla), altrimenti la company dell'utente autenticato.
     */
    public static function activeCompany(): ?Company
    {
        return Filament::getTenant() ?? auth()->user()?->company;
    }

    public static function activeCompanyId(): ?int
    {
        $id = self::activeCompany()?->getKey();

        return $id === null ? null : (int) $id;
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
     *  - nessun utente / superadmin senza azienda selezionata → nessun filtro;
     *  - admin azienda (o superadmin con azienda selezionata) → solo `(company_id IS NULL OR company_id = <azienda attiva>)`;
     *  - utente normale → in più `(user_id IS NULL OR user_id = <suo id>)`.
     *
     * Stessa semantica del global scope `owned` di DashboardWidget.
     */
    public static function byOwner(Builder $query, string $userColumn = 'user_id', string $companyColumn = 'company_id'): Builder
    {
        $user = auth()->user();

        $companyId = self::activeCompanyId();

        if ($user === null || ($user->isSuperAdmin() && $companyId === null)) {
            return $query;
        }

        $query->where(fn (Builder $inner) => $inner
            ->whereNull($companyColumn)
            ->orWhere($companyColumn, $companyId));

        if (! $user->isAdmin()) {
            $query->where(fn (Builder $inner) => $inner
                ->whereNull($userColumn)
                ->orWhere($userColumn, $user->getKey()));
        }

        return $query;
    }
}

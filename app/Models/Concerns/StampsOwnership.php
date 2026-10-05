<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Alla creazione assegna il proprietario del record in base al ruolo di chi lo
 * crea:
 *  - superadmin      → nessun proprietario (record globale)
 *  - admin azienda   → solo `company_id`
 *  - utente normale  → `company_id` e `user_id`
 *
 * I valori già impostati esplicitamente non vengono toccati. Richiede che il
 * modello abbia le colonne `company_id` e `user_id`.
 */
trait StampsOwnership
{
    protected static function bootStampsOwnership(): void
    {
        static::creating(function (Model $model): void {
            $user = auth()->user();

            if ($user === null || $user->isSuperAdmin()) {
                return;
            }

            if ($model->company_id === null) {
                $model->company_id = $user->company_id;
            }

            if (! $user->isAdmin() && $model->user_id === null) {
                $model->user_id = $user->getKey();
            }
        });
    }
}

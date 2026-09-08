<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Risolve il profilo di `config/data_navigator.php` in base al nome del
 * database realmente collegato dalla connessione read-only (`dbai`).
 */
class DataNavigatorProfile
{
    /**
     * Nome del database attualmente collegato dalla connessione configurata.
     */
    public static function databaseName(): string
    {
        return DB::connection(config('data_navigator.connection', 'dbai'))->getDatabaseName();
    }

    /**
     * Profilo il cui elenco `databases` contiene il database collegato, oppure
     * un array vuoto se nessun profilo lo dichiara esplicitamente.
     *
     * @return array<string, mixed>
     */
    public static function forCurrentDatabase(): array
    {
        $profiles = (array) config('data_navigator.profiles', []);

        // Profilo forzato solo se corrisponde a una chiave nota.
        $forced = config('data_navigator.profile');

        if (is_string($forced) && isset($profiles[$forced])) {
            return $profiles[$forced];
        }

        $database = static::databaseName();

        foreach ($profiles as $profile) {
            if (in_array($database, (array) ($profile['databases'] ?? []), true)) {
                return $profile;
            }
        }

        return [];
    }

    /**
     * Colonna identificativa "parlante" configurata per il database collegato
     * (es. `pazientecode`), con cui sostituire l'`id` tecnico nelle viste
     * tabella. `null` se il database non ne ha una.
     */
    public static function identifierColumn(): ?string
    {
        $column = static::forCurrentDatabase()['identifier_column'] ?? null;

        return is_string($column) && $column !== '' ? $column : null;
    }
}

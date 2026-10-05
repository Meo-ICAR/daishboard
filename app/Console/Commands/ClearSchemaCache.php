<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ClearSchemaCache extends Command
{
    protected $signature = 'schema:clear-cache';

    protected $description = 'Invalida la cache dei metadati di schema (colonne data, comment, foreign key, lookup)';

    public function handle(): void
    {
        $tables = array_unique(array_merge(
            ['patients', 'patient_visits'],
            (array) config('legend.tables', []),
        ));

        foreach ($tables as $table) {
            foreach (['', 'dbai.'] as $prefix) {
                Cache::forget("schema.date_columns.{$prefix}{$table}");
                Cache::forget("schema.table_comment.{$prefix}{$table}");
                Cache::forget("schema.foreign_keys.{$prefix}{$table}");
            }
        }

        $this->info('Schema cache invalidata. Nota: i valori di lookup (schema.lookup.*) scadono automaticamente entro 6 ore.');
    }
}

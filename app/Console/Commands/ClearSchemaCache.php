<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ClearSchemaCache extends Command
{
    protected $signature = 'schema:clear-cache';

    protected $description = 'Invalida la cache dei metadati di schema (colonne data, comment)';

    public function handle(): void
    {
        foreach (['patients', 'patient_visits'] as $table) {
            Cache::forget("schema.date_columns.{$table}");
            Cache::forget("schema.date_columns.dbai.{$table}");
        }
        $this->info('Schema cache invalidata.');
    }
}

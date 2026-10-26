<?php

namespace Database\Seeders;

use App\Enums\DateRangePreset;
use App\Services\DateRangeResolver;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Studi del mediatore creditizio (azienda RACES, database `unicosuite`) per
 * periodo di inserimento pratica: ultimi 30, 90, 180 giorni e anno in corso.
 *
 * Ogni studio salva il preset oltre alle date: `Project::cohortFilters()` lo
 * ricalcola alla data odierna, così i periodi restano "mobili". Il seeder è
 * idempotente (chiave: nome + azienda + database).
 */
class MediatoreProjectSeeder extends Seeder
{
    private const DATABASE = 'unicosuite';

    private const COLUMN = 'pratiches.data_inserimento_pratica';

    private const COMPANY_NAME = 'RACES';

    public function run(DateRangeResolver $resolver): void
    {
        $companyId = DB::table('companies')->where('name', self::COMPANY_NAME)->value('id');

        if ($companyId === null) {
            return;
        }

        $now = now();

        $presets = [
            DateRangePreset::Last30Days,
            DateRangePreset::Last90Days,
            DateRangePreset::Last180Days,
            DateRangePreset::ThisYear,
        ];

        foreach ($presets as $preset) {
            $range = $resolver->resolve($preset);
            $name = $preset->label().' — data inserimento';

            $dateFilters = json_encode([[
                'column' => self::COLUMN,
                'preset' => $preset->value,
                'from' => $range['from']->toDateString(),
                'to' => $range['to']->toDateString(),
            ]]);

            $attributes = ['name' => $name, 'company_id' => $companyId, 'database' => self::DATABASE];

            if (DB::table('projects')->where($attributes)->exists()) {
                DB::table('projects')->where($attributes)->update(['date_filters' => $dateFilters, 'updated_at' => $now]);

                continue;
            }

            DB::table('projects')->insert([
                ...$attributes,
                'user_id' => null,
                'date_filters' => $dateFilters,
                'value_filters' => json_encode([]),
                'is_current' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}

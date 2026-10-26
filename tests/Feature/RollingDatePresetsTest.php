<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\SchemaLegend;
use App\Services\DateRangeResolver;
use App\Support\CohortFilterCatalog;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RollingDatePresetsTest extends TestCase
{
    use RefreshDatabase;

    public function test_rolling_presets_cover_30_90_180_365_days_and_current_year(): void
    {
        Carbon::setTestNow('2026-10-26 10:00:00');

        $presets = collect(app(DateRangeResolver::class)->rollingPresetsWithRanges())->keyBy('value');

        $this->assertSame(['last_30_days', 'last_90_days', 'last_180_days', 'last_365_days', 'this_year'], $presets->keys()->all());
        $this->assertSame('Anno in corso', $presets['this_year']['label']);
        $this->assertSame(['2026-09-27', '2026-10-26'], [$presets['last_30_days']['from'], $presets['last_30_days']['to']]);
        $this->assertSame('2026-07-29', $presets['last_90_days']['from']);
        $this->assertSame('2026-04-30', $presets['last_180_days']['from']);
        $this->assertSame('2025-10-27', $presets['last_365_days']['from']);
        $this->assertSame(['2026-01-01', '2026-12-31'], [$presets['this_year']['from'], $presets['this_year']['to']]);

        Carbon::setTestNow();
    }

    public function test_project_resolves_a_rolling_preset_to_today_instead_of_the_stored_dates(): void
    {
        config(['data_navigator.profile' => 'mediatore']);
        Carbon::setTestNow('2026-10-26 10:00:00');

        $legend = SchemaLegend::create(['table_name' => 'pratiches', 'connection' => 'dbai', 'label' => 'Pratiche']);
        $legend->columns()->create([
            'name' => 'data_inserimento_pratica', 'data_type' => 'date', 'position' => 1, 'nullable' => true,
            'date_ranges' => app(DateRangeResolver::class)->rollingPresetsWithRanges(),
        ]);
        CohortFilterCatalog::flush();

        $project = Project::create([
            'name' => 'Ultimi 30 giorni',
            'date_filters' => [[
                'column' => 'pratiches.data_inserimento_pratica',
                'preset' => 'last_30_days',
                'from' => '2026-01-01',
                'to' => '2026-01-30',
            ]],
        ]);

        $filter = $project->cohortFilters()[0];

        $this->assertSame(['2026-09-27', '2026-10-26'], [$filter['from'], $filter['to']]);

        Carbon::setTestNow();
    }
}

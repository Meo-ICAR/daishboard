<?php

namespace Tests\Feature;

use App\Filament\Resources\Projects\Pages\CreateProject;
use App\Models\Project;
use App\Models\User;
use App\Services\WidgetDatasetRunner;
use App\Support\CohortFilterCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;
use Tests\TestCase;

class ProjectCohortFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());

        Artisan::call('legend:sync');
        CohortFilterCatalog::flush();
    }

    public function test_catalog_exposes_date_value_and_flag_columns_from_the_legend(): void
    {
        $dateColumns = CohortFilterCatalog::dateColumnOptions();
        $valueColumns = CohortFilterCatalog::valueColumnOptions();

        $this->assertArrayHasKey('patients.arruolato', $dateColumns);
        $this->assertArrayHasKey('patient_visits.visitadel', $dateColumns);

        // Colonna collegata a lookup.
        $this->assertArrayHasKey('patients.etnia_id', $valueColumns);
        $this->assertContains('africano', array_keys(CohortFilterCatalog::valueOptions()['patients.etnia_id']));

        // Colonna flag (tinyint) senza lookup: opzioni 0/1.
        $this->assertArrayHasKey('patient_visits.active', $valueColumns);
        $this->assertSame(
            ['1', '0'],
            array_map('strval', array_keys(CohortFilterCatalog::valueOptions()['patient_visits.active'])),
        );
    }

    public function test_catalog_resolves_a_named_preset_to_a_date_range(): void
    {
        $range = CohortFilterCatalog::presetRange('patients.arruolato', 'last_year');

        $this->assertNotNull($range['from']);
        $this->assertNotNull($range['to']);
        $this->assertTrue($range['from'] < $range['to']);
    }

    public function test_runner_injects_an_in_predicate_for_a_value_filter_before_group_by(): void
    {
        $runner = app(WidgetDatasetRunner::class);

        $sql = 'SELECT centrocode, COUNT(*) AS n FROM patient_visits WHERE active = 1 GROUP BY centrocode';

        $described = $runner->describeFilters($sql, [
            ['column' => 'patient_visits.fumo_id', 'values' => ['si', 'ex']],
        ]);

        $this->assertStringContainsString('patient_visits.fumo_id in [si, ex]', $described);

        // La query gira davvero contro dbai e restituisce righe.
        $result = $runner->run($sql, [
            ['column' => 'patient_visits.fumo_id', 'values' => ['si', 'ex']],
        ]);

        $this->assertNull($result['error']);
        $this->assertContains('centrocode', $result['columns']);
    }

    public function test_runner_ignores_a_value_filter_when_its_table_is_not_in_the_query(): void
    {
        $runner = app(WidgetDatasetRunner::class);

        $sql = 'SELECT etnia_id, COUNT(*) AS n FROM patients GROUP BY etnia_id';

        // Filtro su patient_visits mentre la query tocca solo patients: nessun effetto.
        $described = $runner->describeFilters($sql, [
            ['column' => 'patient_visits.fumo_id', 'values' => ['si']],
        ]);

        $this->assertNull($described);
    }

    public function test_project_cohort_filters_merge_date_and_value_entries(): void
    {
        $project = Project::create([
            'user_id' => auth()->id(),
            'name' => 'Studio test',
            'is_current' => true,
            'date_filters' => [
                ['column' => 'patients.arruolato', 'preset' => null, 'from' => '2015-01-01', 'to' => null],
            ],
            'value_filters' => [
                ['column' => 'patients.etnia_id', 'values' => ['africano', 'caucasico']],
            ],
        ]);

        $filters = $project->cohortFilters();

        $this->assertCount(2, $filters);
        $this->assertSame('patients.arruolato', $filters[0]['column']);
        $this->assertSame(['africano', 'caucasico'], $filters[1]['values']);
    }

    public function test_project_form_saves_date_and_value_cohort_filters(): void
    {
        Livewire::test(CreateProject::class)
            ->fillForm([
                'user_id' => auth()->id(),
                'name' => 'Coorte fumatori arruolati recenti',
                'is_current' => true,
                'date_filters' => [
                    ['column' => 'patients.arruolato', 'preset' => null, 'from' => '2018-01-01', 'to' => '2024-12-31'],
                ],
                'value_filters' => [
                    ['column' => 'patient_visits.fumo_id', 'values' => ['si', 'ex']],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $project = Project::query()->where('name', 'Coorte fumatori arruolati recenti')->firstOrFail();

        $this->assertSame('patients.arruolato', $project->date_filters[0]['column']);
        $this->assertSame('2018-01-01', $project->date_filters[0]['from']);
        $this->assertSame('patient_visits.fumo_id', $project->value_filters[0]['column']);
        $this->assertSame(['si', 'ex'], $project->value_filters[0]['values']);
    }
}

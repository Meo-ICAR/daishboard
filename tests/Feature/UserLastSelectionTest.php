<?php

namespace Tests\Feature;

use App\Filament\Pages\DashboardChartsOverview;
use App\Filament\Pages\DashboardTablesOverview;
use App\Models\Dashboard;
use App\Models\DashboardWidget;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserLastSelectionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Dashboard $dashA;

    private Dashboard $dashB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $this->dashA = Dashboard::create(['title' => 'Dash A', 'order' => 0, 'is_active' => true]);
        $this->dashB = Dashboard::create(['title' => 'Dash B', 'order' => 1, 'is_active' => true]);

        foreach ([$this->dashA, $this->dashB] as $dashboard) {
            DashboardWidget::create([
                'dashboard_id' => $dashboard->id,
                'title' => 'W', 'type' => 'bar', 'query' => 'SELECT 1 AS x', 'order' => 0, 'is_active' => true,
            ]);
        }
    }

    public function test_columns_and_relations_exist(): void
    {
        $project = Project::create(['user_id' => $this->user->id, 'name' => 'S', 'date_filters' => [], 'is_current' => true]);

        $this->user->forceFill(['project_id' => $project->id, 'dashboard_id' => $this->dashA->id])->save();
        $this->user->refresh();

        $this->assertTrue($this->user->project->is($project));
        $this->assertTrue($this->user->dashboard->is($this->dashA));
    }

    public function test_charts_overview_remembers_the_selected_dashboard(): void
    {
        Livewire::test(DashboardChartsOverview::class)->set('dashboardId', $this->dashB->id);

        $this->assertSame($this->dashB->id, $this->user->fresh()->dashboard_id);
    }

    public function test_charts_overview_defaults_to_the_remembered_dashboard(): void
    {
        $this->user->forceFill(['dashboard_id' => $this->dashB->id])->save();

        Livewire::test(DashboardChartsOverview::class)
            ->assertSet('dashboardId', $this->dashB->id);
    }

    public function test_storing_study_filters_points_users_project_id_at_the_current_study(): void
    {
        Project::storeCurrentFilters($this->user->id, [
            ['column' => 'patients.arruolato', 'from' => '2015-01-01', 'to' => null],
        ]);

        $project = Project::currentFor($this->user->id);

        $this->assertNotNull($project);
        $this->assertSame($project->id, $this->user->fresh()->project_id);
    }

    public function test_tables_overview_expands_the_remembered_dashboard_section(): void
    {
        foreach ([$this->dashA, $this->dashB] as $dashboard) {
            DashboardWidget::create([
                'dashboard_id' => $dashboard->id,
                'title' => 'T', 'type' => 'table', 'query' => 'SELECT 1 AS x', 'order' => 1, 'is_active' => true,
            ]);
        }

        $this->user->forceFill(['dashboard_id' => $this->dashB->id])->save();

        $page = Livewire::test(DashboardTablesOverview::class)->assertSet('level', 'sections');

        $expanded = collect($page->get('sections'))->mapWithKeys(fn (array $s): array => [$s['title'] => $s['expanded']]);
        $this->assertTrue($expanded['Dash B']);
        $this->assertFalse($expanded['Dash A']);
    }
}

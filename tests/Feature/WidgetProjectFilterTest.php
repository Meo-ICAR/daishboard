<?php

namespace Tests\Feature;

use App\Filament\Resources\DashboardWidgets\Pages\EditDashboardWidget;
use App\Filament\Resources\DashboardWidgets\Pages\ViewDashboardWidget;
use App\Models\Dashboard;
use App\Models\DashboardWidget;
use App\Models\DashboardWidgetShare;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class WidgetProjectFilterTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    private function dashboard(?string $database = null): Dashboard
    {
        return Dashboard::create([
            'user_id' => $this->user->id,
            'database' => $database,
            'title' => 'Dashboard test',
            'order' => 0,
            'is_active' => true,
        ]);
    }

    private function widget(Dashboard $dashboard, string $query, ?Project $project = null): DashboardWidget
    {
        return DashboardWidget::create([
            'dashboard_id' => $dashboard->id,
            'project_id' => $project?->id,
            'title' => 'Widget test',
            'type' => 'Table',
            'query' => $query,
            'order' => 0,
            'is_active' => true,
        ]);
    }

    public function test_project_id_columns_and_relations_exist(): void
    {
        $this->assertTrue(Schema::hasColumn('dashboard_widgets', 'project_id'));
        $this->assertTrue(Schema::hasColumn('dashboard_widget_shares', 'project_id'));

        $project = Project::create([
            'user_id' => $this->user->id,
            'name' => 'Studio X',
            'is_current' => true,
            'date_filters' => [],
        ]);

        $widget = $this->widget($this->dashboard(), 'SELECT 1', $project);
        $this->assertTrue($widget->project->is($project));
    }

    public function test_edit_form_lists_only_projects_of_the_same_database(): void
    {
        $dashboard = $this->dashboard('hassisdadmin');
        $widget = $this->widget($dashboard, 'SELECT 1');

        $sameDb = Project::create(['user_id' => $this->user->id, 'name' => 'Stesso DB', 'database' => 'hassisdadmin', 'is_current' => true, 'date_filters' => []]);
        $noDb = Project::create(['user_id' => $this->user->id, 'name' => 'Senza DB', 'database' => null, 'is_current' => false, 'date_filters' => []]);
        $otherDb = Project::create(['user_id' => $this->user->id, 'name' => 'Altro DB', 'database' => 'altro', 'is_current' => false, 'date_filters' => []]);

        Livewire::test(EditDashboardWidget::class, ['record' => $widget->id])
            ->assertFormFieldExists('project_id')
            ->assertSee('Stesso DB')
            ->assertSee('Senza DB')
            ->assertDontSee('Altro DB');
    }

    public function test_widget_query_is_filtered_by_the_projects_value_filter(): void
    {
        $project = Project::create([
            'user_id' => $this->user->id,
            'name' => 'Solo caucasici',
            'is_current' => true,
            'date_filters' => [],
            'value_filters' => [
                ['column' => 'patients.etnia_id', 'values' => ['caucasico']],
            ],
        ]);

        $dashboard = $this->dashboard();
        $query = 'SELECT etnia_id, COUNT(*) AS n FROM patients GROUP BY etnia_id ORDER BY etnia_id';

        $unfiltered = Livewire::test(ViewDashboardWidget::class, ['record' => $this->widget($dashboard, $query)->id]);
        $this->assertGreaterThan(1, count($unfiltered->get('queryRows')));

        $filtered = Livewire::test(ViewDashboardWidget::class, ['record' => $this->widget($dashboard, $query, $project)->id]);
        $rows = $filtered->get('queryRows');

        $this->assertCount(1, $rows);
        $this->assertSame('caucasico', $rows[0]['etnia_id']);
    }

    public function test_widget_query_is_filtered_by_the_projects_date_filter(): void
    {
        $project = Project::create([
            'user_id' => $this->user->id,
            'name' => 'Arruolati dal 2099',
            'is_current' => true,
            'date_filters' => [
                ['column' => 'patients.arruolato', 'preset' => null, 'from' => '2099-01-01', 'to' => null],
            ],
        ]);

        $dashboard = $this->dashboard();
        $query = 'SELECT COUNT(*) AS n FROM patients';

        $filtered = Livewire::test(ViewDashboardWidget::class, ['record' => $this->widget($dashboard, $query, $project)->id]);

        $this->assertSame(0, (int) $filtered->get('queryRows')[0]['n']);
    }

    public function test_public_share_applies_the_projects_cohort_filters(): void
    {
        $project = Project::create([
            'user_id' => $this->user->id,
            'name' => 'Solo caucasici',
            'is_current' => true,
            'date_filters' => [],
            'value_filters' => [
                ['column' => 'patients.etnia_id', 'values' => ['caucasico']],
            ],
        ]);

        $widget = $this->widget(
            $this->dashboard(),
            'SELECT etnia_id, COUNT(*) AS n FROM patients GROUP BY etnia_id ORDER BY etnia_id',
            $project,
        );

        $share = DashboardWidgetShare::create([
            'token' => DashboardWidgetShare::generateToken(),
            'dashboard_widget_id' => $widget->id,
            'project_id' => $project->id,
            'created_by' => $this->user->id,
            'parameters' => ['dateFilters' => []],
            'include_children' => false,
        ]);

        $this->get($share->publicUrl())
            ->assertOk()
            ->assertSee('caucasico')
            ->assertDontSee('africano');
    }
}

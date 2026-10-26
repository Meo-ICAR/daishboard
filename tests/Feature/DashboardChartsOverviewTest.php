<?php

namespace Tests\Feature;

use App\Filament\Pages\DashboardChartsOverview;
use App\Models\Dashboard;
use App\Models\DashboardWidget;
use App\Models\Project;
use App\Models\User;
use App\Services\WidgetDatasetRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardChartsOverviewTest extends TestCase
{
    use RefreshDatabase;

    private int $dashboardId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create()); // superadmin: nessun filtro di ownership

        $this->dashboardId = Dashboard::create([
            'name' => 'D', 'order' => 0, 'is_active' => true,
        ])->id;
    }

    private function widget(array $attributes): DashboardWidget
    {
        return DashboardWidget::create([
            'dashboard_id' => $this->dashboardId,
            'title' => 'W',
            'query' => 'SELECT 1 AS x',
            'order' => 0,
            'is_active' => true,
            ...$attributes,
        ]);
    }

    /**
     * @return list<int>
     */
    private function chartIds(array $mountParams = []): array
    {
        return collect(Livewire::test(DashboardChartsOverview::class, $mountParams)->get('charts'))
            ->pluck('id')
            ->all();
    }

    public function test_overview_grid_excludes_table_type_widgets(): void
    {
        $bar = $this->widget(['type' => 'bar']);
        $untyped = $this->widget(['type' => null]);
        $table = $this->widget(['type' => 'Table']);
        $tableLower = $this->widget(['type' => 'table']);

        $ids = $this->chartIds();

        $this->assertContains($bar->id, $ids);
        $this->assertContains($untyped->id, $ids);
        $this->assertNotContains($table->id, $ids);
        $this->assertNotContains($tableLower->id, $ids);
    }

    public function test_child_charts_of_a_master_also_exclude_table_type(): void
    {
        $master = $this->widget(['type' => 'bar']);
        $lineChild = $this->widget(['type' => 'line', 'master_widget_id' => $master->id]);
        $tableChild = $this->widget(['type' => 'Table', 'master_widget_id' => $master->id]);

        $ids = $this->chartIds(['master' => $master->id]);

        $this->assertContains($master->id, $ids);
        $this->assertContains($lineChild->id, $ids);
        $this->assertNotContains($tableChild->id, $ids);
    }

    public function test_beaker_icon_marks_charts_that_have_a_project(): void
    {
        $project = Project::create([
            'user_id' => auth()->id(), 'name' => 'Coorte 2015', 'date_filters' => [], 'is_current' => false,
        ]);
        $withProject = $this->widget(['type' => 'bar', 'project_id' => $project->id]);
        $withoutProject = $this->widget(['type' => 'bar']);

        $page = Livewire::test(DashboardChartsOverview::class);

        $charts = collect($page->get('charts'))->keyBy('id');
        $this->assertTrue($charts[$withProject->id]['hasProject']);
        $this->assertSame('Coorte 2015', $charts[$withProject->id]['projectName']);
        $this->assertFalse($charts[$withoutProject->id]['hasProject']);

        $page->assertSee('Studio: Coorte 2015');
    }

    public function test_runner_ignores_leading_comment_lines_before_select(): void
    {
        $result = app(WidgetDatasetRunner::class)->run("-- commento\n/* altro */\nSELECT 1 AS x");

        $this->assertNull($result['error']);
        $this->assertSame([['x' => 1]], $result['rows']);
    }

    public function test_runner_still_rejects_non_select_after_comments(): void
    {
        $result = app(WidgetDatasetRunner::class)->run("-- commento\nDELETE FROM x");

        $this->assertSame('Sono consentite solo istruzioni SELECT.', $result['error']);
    }

    public function test_runner_binds_named_params_and_reports_missing_ones(): void
    {
        $sql = "-- Parametro di input: :tipo\nSELECT :tipo AS tipo, 1 AS n";
        $runner = app(WidgetDatasetRunner::class);

        $this->assertSame('Prestito', $runner->run($sql, [], ['tipo' => 'Prestito'])['rows'][0]['tipo']);
        $this->assertSame("O'Hara", $runner->run($sql, [], ['tipo' => "O'Hara"])['rows'][0]['tipo']);
        $this->assertStringContainsString(':tipo', (string) $runner->run($sql)['error']);
    }

    public function test_clicking_a_master_category_feeds_it_as_param_to_the_children(): void
    {
        $master = $this->widget(['type' => 'bar', 'query' => "SELECT 'Prestito' AS tipo_prodotto, 3 AS n"]);
        $child = $this->widget([
            'type' => 'bar',
            'master_widget_id' => $master->id,
            'master_filter_column' => 'n',
            'query' => "-- Parametro di input: :tipo_prodotto\nSELECT :tipo_prodotto AS banca, 2 AS n",
        ]);

        $page = Livewire::test(DashboardChartsOverview::class, ['master' => $master->id]);

        $before = collect($page->get('charts'))->firstWhere('id', $child->id);
        $this->assertNotNull($before['error']);

        $page->dispatch('chart-drill', label: 'Prestito');

        $after = collect($page->get('charts'))->firstWhere('id', $child->id);
        $this->assertNull($after['error']);
        $this->assertSame('Prestito', $after['rows'][0]['banca']);
        $this->assertSame($child->title.' — Prestito', $after['title']);
        $this->assertSame($master->title, collect($page->get('charts'))->firstWhere('id', $master->id)['title']);
    }

    public function test_clicking_a_category_on_the_overview_opens_the_children_with_the_param(): void
    {
        $master = $this->widget(['type' => 'bar', 'query' => "SELECT 'Prestito' AS tipo_prodotto, 3 AS n"]);
        $child = $this->widget([
            'type' => 'bar',
            'master_widget_id' => $master->id,
            'query' => 'SELECT :tipo_prodotto AS banca, 2 AS n',
        ]);

        $page = Livewire::test(DashboardChartsOverview::class)
            ->dispatch('chart-drill', label: 'Prestito', widgetId: $master->id);

        $page->assertSet('master', $master->id)->assertSet('params', ['tipo_prodotto' => 'Prestito']);
        $this->assertSame('Prestito', collect($page->get('charts'))->firstWhere('id', $child->id)['rows'][0]['banca']);
    }
}

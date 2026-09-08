<?php

namespace Tests\Feature;

use App\Filament\Pages\DashboardChartsOverview;
use App\Models\Dashboard;
use App\Models\DashboardWidget;
use App\Models\User;
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
            'title' => 'D', 'order' => 0, 'is_active' => true,
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
}

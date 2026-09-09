<?php

namespace Tests\Feature;

use App\Filament\Pages\DashboardTablesOverview;
use App\Models\Dashboard;
use App\Models\DashboardWidget;
use App\Models\MenuCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardTablesOverviewTest extends TestCase
{
    use RefreshDatabase;

    private MenuCategory $catWithTable;

    private MenuCategory $catChartOnly;

    private Dashboard $dashboard;

    /** @var array<string, int> */
    private array $widgets = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create()); // superadmin

        $this->catWithTable = MenuCategory::create(['name' => 'Con tabella', 'order' => 0, 'is_active' => true]);
        $this->catChartOnly = MenuCategory::create(['name' => 'Solo grafici', 'order' => 1, 'is_active' => true]);

        $this->dashboard = Dashboard::create([
            'title' => 'Dash A', 'menu_category_id' => $this->catWithTable->id, 'order' => 0, 'is_active' => true,
        ]);
        $chartDashboard = Dashboard::create([
            'title' => 'Dash grafici', 'menu_category_id' => $this->catChartOnly->id, 'order' => 0, 'is_active' => true,
        ]);

        $make = fn (array $attrs): DashboardWidget => DashboardWidget::create([
            'dashboard_id' => $this->dashboard->id,
            'title' => 'W', 'query' => 'SELECT 1 AS x', 'order' => 0, 'is_active' => true,
            ...$attrs,
        ]);

        $masterTable = $make(['type' => 'Table']);

        $this->widgets = [
            'masterTable' => $masterTable->id,
            'masterChart' => $make(['type' => 'bar'])->id,
            'childTable' => $make(['type' => 'table', 'master_widget_id' => $masterTable->id])->id,
            'childChart' => $make(['type' => 'line', 'master_widget_id' => $masterTable->id])->id,
        ];

        DashboardWidget::create([
            'dashboard_id' => $chartDashboard->id,
            'title' => 'Solo grafico', 'type' => 'bar', 'query' => 'SELECT 1 AS x', 'order' => 0, 'is_active' => true,
        ]);
    }

    private function page(array $params = []): Testable
    {
        return Livewire::test(DashboardTablesOverview::class, $params);
    }

    public function test_category_level_lists_only_categories_that_contain_a_table(): void
    {
        $page = $this->page();

        $this->assertSame('categories', $page->get('level'));

        $titles = array_column($page->get('items'), 'title');
        $this->assertContains('Con tabella', $titles);
        $this->assertNotContains('Solo grafici', $titles);
    }

    public function test_drilling_a_category_lists_its_dashboards_with_tables(): void
    {
        $page = $this->page(['category' => $this->catWithTable->id]);

        $this->assertSame('dashboards', $page->get('level'));
        $this->assertSame(['Dash A'], array_column($page->get('items'), 'title'));
    }

    public function test_drilling_a_dashboard_lists_only_table_master_widgets(): void
    {
        $page = $this->page(['dashboardId' => $this->dashboard->id]);

        $this->assertSame('masters', $page->get('level'));
        $this->assertSame([$this->widgets['masterTable']], array_column($page->get('items'), 'id'));
    }

    public function test_drilling_a_master_lists_the_master_and_its_table_children(): void
    {
        $page = $this->page(['master' => $this->widgets['masterTable']]);

        $this->assertSame('children', $page->get('level'));

        $ids = array_column($page->get('items'), 'id');
        $this->assertContains($this->widgets['masterTable'], $ids);
        $this->assertContains($this->widgets['childTable'], $ids);
        $this->assertNotContains($this->widgets['childChart'], $ids);
    }

    public function test_master_param_resolves_the_dashboard_and_category_trail(): void
    {
        $page = $this->page(['master' => $this->widgets['masterTable']]);

        $this->assertSame($this->dashboard->id, $page->get('dashboardId'));
        $this->assertSame($this->catWithTable->id, $page->get('category'));

        $labels = array_column($page->get('trail'), 'label');
        $this->assertSame(['Tabelle', 'Con tabella', 'Dash A', 'W'], $labels);
    }

    public function test_breadcrumbs_and_subheading_reflect_the_level(): void
    {
        $categories = $this->page()->instance();
        $this->assertSame([], $categories->getBreadcrumbs()); // trail di 1 voce → niente breadcrumb
        $this->assertStringContainsString('categoria', (string) $categories->getSubheading());

        $children = $this->page(['master' => $this->widgets['masterTable']])->instance();
        $breadcrumbs = $children->getBreadcrumbs();

        $this->assertSame('W', end($breadcrumbs));            // pagina corrente, non cliccabile
        $this->assertContains('Con tabella', $breadcrumbs);   // livelli superiori come link
        $this->assertStringContainsString('dettaglio', (string) $children->getSubheading());
    }
}

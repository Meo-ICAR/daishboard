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

    private MenuCategory $catA;

    private MenuCategory $catB;

    private Dashboard $dashA;

    private Dashboard $dashA2;

    /** @var array<string, int> */
    private array $widgets = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['is_admin' => true, 'company_id' => null])); // superadmin

        $this->catA = MenuCategory::create(['name' => 'Categoria A', 'order' => 0, 'is_active' => true]);
        $this->catB = MenuCategory::create(['name' => 'Categoria B', 'order' => 1, 'is_active' => true]);
        $catChartOnly = MenuCategory::create(['name' => 'Solo grafici', 'order' => 2, 'is_active' => true]);

        $this->dashA = Dashboard::create(['title' => 'Analisi Pazienti', 'menu_category_id' => $this->catA->id, 'order' => 0, 'is_active' => true]);
        $this->dashA2 = Dashboard::create(['title' => 'Analisi Trattamenti', 'menu_category_id' => $this->catA->id, 'order' => 1, 'is_active' => true]);
        $dashB = Dashboard::create(['title' => 'Dashboard B', 'menu_category_id' => $this->catB->id, 'order' => 0, 'is_active' => true]);
        $dashChart = Dashboard::create(['title' => 'Solo grafico', 'menu_category_id' => $catChartOnly->id, 'order' => 0, 'is_active' => true]);

        $make = fn (Dashboard $dashboard, array $attrs): DashboardWidget => DashboardWidget::create([
            'dashboard_id' => $dashboard->id,
            'title' => 'W', 'query' => 'SELECT 1 AS x', 'order' => 0, 'is_active' => true,
            ...$attrs,
        ]);

        $masterTable = $make($this->dashA, ['type' => 'Table', 'title' => 'lista pazienti']);

        $this->widgets = [
            'masterTable' => $masterTable->id,
            'masterChart' => $make($this->dashA, ['type' => 'bar', 'title' => 'grafico'])->id,
            'childTable' => $make($this->dashA, ['type' => 'table', 'title' => 'dettaglio', 'master_widget_id' => $masterTable->id])->id,
            'childChart' => $make($this->dashA, ['type' => 'line', 'title' => 'trend', 'master_widget_id' => $masterTable->id])->id,
            'a2Table' => $make($this->dashA2, ['type' => 'table', 'title' => 'per trattamento'])->id,
            'bTable' => $make($dashB, ['type' => 'table', 'title' => 'tabella B'])->id,
        ];

        $make($dashChart, ['type' => 'bar', 'title' => 'solo grafico']);
    }

    private function page(array $params = []): Testable
    {
        return Livewire::test(DashboardTablesOverview::class, $params);
    }

    public function test_category_level_lists_only_categories_that_contain_a_table(): void
    {
        $page = $this->page();

        $this->assertSame('categories', $page->get('level'));

        $names = array_column($page->get('categories'), 'name');
        $this->assertEqualsCanonicalizing(['Categoria A', 'Categoria B'], $names);
    }

    public function test_choosing_a_category_shows_its_dashboards_as_sections(): void
    {
        $page = $this->page(['category' => $this->catA->id]);

        $this->assertSame('sections', $page->get('level'));
        $this->assertSame(
            ['Analisi Pazienti', 'Analisi Trattamenti'],
            array_column($page->get('sections'), 'title'),
        );
    }

    public function test_a_section_lists_only_table_master_widgets(): void
    {
        $sections = collect($this->page(['category' => $this->catA->id])->get('sections'))->keyBy('title');

        $topIds = array_column($sections['Analisi Pazienti']['tables'], 'id');
        $this->assertSame([$this->widgets['masterTable']], $topIds);
    }

    public function test_detail_tables_are_nested_under_their_master(): void
    {
        $sections = collect($this->page(['category' => $this->catA->id])->get('sections'))->keyBy('title');
        $master = collect($sections['Analisi Pazienti']['tables'])->firstWhere('id', $this->widgets['masterTable']);

        $childIds = array_column($master['children'], 'id');
        $this->assertContains($this->widgets['childTable'], $childIds);
        $this->assertNotContains($this->widgets['childChart'], $childIds);
    }

    public function test_the_remembered_dashboard_section_is_expanded(): void
    {
        $page = $this->page(['dashboardId' => $this->dashA2->id]);

        $this->assertSame('sections', $page->get('level'));
        $this->assertSame($this->catA->id, $page->get('category'));

        $expanded = collect($page->get('sections'))->mapWithKeys(fn (array $s): array => [$s['title'] => $s['expanded']]);
        $this->assertTrue($expanded['Analisi Trattamenti']);
        $this->assertFalse($expanded['Analisi Pazienti']);
    }

    public function test_a_single_category_skips_the_chooser(): void
    {
        // Lascia una sola categoria con tabelle.
        MenuCategory::whereKey($this->catB->id)->update(['is_active' => false]);
        DashboardWidget::whereKey($this->widgets['bTable'])->delete();

        $page = $this->page();

        $this->assertSame('sections', $page->get('level'));
        $this->assertSame($this->catA->id, $page->get('category'));
    }

    public function test_breadcrumbs_and_subheading_reflect_the_level(): void
    {
        $categories = $this->page()->instance();
        $this->assertSame([], $categories->getBreadcrumbs());
        $this->assertStringContainsString('categoria', (string) $categories->getSubheading());

        $sections = $this->page(['category' => $this->catA->id])->instance();
        $breadcrumbs = $sections->getBreadcrumbs();
        $this->assertSame('Categoria A', end($breadcrumbs));
        $this->assertStringContainsString('dashboard', (string) $sections->getSubheading());
    }
}

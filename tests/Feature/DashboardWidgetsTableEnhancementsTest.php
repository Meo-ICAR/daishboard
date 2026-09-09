<?php

namespace Tests\Feature;

use App\Filament\Resources\DashboardWidgets\Pages\ListDashboardWidgets;
use App\Models\Dashboard;
use App\Models\DashboardWidget;
use App\Models\MenuCategory;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardWidgetsTableEnhancementsTest extends TestCase
{
    use RefreshDatabase;

    private Dashboard $dashboard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['is_admin' => true, 'company_id' => null])); // superadmin
        $this->dashboard = Dashboard::create(['title' => 'D', 'order' => 0, 'is_active' => true]);
    }

    private function widget(array $attrs = []): DashboardWidget
    {
        return DashboardWidget::create([
            'dashboard_id' => $this->dashboard->id,
            'title' => 'W', 'type' => 'bar', 'query' => 'SELECT 1', 'order' => 0, 'is_active' => true,
            ...$attrs,
        ]);
    }

    private function list(): Testable
    {
        return Livewire::test(ListDashboardWidgets::class)->removeTableFilter('dashboard_id');
    }

    public function test_every_column_is_sortable_without_error(): void
    {
        $this->widget(['title' => 'A']);
        $this->widget(['title' => 'B', 'type' => 'Table']);

        $this->list()
            ->sortTable('title')->sortTable('title', 'desc')
            ->sortTable('type')
            ->sortTable('order')
            ->sortTable('dashboard.title')
            ->sortTable('masterWidget.title')
            ->sortTable('master_filter_column')
            ->sortTable('is_active')
            ->assertCountTableRecords(2);
    }

    public function test_project_filter(): void
    {
        $project = Project::create(['user_id' => auth()->id(), 'name' => 'Studio X', 'date_filters' => [], 'is_current' => true]);
        $withProject = $this->widget(['project_id' => $project->id]);
        $withoutProject = $this->widget();

        $this->list()
            ->filterTable('project_id', $project->id)
            ->assertCanSeeTableRecords([$withProject])
            ->assertCanNotSeeTableRecords([$withoutProject]);
    }

    public function test_menu_category_filter(): void
    {
        $catA = MenuCategory::create(['name' => 'Cat A', 'order' => 0, 'is_active' => true]);
        $catB = MenuCategory::create(['name' => 'Cat B', 'order' => 1, 'is_active' => true]);
        $dashA = Dashboard::create(['title' => 'DA', 'menu_category_id' => $catA->id, 'order' => 1, 'is_active' => true]);
        $dashB = Dashboard::create(['title' => 'DB', 'menu_category_id' => $catB->id, 'order' => 2, 'is_active' => true]);

        $wA = DashboardWidget::create(['dashboard_id' => $dashA->id, 'title' => 'A', 'type' => 'bar', 'query' => 'SELECT 1', 'order' => 0, 'is_active' => true]);
        $wB = DashboardWidget::create(['dashboard_id' => $dashB->id, 'title' => 'B', 'type' => 'bar', 'query' => 'SELECT 1', 'order' => 0, 'is_active' => true]);

        $this->list()
            ->filterTable('menu_category', $catA->id)
            ->assertCanSeeTableRecords([$wA])
            ->assertCanNotSeeTableRecords([$wB]);
    }

    public function test_duplicate_a_widget_without_owner_runs_directly(): void
    {
        $source = $this->widget(['title' => 'Originale', 'type' => 'Table', 'query' => 'SELECT 2']);
        $this->assertNull($source->user_id);

        $this->list()->callTableAction('duplicate', $source);

        $copy = DashboardWidget::query()->where('title', 'Originale (copia)')->firstOrFail();
        $this->assertSame('SELECT 2', $copy->query);
        $this->assertSame('Table', $copy->type);
        $this->assertNull($copy->user_id);
        $this->assertNotSame($source->id, $copy->id);
    }

    public function test_duplicate_a_widget_with_owner_asks_for_the_user(): void
    {
        // Attore superadmin (setUp): vede tutti gli utenti nel menu a tendina.
        $owner = User::factory()->create(['is_admin' => false]);
        $other = User::factory()->create(['is_admin' => false]);

        $source = DashboardWidget::create([
            'dashboard_id' => $this->dashboard->id, 'title' => 'Mio', 'type' => 'bar',
            'user_id' => $owner->id,
            'query' => 'SELECT 1', 'order' => 0, 'is_active' => true,
        ]);
        $this->assertSame($owner->id, $source->user_id);

        // Assegnato a un altro utente.
        $this->list()->callTableAction('duplicate', $source, data: ['user_id' => $other->id]);
        $this->assertSame($other->id, DashboardWidget::query()->where('title', 'Mio (copia)')->value('user_id'));

        // Lasciato vuoto → nessun proprietario.
        $this->list()->callTableAction('duplicate', $source, data: ['user_id' => null]);
        $this->assertSame(
            [$other->id, null],
            DashboardWidget::query()->where('title', 'Mio (copia)')->orderBy('id')->pluck('user_id')->all(),
        );
    }
}

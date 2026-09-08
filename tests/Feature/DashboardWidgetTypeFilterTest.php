<?php

namespace Tests\Feature;

use App\Filament\Resources\DashboardWidgets\DashboardWidgetResource;
use App\Filament\Resources\DashboardWidgets\Pages\ListDashboardWidgets;
use App\Models\Dashboard;
use App\Models\DashboardWidget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardWidgetTypeFilterTest extends TestCase
{
    use RefreshDatabase;

    private DashboardWidget $tableUpper;

    private DashboardWidget $tableLower;

    private DashboardWidget $chart;

    private DashboardWidget $untyped;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create()); // superadmin

        $dashboardId = Dashboard::create(['title' => 'D', 'order' => 0, 'is_active' => true])->id;

        $make = fn (?string $type): DashboardWidget => DashboardWidget::create([
            'dashboard_id' => $dashboardId,
            'title' => 'W-'.($type ?? 'null'),
            'type' => $type,
            'query' => 'SELECT 1',
            'order' => 0,
            'is_active' => true,
        ]);

        $this->tableUpper = $make('Table');
        $this->tableLower = $make('table');
        $this->chart = $make('bar');
        $this->untyped = $make(null);
    }

    public function test_no_filter_shows_every_widget(): void
    {
        Livewire::test(ListDashboardWidgets::class)
            ->assertCanSeeTableRecords([$this->tableUpper, $this->tableLower, $this->chart, $this->untyped]);
    }

    public function test_table_filter_shows_only_table_type(): void
    {
        Livewire::test(ListDashboardWidgets::class)
            ->filterTable('is_table', 'table')
            ->assertCanSeeTableRecords([$this->tableUpper, $this->tableLower])
            ->assertCanNotSeeTableRecords([$this->chart, $this->untyped]);
    }

    public function test_not_table_filter_hides_table_type(): void
    {
        Livewire::test(ListDashboardWidgets::class)
            ->filterTable('is_table', 'not_table')
            ->assertCanSeeTableRecords([$this->chart, $this->untyped])
            ->assertCanNotSeeTableRecords([$this->tableUpper, $this->tableLower]);
    }

    private function typeColumnUrlFor(DashboardWidget $record): ?string
    {
        $column = Livewire::test(ListDashboardWidgets::class)
            ->instance()
            ->getTable()
            ->getColumn('type')
            ->record($record);

        return $column->getUrl();
    }

    public function test_type_column_links_to_the_table_view_for_table_widgets(): void
    {
        $this->assertSame(
            DashboardWidgetResource::getUrl('view', ['record' => $this->tableUpper]),
            $this->typeColumnUrlFor($this->tableUpper),
        );
        $this->assertSame(
            DashboardWidgetResource::getUrl('view', ['record' => $this->tableLower]),
            $this->typeColumnUrlFor($this->tableLower),
        );
    }

    public function test_type_column_links_to_the_chart_view_for_non_table_widgets(): void
    {
        $this->assertSame(
            DashboardWidgetResource::getUrl('chart', ['record' => $this->chart]),
            $this->typeColumnUrlFor($this->chart),
        );
        $this->assertSame(
            DashboardWidgetResource::getUrl('chart', ['record' => $this->untyped]),
            $this->typeColumnUrlFor($this->untyped),
        );
    }
}

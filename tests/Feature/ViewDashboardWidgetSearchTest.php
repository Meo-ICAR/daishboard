<?php

namespace Tests\Feature;

use App\Filament\Resources\DashboardWidgets\Pages\ViewDashboardWidget;
use App\Models\Dashboard;
use App\Models\DashboardWidget;
use App\Models\User;
use Filament\Tables\Columns\Column;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ViewDashboardWidgetSearchTest extends TestCase
{
    use RefreshDatabase;

    private DashboardWidget $widget;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());

        $this->widget = DashboardWidget::create([
            'dashboard_id' => Dashboard::create(['title' => 'D', 'order' => 0, 'is_active' => true])->id,
            'title' => 'Pazienti per etnia',
            'type' => 'Table',
            'query' => 'SELECT etnia_id, COUNT(*) AS n FROM patients WHERE active = 1 GROUP BY etnia_id ORDER BY etnia_id',
            'order' => 0,
            'is_active' => true,
        ]);
    }

    public function test_non_numeric_columns_are_searchable_numeric_ones_are_not(): void
    {
        $columns = Livewire::test(ViewDashboardWidget::class, ['record' => $this->widget->id])
            ->instance()
            ->getTable()
            ->getColumns();

        $searchable = collect($columns)->mapWithKeys(fn (Column $c): array => [$c->getLabel() => $c->isSearchable()]);

        $this->assertTrue($searchable['etnia_id']);
        $this->assertFalse($searchable['n']);
    }

    public function test_search_filters_rows_by_non_numeric_column_value(): void
    {
        Livewire::test(ViewDashboardWidget::class, ['record' => $this->widget->id])
            ->assertSee('caucasico')
            ->assertSee('africano')
            ->searchTable('cauc')
            ->assertCountTableRecords(1)
            ->assertSee('caucasico')
            ->assertDontSee('africano')
            ->searchTable('zzz-nessun-match')
            ->assertCountTableRecords(0);
    }
}

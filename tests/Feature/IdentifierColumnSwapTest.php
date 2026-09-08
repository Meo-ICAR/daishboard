<?php

namespace Tests\Feature;

use App\Filament\Resources\DashboardWidgets\Pages\ViewDashboardWidget;
use App\Models\Dashboard;
use App\Models\DashboardWidget;
use App\Models\User;
use App\Support\DataNavigatorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class IdentifierColumnSwapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    private function widget(string $query): DashboardWidget
    {
        return DashboardWidget::create([
            'dashboard_id' => Dashboard::create(['title' => 'D', 'order' => 0, 'is_active' => true])->id,
            'title' => 'W',
            'type' => 'Table',
            'query' => $query,
            'order' => 0,
            'is_active' => true,
        ]);
    }

    public function test_profile_for_hassisdadmin_exposes_pazientecode_as_identifier(): void
    {
        $this->assertSame('hassisdadmin', DataNavigatorProfile::databaseName());
        $this->assertSame('pazientecode', DataNavigatorProfile::identifierColumn());
    }

    public function test_swap_select_identifier_rewrites_only_bare_id_items(): void
    {
        $model = new DashboardWidget;

        $this->assertSame(
            'SELECT p.pazientecode, p.centro FROM patients p',
            $model->swapSelectIdentifier('SELECT p.id, p.centro FROM patients p', 'pazientecode'),
        );

        // `AS id` viene normalizzato; le funzioni e gli altri alias non si toccano.
        $this->assertSame(
            'SELECT p.pazientecode, COUNT(p.id) AS n, c.id AS centro_id FROM patients p',
            $model->swapSelectIdentifier(
                'SELECT p.id AS id, COUNT(p.id) AS n, c.id AS centro_id FROM patients p',
                'pazientecode',
            ),
        );

        // Nessun database con identifier_column: no-op.
        $this->assertSame(
            'SELECT id FROM patients',
            $model->swapSelectIdentifier('SELECT id FROM patients', ''),
        );
    }

    public function test_table_view_shows_pazientecode_instead_of_id(): void
    {
        $widget = $this->widget('SELECT p.id, p.centro FROM patients p WHERE p.active = 1 LIMIT 5');

        $columns = Livewire::test(ViewDashboardWidget::class, ['record' => $widget->id])->get('queryColumns');

        $this->assertContains('pazientecode', $columns);
        $this->assertNotContains('id', $columns);
    }

    public function test_generated_drilldown_detail_shows_pazientecode(): void
    {
        $widget = $this->widget(
            'SELECT p.centro AS centro, COUNT(p.id) AS n FROM patients p WHERE p.active = 1 GROUP BY p.centro',
        );

        $drill = rtrim(strtr(base64_encode((string) json_encode([
            ['label' => 'centro', 'expr' => 'p.centro', 'value' => 'COTUGNO NAPOLI'],
        ])), '+/', '-_'), '=');

        $detail = Livewire::test(ViewDashboardWidget::class, ['record' => $widget->id, 'drill' => $drill]);

        $this->assertContains('pazientecode', $detail->get('queryColumns'));
        $this->assertNotContains('n', $detail->get('queryColumns'));
    }
}

<?php

namespace Tests\Feature;

use Database\Seeders\CompanySeeder;
use Database\Seeders\MediatoreDashboardSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MediatoreDashboardSeederTest extends TestCase
{
    use RefreshDatabase;

    /** Tutte le dashboard seminate, indicizzate per titolo. */
    private function proformaDashboards(): Collection
    {
        return DB::table('dashboards')->where('database', 'proforma')->get()->keyBy('title');
    }

    public function test_it_seeds_the_domain_dashboards_and_widgets(): void
    {
        $this->seed([CompanySeeder::class, MediatoreDashboardSeeder::class]);

        $dashboards = $this->proformaDashboards();

        foreach ([
            'Pipeline & SLA Pratiche',
            'Provvigioni & Redditività',
            'ENASARCO & Disallineamenti OAM',
            'Gestione Provvigioni & Fatturazione',
        ] as $title) {
            $this->assertArrayHasKey($title, $dashboards);
        }

        // Dashboard di dominio: company_id NULL. Dashboard rettificata: company_id = 2.
        $this->assertNull($dashboards['Pipeline & SLA Pratiche']->company_id);
        $this->assertSame(2, (int) $dashboards['Gestione Provvigioni & Fatturazione']->company_id);

        $domainIds = $dashboards->whereNull('company_id')->pluck('id');
        $domainWidgets = DB::table('dashboard_widgets')->whereIn('dashboard_id', $domainIds)->get();
        $this->assertSame(13, $domainWidgets->whereNull('master_widget_id')->count());
        $this->assertSame(3, $domainWidgets->whereNotNull('master_widget_id')->count());

        $rectifiedId = $dashboards['Gestione Provvigioni & Fatturazione']->id;
        $rectified = DB::table('dashboard_widgets')->where('dashboard_id', $rectifiedId)->get();
        $this->assertSame(16, $rectified->whereNull('master_widget_id')->count());
        $this->assertSame(3, $rectified->whereNotNull('master_widget_id')->count());
        // I widget rettificati ereditano company_id = 2 dalla loro dashboard.
        $this->assertTrue($rectified->every(fn ($w): bool => (int) $w->company_id === 2));
    }

    public function test_drilldown_children_reference_their_master_and_a_filter_column(): void
    {
        $this->seed([CompanySeeder::class, MediatoreDashboardSeeder::class]);

        $children = DB::table('dashboard_widgets')->whereNotNull('master_widget_id')->get();
        $this->assertSame(6, $children->count());

        foreach ($children as $child) {
            $this->assertNotNull($child->master_filter_column);
            $this->assertStringContainsString(':', $child->query, 'Un widget figlio deve contenere il parametro di drill-down.');

            $master = DB::table('dashboard_widgets')->find($child->master_widget_id);
            $this->assertNotNull($master);
            $this->assertNull($master->master_widget_id);
            $this->assertSame($child->dashboard_id, $master->dashboard_id);
            $this->assertStringContainsString($child->master_filter_column, $master->query);
        }
    }

    public function test_master_widget_queries_are_parameterless_selects(): void
    {
        $this->seed([CompanySeeder::class, MediatoreDashboardSeeder::class]);

        $masters = DB::table('dashboard_widgets')
            ->whereIn('dashboard_id', DB::table('dashboards')->where('database', 'proforma')->pluck('id'))
            ->whereNull('master_widget_id')
            ->get();

        $this->assertSame(29, $masters->count());

        foreach ($masters as $master) {
            $this->assertMatchesRegularExpression('/^\s*(--[^\n]*\n\s*)?SELECT\b/i', $master->query);
            $this->assertDoesNotMatchRegularExpression('/[^:]:[a-z_]+/i', $master->query, "La query master \"{$master->title}\" non deve avere parametri di bind.");
            $this->assertContains($master->type, ['table', 'bar', 'line', 'pie']);
        }
    }

    public function test_it_is_idempotent(): void
    {
        $this->seed([CompanySeeder::class, MediatoreDashboardSeeder::class]);
        $this->seed([CompanySeeder::class, MediatoreDashboardSeeder::class]);

        $this->assertSame(1, DB::table('dashboards')->where('title', 'Pipeline & SLA Pratiche')->count());
        $this->assertSame(1, DB::table('dashboards')->where('title', 'Gestione Provvigioni & Fatturazione')->count());

        $this->assertSame(35, DB::table('dashboard_widgets')
            ->whereIn('dashboard_id', DB::table('dashboards')->where('database', 'proforma')->pluck('id'))
            ->count());
    }
}

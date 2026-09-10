<?php

namespace Tests\Feature;

use Database\Seeders\CompanySeeder;
use Database\Seeders\MediatoreDashboardSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;

class MediatoreDashboardSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Il blueprint del seeder (privato) esposto via reflection.
     *
     * @return array<int, array<string, mixed>>
     */
    private function blueprint(): array
    {
        $method = new ReflectionMethod(MediatoreDashboardSeeder::class, 'blueprint');
        $method->setAccessible(true);

        return $method->invoke(new MediatoreDashboardSeeder);
    }

    /** @return array{masters: int, children: int} */
    private function expectedCounts(?callable $filter = null): array
    {
        $masters = 0;
        $children = 0;

        foreach ($this->blueprint() as $group) {
            if ($filter !== null && ! $filter($group)) {
                continue;
            }

            foreach ($group['widgets'] as $widget) {
                $masters++;
                $children += count($widget['children'] ?? []);
            }
        }

        return ['masters' => $masters, 'children' => $children];
    }

    private function proformaDashboards(): Collection
    {
        return DB::table('dashboards')->where('database', 'proforma')->get()->keyBy('title');
    }

    public function test_it_seeds_every_blueprint_dashboard_and_widget(): void
    {
        $this->seed([CompanySeeder::class, MediatoreDashboardSeeder::class]);

        $dashboards = $this->proformaDashboards();

        foreach ($this->blueprint() as $group) {
            $this->assertArrayHasKey($group['dashboard'], $dashboards);
        }

        $this->assertNull($dashboards['Pipeline & SLA Pratiche']->company_id);
        $this->assertSame(2, (int) $dashboards['Gestione Provvigioni & Fatturazione']->company_id);

        // Widget "di dominio" (dashboard con company_id NULL).
        $domain = $this->expectedCounts(fn (array $g): bool => ! isset($g['company_id']));
        $domainIds = $dashboards->whereNull('company_id')->pluck('id');
        $domainWidgets = DB::table('dashboard_widgets')->whereIn('dashboard_id', $domainIds)->get();
        $this->assertSame($domain['masters'], $domainWidgets->whereNull('master_widget_id')->count());
        $this->assertSame($domain['children'], $domainWidgets->whereNotNull('master_widget_id')->count());

        // Dashboard rettificata (company_id = 2).
        $rectified = $this->expectedCounts(fn (array $g): bool => ($g['company_id'] ?? null) === 2);
        $rid = $dashboards['Gestione Provvigioni & Fatturazione']->id;
        $rw = DB::table('dashboard_widgets')->where('dashboard_id', $rid)->get();
        $this->assertSame($rectified['masters'], $rw->whereNull('master_widget_id')->count());
        $this->assertSame($rectified['children'], $rw->whereNotNull('master_widget_id')->count());
        $this->assertTrue($rw->every(fn ($w): bool => (int) $w->company_id === 2));
    }

    public function test_drilldown_children_reference_their_master_and_a_filter_column(): void
    {
        $this->seed([CompanySeeder::class, MediatoreDashboardSeeder::class]);

        $children = DB::table('dashboard_widgets')->whereNotNull('master_widget_id')->get();
        $this->assertSame($this->expectedCounts()['children'], $children->count());

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

        $this->assertSame($this->expectedCounts()['masters'], $masters->count());

        foreach ($masters as $master) {
            $this->assertMatchesRegularExpression('/^\s*(--[^\n]*\n\s*)?SELECT\b/i', $master->query);
            $this->assertDoesNotMatchRegularExpression('/[^:]:[a-z_]+/i', $master->query, "La query master \"{$master->title}\" non deve avere parametri di bind.");
            $this->assertContains($master->type, ['table', 'bar', 'line', 'pie']);
        }
    }

    public function test_rerunning_the_seeder_syncs_in_place_without_duplicating(): void
    {
        $this->seed([CompanySeeder::class, MediatoreDashboardSeeder::class]);

        // Simula una query "vecchia" da correggere e un widget obsoleto da rimuovere.
        $pipelineId = DB::table('dashboards')->where('title', 'Pipeline & SLA Pratiche')->value('id');
        DB::table('dashboard_widgets')
            ->where('dashboard_id', $pipelineId)
            ->where('title', 'Produzione erogata per mese')
            ->update(['query' => 'SELECT 1']);
        DB::table('dashboard_widgets')->insert([
            'dashboard_id' => $pipelineId,
            'title' => 'Widget obsoleto',
            'type' => 'table',
            'query' => 'SELECT 1',
            'order' => 99,
            'is_active' => true,
        ]);

        $this->seed(MediatoreDashboardSeeder::class);

        // Nessun duplicato di dashboard.
        $this->assertSame(1, DB::table('dashboards')->where('title', 'Pipeline & SLA Pratiche')->count());

        // La query è stata risincronizzata e il widget obsoleto rimosso.
        $this->assertStringContainsString('DATE_FORMAT(erogated_at', DB::table('dashboard_widgets')
            ->where('dashboard_id', $pipelineId)->where('title', 'Produzione erogata per mese')->value('query'));
        $this->assertDatabaseMissing('dashboard_widgets', ['dashboard_id' => $pipelineId, 'title' => 'Widget obsoleto']);

        $expected = $this->expectedCounts();
        $this->assertSame($expected['masters'] + $expected['children'], DB::table('dashboard_widgets')
            ->whereIn('dashboard_id', DB::table('dashboards')->where('database', 'proforma')->pluck('id'))
            ->count());
    }
}

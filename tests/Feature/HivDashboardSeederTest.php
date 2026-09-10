<?php

namespace Tests\Feature;

use Database\Seeders\CompanySeeder;
use Database\Seeders\HivDashboardSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HivDashboardSeederTest extends TestCase
{
    use RefreshDatabase;

    private function seedHiv(): void
    {
        $this->seed([CompanySeeder::class, HivDashboardSeeder::class]);
    }

    private function widgets(): Collection
    {
        $ids = DB::table('dashboards')
            ->where('database', 'hassisdadmin')
            ->where('company_id', 3)
            ->pluck('id');

        return DB::table('dashboard_widgets')->whereIn('dashboard_id', $ids)->get();
    }

    public function test_it_seeds_three_company_3_dashboards(): void
    {
        $this->seedHiv();

        $dashboards = DB::table('dashboards')
            ->where('database', 'hassisdadmin')
            ->where('company_id', 3)
            ->pluck('title');

        $this->assertCount(3, $dashboards);
        $this->assertEqualsCanonicalizing([
            'Rischio Cardiovascolare & Placca Carotidea',
            'Demografia Pazienti & Trattamenti',
            'Gestione Pazienti',
        ], $dashboards->all());

        $widgets = $this->widgets();
        $this->assertSame(19, $widgets->whereNull('master_widget_id')->count());
        $this->assertSame(8, $widgets->whereNotNull('master_widget_id')->count());
        $this->assertTrue($widgets->every(fn ($w): bool => (int) $w->company_id === 3));
    }

    public function test_widget_types_are_canonical(): void
    {
        $this->seedHiv();

        foreach ($this->widgets() as $widget) {
            $this->assertContains($widget->type, ['table', 'bar', 'line', 'pie'], "Tipo non canonico: {$widget->type}");
            $this->assertMatchesRegularExpression('/^\s*SELECT\b/i', $widget->query);
        }
    }

    public function test_drilldown_children_reference_a_column_of_their_master(): void
    {
        $this->seedHiv();

        $children = $this->widgets()->whereNotNull('master_widget_id');
        $this->assertCount(8, $children);

        foreach ($children as $child) {
            $this->assertNotNull($child->master_filter_column);

            $master = DB::table('dashboard_widgets')->find($child->master_widget_id);
            $this->assertNotNull($master);
            $this->assertNull($master->master_widget_id);
            $this->assertSame($child->dashboard_id, $master->dashboard_id);
            $this->assertStringContainsString($child->master_filter_column, $master->query);
        }
    }

    public function test_queries_respect_the_hiv_profile_rules(): void
    {
        $this->seedHiv();

        foreach ($this->widgets() as $widget) {
            // Nessuna colonna identificativa "iniziali".
            $this->assertDoesNotMatchRegularExpression('/\biniziali\b/i', $widget->query, "\"{$widget->title}\" espone la colonna iniziali");

            // Le query su patients / patient_visits filtrano active = 1
            // (eccetto quelle basate su viste vw*).
            $onView = str_contains($widget->query, ' vw');
            $touchesCohort = preg_match('/\b(from|join)\s+patients\b/i', $widget->query)
                || preg_match('/\bpatient_visits\b/i', $widget->query);

            if ($touchesCohort && ! $onView) {
                $this->assertMatchesRegularExpression('/active\s*=\s*1/i', $widget->query, "\"{$widget->title}\" non filtra active = 1");
            }
        }

        // La lista 0-18 identifica i pazienti con pazientecode.
        $list = $this->widgets()->firstWhere('title', 'Elenco pazienti 0-18 anni');
        $this->assertStringContainsString('pazientecode', $list->query);
    }

    public function test_it_is_idempotent(): void
    {
        $this->seedHiv();
        $this->seed(HivDashboardSeeder::class);

        $this->assertCount(3, DB::table('dashboards')->where('company_id', 3)->get());
        $this->assertCount(27, $this->widgets());
    }
}

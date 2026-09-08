<?php

namespace Tests\Feature;

use App\Filament\Resources\DashboardWidgets\Pages\ViewDashboardWidget;
use App\Models\Dashboard;
use App\Models\DashboardWidget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use ReflectionMethod;
use Tests\TestCase;

class WidgetGeneratedDrillDownTest extends TestCase
{
    use RefreshDatabase;

    private const GROUPED_SQL = 'SELECT etnia_id, COUNT(*) AS n FROM patients WHERE active = 1 GROUP BY etnia_id ORDER BY n DESC';

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    private function groupedWidget(): DashboardWidget
    {
        return DashboardWidget::create([
            'dashboard_id' => Dashboard::create([
                'user_id' => auth()->id(), 'title' => 'D', 'order' => 0, 'is_active' => true,
            ])->id,
            'title' => 'Pazienti per etnia',
            'type' => 'Table',
            'query' => self::GROUPED_SQL,
            'order' => 0,
            'is_active' => true,
        ]);
    }

    /**
     * @param  list<array{label: string, expr: string, value: mixed}>  $filters
     */
    private function encodeDrill(array $filters): string
    {
        return rtrim(strtr(base64_encode((string) json_encode($filters)), '+/', '-_'), '=');
    }

    public function test_model_extracts_group_by_dimension_expressions(): void
    {
        $widget = new DashboardWidget;

        $sql = "SELECT U.center, concat(U.last_name,' ',U.first_name) AS Specialista, COUNT(P.id) AS total
                FROM patient_visits P JOIN users U ON P.created_by = U.id
                GROUP BY U.center, U.last_name, U.first_name";

        $map = $widget->selectDimensionExpressions($sql);

        $this->assertSame('U.center', $map['center']);
        $this->assertSame("concat(U.last_name,' ',U.first_name)", $map['specialista']);
        $this->assertArrayNotHasKey('total', $map); // aggregata esclusa
    }

    public function test_convert_sql_string_strips_grouping_and_appends_filters(): void
    {
        $detail = (new DashboardWidget)->convertSqlStringToDrillDown(
            self::GROUPED_SQL,
            ['etnia_id' => 'caucasico'],
        );

        $this->assertStringNotContainsStringIgnoringCase('GROUP BY', $detail);
        $this->assertStringNotContainsStringIgnoringCase('ORDER BY', $detail);
        $this->assertStringContainsString("etnia_id = 'caucasico'", $detail);
        // COUNT(*) non ha un campo di dettaglio: si ripiega su SELECT *.
        $this->assertStringNotContainsStringIgnoringCase('COUNT(', $detail);
        $this->assertMatchesRegularExpression('/^SELECT \* FROM patients\b/i', $detail);
    }

    public function test_a_null_dimension_value_becomes_is_null(): void
    {
        $detail = (new DashboardWidget)->convertSqlStringToDrillDown(
            self::GROUPED_SQL,
            ['etnia_id' => null],
        );

        $this->assertStringContainsString('etnia_id IS NULL', $detail);
    }

    public function test_view_page_exposes_dimension_expressions_for_a_grouped_widget(): void
    {
        $widget = $this->groupedWidget();

        $page = Livewire::test(ViewDashboardWidget::class, ['record' => $widget->id]);

        $method = new ReflectionMethod($page->instance(), 'dimensionExpressions');
        $method->setAccessible(true);
        $expressions = $method->invoke($page->instance());

        $this->assertArrayHasKey('etnia_id', $expressions);
        $this->assertSame([], $page->get('drillFilters'));
    }

    public function test_mounting_with_a_drill_param_runs_the_detail_query(): void
    {
        $widget = $this->groupedWidget();

        // Prima l'aggregato: più righe, una per etnia.
        $aggregate = Livewire::test(ViewDashboardWidget::class, ['record' => $widget->id]);
        $this->assertGreaterThan(1, count($aggregate->get('queryRows')));

        // Poi il drill-down su una riga: solo i pazienti di quell'etnia, non aggregati.
        $detail = Livewire::test(ViewDashboardWidget::class, [
            'record' => $widget->id,
            'drill' => $this->encodeDrill([
                ['label' => 'etnia_id', 'expr' => 'etnia_id', 'value' => 'caucasico'],
            ]),
        ]);

        $rows = $detail->get('queryRows');

        $this->assertSame(
            [['label' => 'etnia_id', 'expr' => 'etnia_id', 'value' => 'caucasico']],
            $detail->get('drillFilters'),
        );
        $this->assertGreaterThan(1, count($rows));
        $this->assertNotContains('n', array_keys($rows[0])); // niente colonna aggregata
        foreach (array_slice($rows, 0, 20) as $row) {
            $this->assertSame('caucasico', $row['etnia_id']);
        }
    }

    public function test_actions_are_in_the_table_header(): void
    {
        $widget = $this->groupedWidget();

        // Le azioni sono nell'intestazione della tabella (non nell'header di
        // pagina), così titolo e sottotitolo restano a piena larghezza.
        Livewire::test(ViewDashboardWidget::class, ['record' => $widget->id])
            ->assertTableActionExists('edit')
            ->assertTableActionExists('chart');

        Livewire::test(ViewDashboardWidget::class, [
            'record' => $widget->id,
            'drill' => $this->encodeDrill([
                ['label' => 'etnia_id', 'expr' => 'etnia_id', 'value' => 'caucasico'],
            ]),
        ])->assertTableActionExists('backToAggregate');
    }
}

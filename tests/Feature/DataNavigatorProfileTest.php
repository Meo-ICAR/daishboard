<?php

namespace Tests\Feature;

use App\Neuron\DataNavigatorAgent;
use App\Services\WidgetDatasetRunner;
use App\Support\DataNavigatorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class DataNavigatorProfileTest extends TestCase
{
    use RefreshDatabase;

    private function instructions(DataNavigatorAgent $agent): string
    {
        $method = new ReflectionMethod($agent, 'instructions');
        $method->setAccessible(true);

        return $method->invoke($agent);
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function profile(DataNavigatorAgent $agent): array
    {
        $method = new ReflectionMethod($agent, 'resolveProfile');
        $method->setAccessible(true);

        return $method->invoke($agent);
    }

    public function test_it_selects_the_profile_from_the_connected_database_name(): void
    {
        // La connessione dbai di test punta a hassisdadmin -> profilo hiv.
        [$key, $profile] = $this->profile(new DataNavigatorAgent);

        $this->assertSame('hiv', $key);
        $this->assertSame(['patients', 'patient_visits'], $profile['tables']);
    }

    public function test_forced_profile_overrides_the_database_detection(): void
    {
        config(['data_navigator.profile' => 'mediatore']);

        [$key, $profile] = $this->profile(new DataNavigatorAgent);

        $this->assertSame('mediatore', $key);
        $this->assertContains('pratiches', $profile['tables']);
        $this->assertContains('provvigioni', $profile['tables']);
    }

    public function test_hiv_instructions_carry_the_cohort_domain_rules(): void
    {
        $text = $this->instructions(new DataNavigatorAgent);

        $this->assertStringContainsString('pazientecode', $text);
        $this->assertStringContainsString('active = 1', $text);
        $this->assertStringNotContainsString('entrata_uscita', $text);
    }

    public function test_mediatore_instructions_carry_the_credit_broker_domain_rules(): void
    {
        config(['data_navigator.profile' => 'mediatore']);

        $text = $this->instructions(new DataNavigatorAgent);

        $this->assertStringContainsString('entrata_uscita', $text);
        $this->assertStringContainsString('provvigioni.importo', $text);
        $this->assertStringContainsString('provvigioni.id_pratica', $text);
        // Vocabolario degli stati: le dizioni sono mappate sui campi *_at, e accepted_at non esiste.
        $this->assertStringContainsString('"deliberata"', $text);
        $this->assertStringContainsString('"perfezionata"', $text);
        $this->assertStringContainsString('approved_at IS NOT NULL AND erogated_at IS NULL', $text);
        $this->assertStringNotContainsString('accepted_at IS NOT NULL', $text);
        // Nessuna legenda sincronizzata per proforma: fallback agli strumenti di ispezione.
        $this->assertStringContainsString('legenda non ancora sincronizzata', $text);
    }

    public function test_cohort_tables_follow_the_active_profile(): void
    {
        // dbai di test -> hassisdadmin -> profilo hiv.
        $this->assertSame(['patients', 'patient_visits'], DataNavigatorProfile::cohortTables());

        config(['data_navigator.profile' => 'mediatore']);

        $tables = DataNavigatorProfile::cohortTables();
        $this->assertContains('pratiches', $tables);
        $this->assertContains('provvigioni', $tables);
        $this->assertNotContains('patients', $tables);
    }

    public function test_runner_detects_the_active_profiles_cohort_tables_in_the_sql(): void
    {
        $runner = app(WidgetDatasetRunner::class);

        // Profilo hiv: le tabelle proforma non sono di coorte.
        $this->assertSame([], $runner->resolveCohortTableAliases('SELECT * FROM pratiches p'));

        config(['data_navigator.profile' => 'mediatore']);

        $aliases = $runner->resolveCohortTableAliases(
            'SELECT * FROM pratiches p INNER JOIN provvigioni pr ON pr.id_pratica = p.id',
        );
        $this->assertSame(['pratiches' => 'p', 'provvigioni' => 'pr'], $aliases);

        // `pratiches_statos` (codifica, non tabella di coorte) non deve essere
        // scambiata per `pratiches`: il lookahead della regex la esclude.
        $this->assertSame(
            [],
            $runner->resolveCohortTableAliases('SELECT * FROM pratiches_statos ps'),
        );
    }

    public function test_unknown_database_without_profile_throws(): void
    {
        config([
            'data_navigator.profile' => null,
            'data_navigator.default' => null,
            'data_navigator.profiles.hiv.databases' => ['some-other-db'],
        ]);

        $this->expectExceptionMessage('Nessun profilo DataNavigator');

        new DataNavigatorAgent;
    }
}

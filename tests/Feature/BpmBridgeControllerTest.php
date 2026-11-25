<?php

namespace Tests\Feature;

use App\Http\Middleware\UseClinicalDbWhenComingFromClinicalDb;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BpmBridgeControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_logs_in_via_a_token_verified_against_the_default_source(): void
    {
        User::factory()->create(['email' => 'agente@example.com']);

        config()->set('services.bridge_sources.unicobpm', 'https://unicobpm.test');
        Http::fake(['https://unicobpm.test/api/verify-token' => Http::response(['valid' => true])]);

        $response = $this->get('/bpm-landing/dashboard?'.http_build_query([
            'token' => 'sometoken',
            'user_email' => 'agente@example.com',
        ]));

        $response->assertRedirect('/admin');
        $this->assertAuthenticatedAs(User::where('email', 'agente@example.com')->first());
    }

    public function test_logs_in_via_a_token_verified_against_an_explicit_source(): void
    {
        User::factory()->create(['email' => 'agente@example.com']);

        config()->set('services.bridge_sources.clinicaldb', 'https://clinicaldb.test');
        Http::fake(['https://clinicaldb.test/api/verify-token' => Http::response(['valid' => true])]);

        $response = $this->get('/bpm-landing/dashboard?'.http_build_query([
            'token' => 'sometoken',
            'user_email' => 'agente@example.com',
            'source' => 'clinicaldb',
        ]));

        $response->assertRedirect('/admin');
        Http::assertSent(fn ($request) => $request->url() === 'https://clinicaldb.test/api/verify-token');
    }

    public function test_flags_the_session_only_when_coming_from_clinicaldb(): void
    {
        User::factory()->create(['email' => 'agente@example.com']);

        config()->set('services.bridge_sources.clinicaldb', 'https://clinicaldb.test');
        config()->set('services.bridge_sources.unicobpm', 'https://unicobpm.test');
        Http::fake(['*/api/verify-token' => Http::response(['valid' => true])]);

        $this->get('/bpm-landing/dashboard?'.http_build_query([
            'token' => 'sometoken',
            'user_email' => 'agente@example.com',
            'source' => 'clinicaldb',
        ]))->assertSessionHas(UseClinicalDbWhenComingFromClinicalDb::SESSION_KEY, true);

        $this->get('/bpm-landing/dashboard?'.http_build_query([
            'token' => 'sometoken',
            'user_email' => 'agente@example.com',
        ]))->assertSessionHas(UseClinicalDbWhenComingFromClinicalDb::SESSION_KEY, false);
    }

    public function test_uses_the_clinicaldb_database_for_sessions_coming_from_clinicaldb(): void
    {
        config()->set('database.connections.dbai.database', 'proforma');

        $this->withSession([UseClinicalDbWhenComingFromClinicalDb::SESSION_KEY => true])->get('/admin/login');

        $this->assertSame('clinicaldb', config('database.connections.dbai.database'));
    }

    public function test_keeps_the_default_database_without_the_clinicaldb_flag(): void
    {
        config()->set('database.connections.dbai.database', 'proforma');

        $this->get('/admin/login');

        $this->assertSame('proforma', config('database.connections.dbai.database'));
    }

    public function test_rejects_an_unknown_source(): void
    {
        $response = $this->get('/bpm-landing/dashboard?'.http_build_query([
            'token' => 'sometoken',
            'user_email' => 'agente@example.com',
            'source' => 'not-a-real-app',
        ]));

        $response->assertForbidden();
    }
}

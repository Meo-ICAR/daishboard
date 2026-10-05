<?php

namespace Tests\Feature;

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

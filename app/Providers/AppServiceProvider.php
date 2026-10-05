<?php

namespace App\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Google\GoogleExtendSocialite;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Microsoft\MicrosoftExtendSocialite;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // I provider SocialiteProviders (microsoft, google) non sono driver
        // nativi di Socialite: vanno registrati agganciandosi a SocialiteWasCalled.
        Event::listen(SocialiteWasCalled::class, [MicrosoftExtendSocialite::class, 'handle']);
        Event::listen(SocialiteWasCalled::class, [GoogleExtendSocialite::class, 'handle']);

        $this->configureTenantByHost();
    }

    /**
     * Forza la connessione `dbai` e il profilo dell'assistente dati in base
     * al dominio con cui l'app viene servita, per gli host che ospitano un
     * solo cliente/dominio indipendentemente dalla configurazione in .env.
     */
    private function configureTenantByHost(): void
    {
        if ($this->app->runningInConsole()) {
            return;
        }

        $overridesByHost = [
            'dashboard.archiprevaleat.com' => [
                'database.connections.dbai.database' => 'clinicaldb',
                'data_navigator.profile' => 'hiv',
            ],
        ];

        $overrides = $overridesByHost[request()->getHost()] ?? null;

        if ($overrides !== null) {
            config($overrides);
        }
    }
}

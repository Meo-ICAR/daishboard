<?php

namespace App\Providers\Filament;

use App\Http\Middleware\RememberActiveCompany;
use App\Models\Company;
use App\Models\SocialiteUser;
use App\Models\User;
use DutchCodingCompany\FilamentSocialite\FilamentSocialitePlugin;
use DutchCodingCompany\FilamentSocialite\Provider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Laravel\Socialite\Contracts\User as SocialiteUserContract;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->registration()
            ->passwordReset()
            ->colors([
                'primary' => Color::Amber,
            ])
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): HtmlString => new HtmlString('<style>.fi-header-has-subheading > div:first-child:not(.fi-header-actions-ctn){flex:1 1 0%;width:auto;min-width:0}.fi-header-subheading{max-width:none}</style>'),
            )
            ->tenant(Company::class)
            ->tenantMiddleware([
                RememberActiveCompany::class,
            ], isPersistent: true)
            ->brandLogo(asset('brand/logos/dai-shboard.jpg'))
            ->brandLogoHeight('5rem')
            ->favicon(asset('brand/icons/dai-shboard-64.png'))
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                //
            ])
            ->navigationGroups([
                NavigationGroup::make('Impostazioni')->collapsed(),
                NavigationGroup::make('Legenda')->collapsed(),
                NavigationGroup::make('Documentazione')->collapsed(),
                NavigationGroup::make('Configurazioni')->collapsed(),
                NavigationGroup::make('Manuali')->collapsed(),
            ])
            ->navigationItems([
                NavigationItem::make('Manuale utente')
                    ->url(fn (): string => route('docs.manuale-utente'), shouldOpenInNewTab: true)
                    ->icon(Heroicon::OutlinedBookOpen)
                    ->group('Manuali')
                    ->sort(1),
            ])
           // ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
       //     ->widgets([
             //   AccountWidget::class,
              //  FilamentInfoWidget::class,
       //     ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->plugin(
                FilamentSocialitePlugin::make()
                    // (required) Add providers corresponding with providers in `config/services.php`.
                    ->providers([
                        // Create a provider 'microsoft' corresponding to the Socialite driver with the same name.
                        Provider::make('microsoft')
                            ->label('Microsoft')
                            ->icon('fab-microsoft')
                            ->color(Color::hex('#f3f3f3'))
                            ->outlined(false)
                            ->stateless(false),
                        //     ->scopes(['...'])
                        //    ->with(['...']),
                        Provider::make('google')
                            ->label('Google')
                            ->icon('fab-google')
                            ->color(Color::hex('#4285F4'))
                            ->outlined(false)
                            ->stateless(false),
                        //   ->scopes(['...'])
                        //   ->with(['...']),
                    ])
                    // (optional) Override the panel slug to be used in the oauth routes. Defaults to the panel's configured path.
               //     ->slug('admin')
                    // (optional) Enable/disable registration of new (socialite-) users.
                    ->registration(true)
                // (optional) Enable/disable registration of new (socialite-) users using a callback.
                // In this example, a login flow can only continue if there exists a user (Authenticatable) already.
                //   ->registration(fn (string $provider, SocialiteUserContract $oauthUser, ?Authenticatable $user) => (bool) $user)
                // (optional) Change the associated model class.
                //  ->userModelClass(User::class)
                // (optional) Change the associated socialite class (see below).
                //  ->socialiteUserModelClass(SocialiteUser::class)
            );
    }
}

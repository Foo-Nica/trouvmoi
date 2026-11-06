<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/** Espace des photographes : /studio */
class StudioPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('studio')
            ->path('studio')
            ->brandName(config('app.name').' Studio')
            ->brandLogo(fn () => asset('images/logo-600.png'))
            ->darkModeBrandLogo(fn () => asset('images/logo-white-600.png'))
            ->brandLogoHeight('2.25rem')
            ->favicon(fn () => asset('favicon.ico'))
            ->login()
            ->registration()
            ->passwordReset()
            ->emailVerification()
            ->profile()
            ->colors([
                'primary' => Color::hex('#282667'),
                'warning' => Color::hex('#FE7737'),
            ])
            ->font('Poppins')
            ->discoverResources(in: app_path('Filament/Studio/Resources'), for: 'App\Filament\Studio\Resources')
            ->discoverPages(in: app_path('Filament/Studio/Pages'), for: 'App\Filament\Studio\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Studio/Widgets'), for: 'App\Filament\Studio\Widgets')
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
            ]);
    }
}

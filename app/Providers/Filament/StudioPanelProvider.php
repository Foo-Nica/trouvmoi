<?php

namespace App\Providers\Filament;

use App\Filament\AvatarProviders\InitialsAvatarProvider;
use Filament\FontProviders\LocalFontProvider;
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

/** Espace de l'équipe TRECHART : /studio (comptes créés par l'administrateur). */
class StudioPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('studio')
            ->path('studio')
            ->brandName(config('app.name'))
            ->brandLogo(fn () => asset('images/logo-dark.png'))
            ->darkModeBrandLogo(fn () => asset('images/logo-white.png'))
            ->brandLogoHeight('2.25rem')
            ->favicon(fn () => asset('favicon.ico'))
            ->defaultAvatarProvider(InitialsAvatarProvider::class)
            ->login()
            ->passwordReset()
            ->emailVerification()
            ->profile()
            ->colors([
                'primary' => Color::hex('#ED8B00'),
                'gray' => Color::Neutral,
            ])
            ->font('Satoshi', url: fn (): string => asset('fonts/satoshi.css'), provider: LocalFontProvider::class)
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

<?php

namespace App\Providers;

use App\Services\FaceEngine\FaceEngine;
use App\Services\FaceEngine\HttpFaceEngine;
use App\Services\Guests\GuestSession;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\DevCommands;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FaceEngine::class, fn () => HttpFaceEngine::fromConfig());
        $this->app->singleton(GuestSession::class);
    }

    public function boot(): void
    {
        // `composer run dev` : un simple worker sur les trois files (Horizon, lui, exige Redis
        // et Linux/macOS ; il reste l'outil de production).
        DevCommands::except('queue', 'horizon');
        DevCommands::artisan('queue:listen --queue=photos,faces,default --tries=3 --timeout=0', 'queue-workers');

        // Envoi de photos : large pour les gros lots, mais borné par compte.
        RateLimiter::for('uploads', fn (Request $request) => Limit::perMinute(600)->by($request->user()?->id ?: $request->ip()));

        // Recherches faciales des invités : anti-aspiration de la galerie.
        RateLimiter::for('face-search', fn (Request $request) => [
            Limit::perMinute(10)->by($request->ip()),
            Limit::perDay(100)->by($request->ip()),
        ]);
    }
}

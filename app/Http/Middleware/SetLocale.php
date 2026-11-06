<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Langue des pages invités : ?lang=…, puis la session, puis la langue du téléphone. */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = array_keys(config('trouvmoi.locales'));

        if (in_array($lang = $request->query('lang'), $supported, true)) {
            $request->session()->put('locale', $lang);
        }

        $locale = $request->session()->get('locale')
            ?? $request->getPreferredLanguage($supported)
            ?? config('app.locale');

        app()->setLocale(in_array($locale, $supported, true) ? $locale : config('app.locale'));

        return $next($request);
    }
}

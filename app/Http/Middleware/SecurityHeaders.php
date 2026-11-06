<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * En-têtes de sécurité communs. Les pages invités, les dossiers privés et les photos
 * ne doivent jamais être indexés par les moteurs de recherche ni transmettre leur
 * adresse (qui contient un lien privé) aux sites externes.
 */
class SecurityHeaders
{
    private const PRIVATE_PATHS = ['e/*', 'd/*', 'media/*'];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $headers = $response->headers;

        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'SAMEORIGIN');
        $headers->set('Permissions-Policy', 'camera=(self), microphone=(), geolocation=()');

        if ($request->is(self::PRIVATE_PATHS)) {
            $headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
            $headers->set('Referrer-Policy', 'no-referrer');
        } else {
            $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        }

        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}

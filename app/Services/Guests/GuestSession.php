<?php

namespace App\Services\Guests;

use App\Models\ConsentLog;
use App\Models\Event;
use App\Models\Guest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * Invité anonyme reconnu par un cookie (aucun compte requis).
 * Le jeton n'est stocké qu'haché en base.
 */
class GuestSession
{
    public const COOKIE = 'tm_guest';

    private const DAYS = 365;

    /**
     * Invité résolu, mémorisé par jeton : le service ne garde jamais l'invité d'une autre requête.
     *
     * @var array{0: string, 1: Guest}|null
     */
    private ?array $resolved = null;

    private function request(): Request
    {
        return request();
    }

    private function token(): ?string
    {
        $token = $this->request()->cookie(self::COOKIE);

        return is_string($token) && strlen($token) === 40 ? $token : null;
    }

    public function current(): ?Guest
    {
        $token = $this->token();
        if (! $token) {
            return null;
        }

        if ($this->resolved && hash_equals($this->resolved[0], $token)) {
            return $this->resolved[1];
        }

        $guest = Guest::where('token_hash', hash('sha256', $token))->first();
        $this->resolved = $guest ? [$token, $guest] : null;

        return $guest;
    }

    public function ensure(): Guest
    {
        if ($guest = $this->current()) {
            return $guest;
        }

        $token = Str::random(40);
        $guest = Guest::create([
            'token_hash' => hash('sha256', $token),
            'locale' => app()->getLocale(),
            'last_seen_at' => now(),
        ]);

        // Le cookie est posé sur la réponse ; la requête en cours voit déjà le nouvel invité.
        Cookie::queue(Cookie::make(self::COOKIE, $token, self::DAYS * 24 * 60, httpOnly: true, sameSite: 'lax'));
        $this->request()->cookies->set(self::COOKIE, $token);
        $this->resolved = [$token, $guest];

        return $guest;
    }

    public function hasConsented(Event $event): bool
    {
        return (bool) $this->current()?->hasConsentedTo($event);
    }

    public function recordConsent(Event $event): Guest
    {
        $guest = $this->ensure();

        if (! $guest->hasConsentedTo($event)) {
            ConsentLog::create([
                'guest_id' => $guest->id,
                'event_id' => $event->id,
                'notice_version' => config('trouvmoi.consent_notice_version'),
                'ip_hash' => hash('sha256', $this->request()->ip().config('app.key')),
                'accepted_at' => now(),
            ]);
        }

        return $guest;
    }

    /** Événements protégés par PIN déjà déverrouillés dans cette session. */
    public function unlock(Event $event): void
    {
        $this->request()->session()->put("pin_ok.{$event->id}", true);
    }

    public function isUnlocked(Event $event): bool
    {
        return (bool) $this->request()->session()->get("pin_ok.{$event->id}", false);
    }
}

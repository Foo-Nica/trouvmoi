<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Invité anonyme, reconnu par un cookie ; numéro de téléphone facultatif. */
#[Fillable(['token_hash', 'phone', 'locale', 'last_seen_at'])]
#[Hidden(['token_hash'])]
class Guest extends Model
{
    use HasUlids;

    protected function casts(): array
    {
        return ['last_seen_at' => 'datetime'];
    }

    public function folders(): HasMany
    {
        return $this->hasMany(Folder::class);
    }

    public function consents(): HasMany
    {
        return $this->hasMany(ConsentLog::class);
    }

    public function hasConsentedTo(Event $event): bool
    {
        return $this->consents()
            ->where('event_id', $event->id)
            ->where('notice_version', config('trouvmoi.consent_notice_version'))
            ->exists();
    }
}

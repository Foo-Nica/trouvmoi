<?php

namespace App\Models;

use App\Enums\AccessMode;
use App\Enums\EventStatus;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

#[Fillable([
    'user_id', 'title', 'event_date', 'location', 'description', 'cover_path', 'access_mode', 'status',
    'watermark_enabled', 'has_minors', 'retention_days',
])]
#[Hidden(['pin_hash'])]
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory, HasUlids;

    protected $attributes = [
        'access_mode' => 'gallery',
        'status' => 'draft',
        'watermark_enabled' => true,
        'has_minors' => false,
        'retention_days' => 60,
        'photos_count' => 0,
        'faces_count' => 0,
        'scans_count' => 0,
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'access_mode' => AccessMode::class,
            'status' => EventStatus::class,
            'watermark_enabled' => 'boolean',
            'has_minors' => 'boolean',
            'retention_days' => 'integer',
            'expires_at' => 'datetime',
            'faces_purged_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Event $event): void {
            $event->slug ??= static::uniqueSlug();
        });

        static::saving(function (Event $event): void {
            $start = $event->event_date ?? $event->created_at ?? now();
            $event->expires_at = $start->copy()->startOfDay()->addDays($event->retention_days ?: 60);
        });
    }

    /** Slug court, sans caractères ambigus (0/o, 1/l), pour le QR et l'URL publique. */
    public static function uniqueSlug(int $length = 8): string
    {
        $alphabet = 'abcdefghijkmnpqrstuvwxyz23456789';
        do {
            $slug = '';
            for ($i = 0; $i < $length; $i++) {
                $slug .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (static::where('slug', $slug)->exists());

        return $slug;
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_members')->withPivot('role')->withTimestamps();
    }

    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class);
    }

    public function faces(): HasMany
    {
        return $this->hasMany(Face::class);
    }

    public function folders(): HasMany
    {
        return $this->hasMany(Folder::class);
    }

    public function searches(): HasMany
    {
        return $this->hasMany(FaceSearch::class);
    }

    public function downloads(): HasMany
    {
        return $this->hasMany(Download::class);
    }

    public function removalRequests(): HasMany
    {
        return $this->hasMany(RemovalRequest::class);
    }

    /** Événements que l'utilisateur possède ou sur lesquels il est membre. */
    public function scopeAccessibleBy(Builder $query, User $user): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->where('user_id', $user->id)
            ->orWhereHas('members', fn (Builder $m) => $m->where('users.id', $user->id)));
    }

    public function isAccessibleBy(User $user): bool
    {
        return $this->user_id === $user->id
            || $user->isAdmin()
            || $this->members()->whereKey($user->id)->exists();
    }

    public function isOnline(): bool
    {
        return $this->status === EventStatus::Published && ! $this->isExpired();
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /** L'indexation faciale est désactivée pour les événements avec des mineurs. */
    public function faceSearchEnabled(): bool
    {
        return ! $this->has_minors && $this->faces_purged_at === null;
    }

    public function setPin(?string $pin): void
    {
        $this->pin_hash = $pin ? Hash::make($pin) : null;
    }

    public function checkPin(string $pin): bool
    {
        return $this->pin_hash !== null && Hash::check($pin, $this->pin_hash);
    }

    public function publicUrl(): string
    {
        return route('event.show', $this->slug);
    }

    public function shortTitle(int $limit = 40): string
    {
        return Str::limit($this->title, $limit);
    }
}

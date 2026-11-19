<?php

namespace App\Models;

use App\Enums\AccessMode;
use App\Enums\EventStatus;
use App\Enums\EventType;
use App\Enums\OrderStatus;
use App\Jobs\DeleteEventFiles;
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
    'user_id', 'title', 'type', 'client_name', 'event_date', 'location', 'description', 'cover_path', 'access_mode', 'status',
    'watermark_enabled', 'original_price', 'pack_price', 'has_minors', 'retention_days',
])]
#[Hidden(['pin_hash'])]
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory, HasUlids;

    protected $attributes = [
        'type' => 'public',
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
            'type' => EventType::class,
            'access_mode' => AccessMode::class,
            'original_price' => 'integer',
            'pack_price' => 'integer',
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

        // Les fichiers sont supprimés en arrière-plan une fois l'événement effacé.
        static::deleted(fn (Event $event) => DeleteEventFiles::dispatch($event->id, $event->cover_path));

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

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function paidOrders(): HasMany
    {
        return $this->hasMany(Order::class)->where('status', OrderStatus::Paid);
    }

    public function isClient(): bool
    {
        return $this->type === EventType::Client;
    }

    /** Événement public : la version avec logo se télécharge gratuitement. */
    public function allowsFreeDownload(): bool
    {
        return ! $this->isClient();
    }

    /** Les aperçus d'un événement « personnalité » portent toujours le logo. */
    public function watermarksPreviews(): bool
    {
        return $this->watermark_enabled || $this->isClient();
    }

    /** Prix de la version originale d'une photo (FCFA) : celui de la photo, sinon celui de l'événement. */
    public function priceFor(Photo $photo): int
    {
        return (int) ($photo->price ?? $this->original_price ?? 0);
    }

    /** La version originale d'une photo est-elle disponible (payante ou offerte) ? */
    public function offersOriginal(Photo $photo): bool
    {
        return $this->isClient() || $this->priceFor($photo) > 0;
    }

    /** Prix de toutes les photos en une fois (personnalité), null si non proposé. */
    public function packOffer(): ?int
    {
        return $this->isClient() && $this->pack_price > 0 ? $this->pack_price : null;
    }

    /** Total encaissé pour l'événement (commandes payées). */
    public function revenue(): int
    {
        return (int) $this->orders()->where('status', OrderStatus::Paid)->sum('amount');
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

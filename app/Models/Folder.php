<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

/** Dossier personnel d'un invité : ses photos, accessibles par un lien privé. */
#[Fillable(['event_id', 'guest_id', 'name', 'query_face_ids', 'refreshed_at'])]
#[Hidden(['access_token'])]
class Folder extends Model
{
    use HasUlids;

    protected function casts(): array
    {
        return ['query_face_ids' => 'array', 'refreshed_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(fn (Folder $folder) => $folder->access_token ??= Str::random(40));
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function photos(): BelongsToMany
    {
        return $this->belongsToMany(Photo::class)->withPivot(['similarity', 'source', 'excluded'])->withTimestamps();
    }

    /** Photos retenues (sans celles marquées « Ce n'est pas moi »). */
    public function keptPhotos(): BelongsToMany
    {
        return $this->photos()->wherePivot('excluded', false)->where('photos.is_visible', true);
    }

    public function privateUrl(): string
    {
        return route('folder.show', $this->access_token);
    }
}

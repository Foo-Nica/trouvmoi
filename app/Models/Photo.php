<?php

namespace App\Models;

use App\Enums\PhotoStatus;
use Database\Factories\PhotoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

#[Fillable([
    'event_id', 'uploaded_by', 'original_name', 'original_path', 'preview_path', 'thumb_path',
    'width', 'height', 'size_bytes', 'checksum', 'status', 'faces_count', 'is_visible', 'error', 'taken_at',
])]
class Photo extends Model
{
    /** @use HasFactory<PhotoFactory> */
    use HasFactory, HasUlids;

    protected function casts(): array
    {
        return [
            'status' => PhotoStatus::class,
            'is_visible' => 'boolean',
            'taken_at' => 'datetime',
            'width' => 'integer',
            'height' => 'integer',
            'size_bytes' => 'integer',
            'faces_count' => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function faces(): HasMany
    {
        return $this->hasMany(Face::class);
    }

    public function folders(): BelongsToMany
    {
        return $this->belongsToMany(Folder::class)->withPivot(['similarity', 'source', 'excluded'])->withTimestamps();
    }

    public static function disk(): string
    {
        return config('trouvmoi.photos_disk');
    }

    /** Lien temporaire et signé vers une version de la photo (jamais d'URL publique devinable). */
    public function temporaryUrl(string $variant = 'thumb', int $minutes = 60): ?string
    {
        $path = match ($variant) {
            'original' => $this->original_path,
            'download' => $this->download_path,
            'preview' => $this->preview_path,
            default => $this->thumb_path,
        };

        if (! $path) {
            return null;
        }

        $disk = Storage::disk(static::disk());
        $expires = now()->addMinutes($minutes);

        return $disk->providesTemporaryUrls()
            ? $disk->temporaryUrl($path, $expires)
            : URL::temporarySignedRoute('media.show', $expires, ['photo' => $this->id, 'variant' => $variant]);
    }
}

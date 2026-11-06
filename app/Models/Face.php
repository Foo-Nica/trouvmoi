<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Cadre d'un visage sur une photo. L'empreinte reste dans le service IA. */
#[Fillable(['photo_id', 'event_id', 'external_id', 'x', 'y', 'w', 'h', 'det_score', 'sharpness'])]
class Face extends Model
{
    protected function casts(): array
    {
        return ['x' => 'integer', 'y' => 'integer', 'w' => 'integer', 'h' => 'integer'];
    }

    public function photo(): BelongsTo
    {
        return $this->belongsTo(Photo::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}

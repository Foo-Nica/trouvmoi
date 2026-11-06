<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['event_id', 'guest_id', 'folder_id', 'type', 'photos_count'])]
class Download extends Model
{
    public const UPDATED_AT = null;

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}

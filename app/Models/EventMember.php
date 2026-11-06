<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class EventMember extends Pivot
{
    protected $table = 'event_members';

    public $incrementing = true;
}

<?php

namespace App\Http\Controllers\Studio;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\Qr\EventQrCode;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EventQrController extends Controller
{
    public function __invoke(Event $event, string $format, EventQrCode $qr): Response
    {
        abort_unless($event->isAccessibleBy(request()->user()), 403);

        $result = $qr->make($event, $format);
        $filename = 'qr-'.Str::slug($event->title).'.'.$format;

        return response($result->getString(), 200, [
            'Content-Type' => $result->getMimeType(),
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}

<?php

namespace App\Services\Qr;

use App\Models\Event;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\Result\ResultInterface;
use Endroid\QrCode\Writer\SvgWriter;

/** Code QR de la page invités d'un événement, aux couleurs de TrouvMoi. */
class EventQrCode
{
    public function make(Event $event, string $format = 'png', int $size = 1200): ResultInterface
    {
        $logo = public_path('images/qr-logo.png');

        return (new Builder(
            writer: $format === 'svg' ? new SvgWriter : new PngWriter,
            data: $event->publicUrl(),
            // Correction élevée : le QR reste lisible avec un logo au centre ou imprimé petit.
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: $size,
            margin: (int) round($size / 20),
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
            foregroundColor: new Color(43, 42, 107),
            backgroundColor: new Color(255, 255, 255),
            logoPath: is_file($logo) ? $logo : '',
            logoResizeToWidth: is_file($logo) ? (int) round($size * 0.2) : null,
            logoPunchoutBackground: is_file($logo),
        ))->build();
    }
}

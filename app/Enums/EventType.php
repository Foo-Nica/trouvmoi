<?php

namespace App\Enums;

enum EventType: string
{
    /** Événement ouvert : téléchargement gratuit avec logo, version originale payante. */
    case Public = 'public';
    /** Prestation pour une personnalité : tout téléchargement est payant. */
    case Client = 'client';

    public function label(): string
    {
        return match ($this) {
            self::Public => 'Événement public',
            self::Client => 'Personnalité (client privé)',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Public => 'Les invités retrouvent et téléchargent gratuitement leurs photos avec le logo TRECHART. La version originale, sans filigrane, est payante si un prix est défini.',
            self::Client => 'Photos d\'une personnalité qui a engagé TRECHART. Les aperçus portent le logo ; chaque téléchargement en version originale est payant.',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $type) => [$type->value => $type->label()])->all();
    }
}

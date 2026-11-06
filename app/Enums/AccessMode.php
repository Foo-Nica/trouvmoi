<?php

namespace App\Enums;

enum AccessMode: string
{
    /** L'invité parcourt la galerie et touche son visage (mode par défaut). */
    case Gallery = 'gallery';
    /** L'invité prend un selfie ; la galerie complète n'est jamais visible. */
    case Selfie = 'selfie';
    /** Galerie protégée par un code PIN en plus du QR. */
    case Pin = 'pin';

    public function label(): string
    {
        return match ($this) {
            self::Gallery => 'Galerie ouverte',
            self::Selfie => 'Selfie uniquement',
            self::Pin => 'Galerie + code PIN',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Gallery => 'Toute personne ayant le QR voit la galerie et choisit une photo où elle apparaît.',
            self::Selfie => "L'invité prend un selfie et ne voit que ses propres photos. Recommandé pour les événements privés.",
            self::Pin => "Comme la galerie ouverte, mais un code PIN est demandé à l'entrée.",
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $m) => [$m->value => $m->label()])->all();
    }
}

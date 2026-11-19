<?php

namespace App\Enums;

enum UserRole: string
{
    case Photographer = 'photographer';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Photographer => 'Équipe',
            self::Admin => 'Administrateur',
        };
    }
}

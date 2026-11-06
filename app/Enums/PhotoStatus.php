<?php

namespace App\Enums;

enum PhotoStatus: string
{
    case Received = 'received';
    case Processed = 'processed';
    case Indexed = 'indexed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Received => 'Reçue',
            self::Processed => 'Traitée',
            self::Indexed => 'Indexée',
            self::Failed => 'Erreur',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Received => 'gray',
            self::Processed => 'info',
            self::Indexed => 'success',
            self::Failed => 'danger',
        };
    }
}

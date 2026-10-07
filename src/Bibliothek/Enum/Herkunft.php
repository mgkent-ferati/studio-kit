<?php

declare(strict_types=1);

namespace App\Bibliothek\Enum;

enum Herkunft: string
{
    case Upload = 'upload';
    case MusikUnterlegt = 'musik_unterlegt';
    case Import = 'import';

    public function label(): string
    {
        return match ($this) {
            self::Upload => 'Hochgeladen',
            self::MusikUnterlegt => 'Musik unterlegt',
            self::Import => 'Aus Clip Locker übernommen',
        };
    }
}

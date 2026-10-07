<?php

declare(strict_types=1);

namespace App\Bibliothek\Enum;

enum DateiTyp: string
{
    case Video = 'video';
    case Rive = 'rive';
    case Bild = 'bild';
    case Photoshop = 'photoshop';
    case Audio = 'audio';
    case Sonstiges = 'sonstiges';

    public function label(): string
    {
        return match ($this) {
            self::Video => 'Video',
            self::Rive => 'Rive',
            self::Bild => 'Bild',
            self::Photoshop => 'Photoshop',
            self::Audio => 'Audio',
            self::Sonstiges => 'Sonstiges',
        };
    }
}

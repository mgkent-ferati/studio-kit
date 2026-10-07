<?php

declare(strict_types=1);

namespace App\Bibliothek;

use App\Bibliothek\Enum\DateiTyp;

final class DateiTypeDetector
{
    private const array VIDEO_ENDUNGEN = ['mp4', 'webm', 'mov', 'm4v'];
    private const array AUDIO_ENDUNGEN = ['mp3', 'wav', 'm4a', 'aac', 'ogg', 'flac'];
    private const array BILD_ENDUNGEN = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'];

    public function detect(string $originalname, ?string $mimeTyp): DateiTyp
    {
        $endung = mb_strtolower(pathinfo($originalname, \PATHINFO_EXTENSION));
        $mime = (string) $mimeTyp;

        return match (true) {
            'riv' === $endung => DateiTyp::Rive,
            'psd' === $endung, 'image/vnd.adobe.photoshop' === $mime => DateiTyp::Photoshop,
            str_starts_with($mime, 'video/'), in_array($endung, self::VIDEO_ENDUNGEN, true) => DateiTyp::Video,
            str_starts_with($mime, 'audio/'), in_array($endung, self::AUDIO_ENDUNGEN, true) => DateiTyp::Audio,
            str_starts_with($mime, 'image/'), in_array($endung, self::BILD_ENDUNGEN, true) => DateiTyp::Bild,
            default => DateiTyp::Sonstiges,
        };
    }
}

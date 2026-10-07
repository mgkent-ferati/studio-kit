<?php

declare(strict_types=1);

namespace App\Tests\Unit\Bibliothek;

use App\Bibliothek\DateiTypeDetector;
use App\Bibliothek\Enum\DateiTyp;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DateiTypeDetectorTest extends TestCase
{
    /** @return iterable<string, array{string, ?string, DateiTyp}> */
    public static function faelle(): iterable
    {
        yield 'riv' => ['faey.riv', 'application/octet-stream', DateiTyp::Rive];
        yield 'psd per Endung' => ['hintergrund.PSD', 'application/octet-stream', DateiTyp::Photoshop];
        yield 'psd per MIME' => ['ohne-endung', 'image/vnd.adobe.photoshop', DateiTyp::Photoshop];
        yield 'mp4' => ['clip.mp4', 'video/mp4', DateiTyp::Video];
        yield 'webm ohne MIME' => ['clip.webm', null, DateiTyp::Video];
        yield 'mp3' => ['song.mp3', 'audio/mpeg', DateiTyp::Audio];
        yield 'png' => ['logo.png', 'image/png', DateiTyp::Bild];
        yield 'svg' => ['logo.svg', 'image/svg+xml', DateiTyp::Bild];
        yield 'pdf' => ['briefing.pdf', 'application/pdf', DateiTyp::Sonstiges];
    }

    #[DataProvider('faelle')]
    public function testErkenntTyp(string $name, ?string $mime, DateiTyp $erwartet): void
    {
        self::assertSame($erwartet, (new DateiTypeDetector())->detect($name, $mime));
    }
}

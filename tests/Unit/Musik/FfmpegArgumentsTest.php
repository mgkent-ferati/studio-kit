<?php

declare(strict_types=1);

namespace App\Tests\Unit\Musik;

use App\Musik\FfmpegArguments;
use App\Musik\MediaInfo;
use PHPUnit\Framework\TestCase;

final class FfmpegArgumentsTest extends TestCase
{
    public function testH264MitTonUndOriginalBehaltenMischtUndKopiertVideo(): void
    {
        $args = FfmpegArguments::musikUnterlegen('/v.mp4', '/t.mp3', '/ziel', new MediaInfo(8.0, 'h264', true), 1.0, 0.0, true);

        self::assertSame([
            'ffmpeg', '-v', 'error', '-y',
            '-i', '/v.mp4',
            '-ss', '0', '-i', '/t.mp3',
            '-filter_complex', '[1:a]atrim=0:8,asetpts=PTS-STARTPTS,volume=1,afade=t=out:st=6.000:d=2.000[m];[0:a][m]amix=inputs=2:duration=first:normalize=0[a]',
            '-map', '0:v:0', '-map', '[a]',
            '-c:v', 'copy',
            '-c:a', 'aac', '-b:a', '192k',
            '-t', '8',
            '-movflags', '+faststart',
            '-f', 'mp4', '/ziel',
        ], $args);
    }

    public function testStartMittenImSongBlendetEinUndKurzesVideoBlendetKuerzerAus(): void
    {
        $args = FfmpegArguments::musikUnterlegen('/v.mp4', '/t.mp3', '/ziel', new MediaInfo(2.5, 'h264', true), 0.6, 12.5, false);

        self::assertSame('12.5', $args[7]);
        self::assertSame('[1:a]atrim=0:2.5,asetpts=PTS-STARTPTS,volume=0.6,afade=t=in:st=0:d=0.3,afade=t=out:st=1.875:d=0.625[a]', $args[11]);
    }

    public function testOriginaltonOhneTonspurNutztNurMusik(): void
    {
        $args = FfmpegArguments::musikUnterlegen('/v.mp4', '/t.mp3', '/ziel', new MediaInfo(4.0, 'h264', false), 1.0, 0.0, true);

        self::assertStringNotContainsString('amix', $args[11]);
        self::assertStringEndsWith('[a]', $args[11]);
    }

    public function testAnderesFormatWirdNachH264Umkodiert(): void
    {
        $args = FfmpegArguments::musikUnterlegen('/v.webm', '/t.mp3', '/ziel', new MediaInfo(4.0, 'vp9', true), 1.0, 0.0, true);

        $index = array_search('-c:v', $args, true);
        self::assertSame(['-c:v', 'libx264', '-crf', '20', '-preset', 'medium', '-pix_fmt', 'yuv420p'], array_slice($args, (int) $index, 8));
    }
}

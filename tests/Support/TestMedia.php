<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Symfony\Component\Process\Process;

/** Erzeugt kleine Testmedien per ffmpeg lavfi – keine echten Mediendateien im Repo. */
final class TestMedia
{
    private const string VERZEICHNIS = '/tmp/studio-kit-test-media';

    public static function video(float $sekunden = 2.0, bool $mitTon = true, string $codec = 'libx264'): string
    {
        $ziel = sprintf('%s/video-%s-%s-%s.mp4', self::VERZEICHNIS, $sekunden, $mitTon ? 'ton' : 'stumm', $codec);
        $args = ['-f', 'lavfi', '-i', sprintf('testsrc=size=320x240:rate=25:duration=%s', $sekunden)];
        if ($mitTon) {
            $args = [...$args, '-f', 'lavfi', '-i', sprintf('sine=frequency=440:duration=%s', $sekunden), '-c:a', 'aac'];
        }
        $args = [...$args, '-c:v', $codec, '-pix_fmt', 'yuv420p', '-shortest'];

        return self::erzeugen($ziel, $args);
    }

    public static function audio(float $sekunden = 3.0): string
    {
        $ziel = sprintf('%s/audio-%s.mp3', self::VERZEICHNIS, $sekunden);

        return self::erzeugen($ziel, ['-f', 'lavfi', '-i', sprintf('sine=frequency=660:duration=%s', $sekunden), '-c:a', 'libmp3lame']);
    }

    public static function bild(): string
    {
        return self::erzeugen(self::VERZEICHNIS.'/bild.png', ['-f', 'lavfi', '-i', 'color=c=pink:size=64x64', '-frames:v', '1']);
    }

    public static function datei(string $name, string $inhalt): string
    {
        self::verzeichnis();
        $ziel = self::VERZEICHNIS.'/'.$name;
        file_put_contents($ziel, $inhalt);

        return $ziel;
    }

    /** @param list<string> $args */
    private static function erzeugen(string $ziel, array $args): string
    {
        if (is_file($ziel)) {
            return $ziel;
        }
        self::verzeichnis();
        (new Process(['ffmpeg', '-v', 'error', '-y', ...$args, $ziel]))->mustRun();

        return $ziel;
    }

    private static function verzeichnis(): void
    {
        if (!is_dir(self::VERZEICHNIS)) {
            mkdir(self::VERZEICHNIS, 0777, true);
        }
    }
}

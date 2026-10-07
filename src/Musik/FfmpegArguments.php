<?php

declare(strict_types=1);

namespace App\Musik;

/** Portiert aus dem Clip Locker (server.js), Verhalten 1:1. */
final class FfmpegArguments
{
    /** @return list<string> */
    public static function musikUnterlegen(
        string $videoPfad,
        string $tonPfad,
        string $zielPfad,
        MediaInfo $video,
        float $lautstaerke,
        float $startSekunde,
        bool $originalBehalten,
    ): array {
        // Musik exakt auf Videolänge schneiden, am Ende ausblenden, bei Einstieg mitten im Song kurz einblenden.
        $dauer = $video->dauer;
        $ausblenden = min(2.0, $dauer / 4);
        $musik = array_filter([
            'atrim=0:'.self::zahl($dauer),
            'asetpts=PTS-STARTPTS',
            'volume='.self::zahl($lautstaerke),
            $startSekunde > 0 ? 'afade=t=in:st=0:d=0.3' : null,
            sprintf('afade=t=out:st=%.3F:d=%.3F', $dauer - $ausblenden, $ausblenden),
        ]);
        $kette = implode(',', $musik);
        $filter = $originalBehalten && $video->hatTon
            ? "[1:a]{$kette}[m];[0:a][m]amix=inputs=2:duration=first:normalize=0[a]"
            : "[1:a]{$kette}[a]";

        // H.264 unverändert übernehmen (schnell, verlustfrei); sonst neu kodieren, weil TikTok/Instagram/Facebook H.264 in MP4 erwarten.
        $videoArgs = 'h264' === $video->videoCodec
            ? ['-c:v', 'copy']
            : ['-c:v', 'libx264', '-crf', '20', '-preset', 'medium', '-pix_fmt', 'yuv420p'];

        return [
            'ffmpeg', '-v', 'error', '-y',
            '-i', $videoPfad,
            '-ss', self::zahl($startSekunde), '-i', $tonPfad,
            '-filter_complex', $filter,
            '-map', '0:v:0', '-map', '[a]',
            ...$videoArgs,
            '-c:a', 'aac', '-b:a', '192k',
            '-t', self::zahl($dauer),
            '-movflags', '+faststart',
            '-f', 'mp4', $zielPfad,
        ];
    }

    /** Wie JavaScripts String(zahl): 8.0 → "8", 0.6 → "0.6". */
    private static function zahl(float $wert): string
    {
        return rtrim(rtrim(sprintf('%.6F', $wert), '0'), '.');
    }
}

<?php

declare(strict_types=1);

namespace App\Musik;

use Symfony\Component\Process\Process;

final class MediaProbe
{
    public function probe(string $pfad): MediaInfo
    {
        $process = new Process(['ffprobe', '-v', 'error', '-print_format', 'json', '-show_format', '-show_streams', $pfad]);
        $process->setTimeout(30);
        $process->run();
        if (!$process->isSuccessful()) {
            return new MediaInfo(0.0, null, false);
        }
        /** @var array{format?: array{duration?: string}, streams?: list<array{codec_type?: string, codec_name?: string}>} $daten */
        $daten = json_decode($process->getOutput(), true) ?: [];
        $videoCodec = null;
        $hatTon = false;
        foreach ($daten['streams'] ?? [] as $stream) {
            if ('video' === ($stream['codec_type'] ?? null) && null === $videoCodec) {
                $videoCodec = $stream['codec_name'] ?? null;
            }
            if ('audio' === ($stream['codec_type'] ?? null)) {
                $hatTon = true;
            }
        }

        return new MediaInfo((float) ($daten['format']['duration'] ?? 0), $videoCodec, $hatTon);
    }
}

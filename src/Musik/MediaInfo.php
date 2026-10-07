<?php

declare(strict_types=1);

namespace App\Musik;

final readonly class MediaInfo
{
    public function __construct(
        public float $dauer,
        public ?string $videoCodec,
        public bool $hatTon,
    ) {
    }
}

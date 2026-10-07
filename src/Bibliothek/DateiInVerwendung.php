<?php

declare(strict_types=1);

namespace App\Bibliothek;

use App\Bibliothek\Entity\Datei;
use App\Mappe\Entity\Mappe;

final class DateiInVerwendung extends \RuntimeException
{
    /** @param list<Mappe> $mappen */
    public function __construct(Datei $datei, private readonly array $mappen)
    {
        parent::__construct(sprintf(
            '„%s“ steckt noch in: %s',
            $datei->getName(),
            implode(', ', array_map(static fn (Mappe $m): string => $m->getName(), $mappen)),
        ));
    }

    /** @return list<Mappe> */
    public function getMappen(): array
    {
        return $this->mappen;
    }
}

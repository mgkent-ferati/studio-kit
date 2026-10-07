<?php

declare(strict_types=1);

namespace App\Tests\Unit\Mappe;

use App\Mappe\ZipEntryNames;
use PHPUnit\Framework\TestCase;

final class ZipEntryNamesTest extends TestCase
{
    public function testDoppelteNamenBekommenEinSuffix(): void
    {
        self::assertSame(
            ['logo.png', 'logo (2).png', 'Logo (3).PNG', 'song.mp3', 'README', 'README (2)'],
            ZipEntryNames::eindeutig(['logo.png', 'logo.png', 'Logo.PNG', 'song.mp3', 'README', 'README']),
        );
    }
}

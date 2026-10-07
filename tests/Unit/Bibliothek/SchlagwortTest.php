<?php

declare(strict_types=1);

namespace App\Tests\Unit\Bibliothek;

use App\Bibliothek\Entity\Schlagwort;
use PHPUnit\Framework\TestCase;

final class SchlagwortTest extends TestCase
{
    public function testAusTextNormalisiertUndEntferntDoppelte(): void
    {
        self::assertSame(['faey', 'maskottchen', 'folge-1', '2026'], Schlagwort::ausText(' Faey, Maskottchen ,, folge-1, FAEY, 2026 '));
    }

    public function testLeererTextErgibtKeineSchlagwoerter(): void
    {
        self::assertSame([], Schlagwort::ausText('  ,  '));
    }
}

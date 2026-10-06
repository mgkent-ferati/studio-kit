<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Tests\Support\StudioKitKernelTestCase;
use Doctrine\DBAL\Connection;

final class TestumgebungTest extends StudioKitKernelTestCase
{
    public function testTestumgebungNutztTemporaereAblage(): void
    {
        self::assertSame(self::TEST_ABLAGE, self::getContainer()->getParameter('app.dateien_verzeichnis'));
    }

    public function testTestumgebungNutztTestdatenbank(): void
    {
        $connection = self::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $connection);
        self::assertSame('app_test', $connection->getDatabase());
    }
}

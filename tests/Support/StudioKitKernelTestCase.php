<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;

abstract class StudioKitKernelTestCase extends KernelTestCase
{
    public const string TEST_ABLAGE = '/tmp/studio-kit-test-daten/dateien';

    protected function setUp(): void
    {
        self::bootKernel();
        $verzeichnis = self::getContainer()->getParameter('app.dateien_verzeichnis');
        if (self::TEST_ABLAGE !== $verzeichnis) {
            self::fail(sprintf('Abbruch: Test-Ablage ist "%s" statt "%s".', is_string($verzeichnis) ? $verzeichnis : get_debug_type($verzeichnis), self::TEST_ABLAGE));
        }
        (new Filesystem())->remove(self::TEST_ABLAGE);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $id
     *
     * @return T
     */
    protected static function service(string $id): object
    {
        $service = self::getContainer()->get($id);
        self::assertInstanceOf($id, $service);

        return $service;
    }
}

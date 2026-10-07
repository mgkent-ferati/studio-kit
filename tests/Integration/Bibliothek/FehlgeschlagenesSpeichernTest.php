<?php

declare(strict_types=1);

namespace App\Tests\Integration\Bibliothek;

use App\Bibliothek\DateiManager;
use App\Bibliothek\DateiTypeDetector;
use App\Bibliothek\Entity\Datei;
use App\Bibliothek\Enum\DateiTyp;
use App\Bibliothek\FassungStorage;
use App\Bibliothek\Repository\DateiRepository;
use App\Mappe\Repository\MappeRepository;
use App\Tests\Support\StudioKitKernelTestCase;
use App\Tests\Support\TestMedia;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\Config;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\File\File;

final class FehlgeschlagenesSpeichernTest extends StudioKitKernelTestCase
{
    public function testFehlerBeimSpeichernHinterlaesstNichts(): void
    {
        $adapter = new class(self::TEST_ABLAGE) extends LocalFilesystemAdapter {
            public function move(string $source, string $destination, Config $config): void
            {
                throw new \RuntimeException('Umbenennen fehlgeschlagen');
            }
        };
        $manager = $this->manager(new Filesystem($adapter), self::service(EntityManagerInterface::class));
        $datei = new Datei('Clip', DateiTyp::Bild);

        try {
            $manager->neueFassung($datei, new File(TestMedia::bild()), 'clip.png');
            self::fail('RuntimeException erwartet');
        } catch (\RuntimeException $e) {
            self::assertSame('Umbenennen fehlgeschlagen', $e->getMessage());
        }

        self::assertNull($datei->aktuelleFassung());
        self::assertSame([], $this->dateienImOrdner());
        self::assertNull(self::service(DateiRepository::class)->find($datei->getId()));
    }

    public function testFehlerBeimFlushEntferntDateiUndFassungWieder(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('flush')->willThrowException(new \RuntimeException('Datenbank weg'));
        $storage = new Filesystem(new LocalFilesystemAdapter(self::TEST_ABLAGE));
        $manager = $this->manager($storage, $em);
        $datei = new Datei('Clip', DateiTyp::Bild);

        try {
            $manager->neueFassung($datei, new File(TestMedia::bild()), 'clip.png');
            self::fail('RuntimeException erwartet');
        } catch (\RuntimeException $e) {
            self::assertSame('Datenbank weg', $e->getMessage());
        }

        self::assertNull($datei->aktuelleFassung());
        self::assertSame([], $this->dateienImOrdner());
    }

    private function manager(Filesystem $filesystem, EntityManagerInterface $em): DateiManager
    {
        return new DateiManager(
            $em,
            new FassungStorage($filesystem, self::TEST_ABLAGE),
            new DateiTypeDetector(),
            self::service(MappeRepository::class),
        );
    }

    /** @return list<string> */
    private function dateienImOrdner(): array
    {
        if (!is_dir(self::TEST_ABLAGE)) {
            return [];
        }

        return array_map(
            static fn (\SplFileInfo $f): string => $f->getPathname(),
            iterator_to_array((new Finder())->files()->in(self::TEST_ABLAGE), false),
        );
    }
}

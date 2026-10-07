<?php

declare(strict_types=1);

namespace App\Tests\Integration\Bibliothek;

use App\Bibliothek\DateiInVerwendung;
use App\Bibliothek\DateiManager;
use App\Bibliothek\Enum\DateiTyp;
use App\Bibliothek\Enum\Herkunft;
use App\Bibliothek\FassungStorage;
use App\Bibliothek\Repository\DateiRepository;
use App\Mappe\Entity\Mappe;
use App\Tests\Support\StudioKitKernelTestCase;
use App\Tests\Support\TestMedia;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\File;

final class DateiManagerTest extends StudioKitKernelTestCase
{
    private DateiManager $manager;
    private FassungStorage $storage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = self::service(DateiManager::class);
        $this->storage = self::service(FassungStorage::class);
    }

    public function testHochladenLegtDateiMitErsterFassungUndInhaltAn(): void
    {
        $quelle = TestMedia::bild();

        $datei = $this->manager->hochladen(new File($quelle), 'Faey freigestellt.png');

        self::assertSame('Faey freigestellt', $datei->getName());
        self::assertSame(DateiTyp::Bild, $datei->getTyp());
        $fassung = $datei->aktuelleFassung();
        self::assertNotNull($fassung);
        self::assertSame(1, $fassung->getNummer());
        self::assertSame(hash_file('sha256', $quelle), $fassung->getSha256());
        self::assertSame(filesize($quelle), $fassung->getGroesse());
        self::assertSame(Herkunft::Upload, $fassung->getHerkunft());
        self::assertFileEquals($quelle, $this->storage->absolutePath($fassung));
        self::assertFileDoesNotExist($this->storage->absolutePath($fassung).'.part');
    }

    public function testNeueFassungZaehltHoch(): void
    {
        $datei = $this->manager->hochladen(new File(TestMedia::video(1.0)), 'Clip.mp4');

        $zweite = $this->manager->neueFassung($datei, new File(TestMedia::video(1.5)), 'Clip v2.mp4');

        self::assertSame(2, $zweite->getNummer());
        self::assertSame($zweite, $datei->aktuelleFassung());
        self::assertFileExists($this->storage->absolutePath($zweite));
    }

    public function testErstelltAmKannVorgegebenWerden(): void
    {
        $zeit = new \DateTimeImmutable('2026-09-01 12:00:00');

        $datei = $this->manager->hochladen(new File(TestMedia::bild()), 'alt.png', null, Herkunft::Import, $zeit);

        self::assertEquals($zeit, $datei->getErstelltAm());
        self::assertEquals($zeit, $datei->aktuelleFassung()?->getErstelltAm());
    }

    public function testLoeschenEntferntDateiUndInhalt(): void
    {
        $datei = $this->manager->hochladen(new File(TestMedia::bild()), 'weg.png');
        $pfad = $this->storage->absolutePath($datei->aktuelleFassung() ?? self::fail());
        $id = $datei->getId();

        $this->manager->loeschen($datei);

        self::assertNull(self::service(DateiRepository::class)->find($id));
        self::assertFileDoesNotExist($pfad);
    }

    public function testLoeschenIstGesperrtSolangeEineMappeDieDateiNutzt(): void
    {
        $datei = $this->manager->hochladen(new File(TestMedia::bild()), 'logo.png');
        $mappe = new Mappe('Oktober');
        $mappe->bestandteilHinzufuegen($datei);
        $em = self::service(EntityManagerInterface::class);
        $em->persist($mappe);
        $em->flush();

        try {
            $this->manager->loeschen($datei);
            self::fail('DateiInVerwendung erwartet');
        } catch (DateiInVerwendung $e) {
            self::assertSame([$mappe], $e->getMappen());
            self::assertSame('„logo“ steckt noch in: Oktober', $e->getMessage());
        }
        self::assertFileExists($this->storage->absolutePath($datei->aktuelleFassung() ?? self::fail()));
    }
}

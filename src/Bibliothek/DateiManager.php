<?php

declare(strict_types=1);

namespace App\Bibliothek;

use App\Bibliothek\Entity\Datei;
use App\Bibliothek\Entity\Fassung;
use App\Bibliothek\Enum\Herkunft;
use App\Mappe\Repository\MappeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\File;

final readonly class DateiManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private FassungStorage $storage,
        private DateiTypeDetector $typeDetector,
        private MappeRepository $mappen,
    ) {
    }

    public function hochladen(
        File $file,
        string $originalname,
        ?string $name = null,
        Herkunft $herkunft = Herkunft::Upload,
        ?\DateTimeImmutable $erstelltAm = null,
    ): Datei {
        $datei = new Datei(
            $name ?? pathinfo(Fassung::sichererName($originalname), \PATHINFO_FILENAME),
            $this->typeDetector->detect($originalname, $file->getMimeType()),
            $erstelltAm,
        );
        $this->fassungAnlegen($datei, $file, $originalname, $herkunft, null, null, $erstelltAm);

        return $datei;
    }

    public function neueFassung(
        Datei $datei,
        File $file,
        string $originalname,
        Herkunft $herkunft = Herkunft::Upload,
        ?Fassung $quelleVideo = null,
        ?Fassung $quelleAudio = null,
        ?\DateTimeImmutable $erstelltAm = null,
    ): Fassung {
        return $this->fassungAnlegen($datei, $file, $originalname, $herkunft, $quelleVideo, $quelleAudio, $erstelltAm);
    }

    public function loeschen(Datei $datei): void
    {
        $mappen = $this->mappen->findByDatei($datei);
        if ([] !== $mappen) {
            throw new DateiInVerwendung($datei, $mappen);
        }
        $this->em->remove($datei);
        $this->em->flush();
        $this->storage->dateiEntfernen($datei);
    }

    private function fassungAnlegen(
        Datei $datei,
        File $file,
        string $originalname,
        Herkunft $herkunft,
        ?Fassung $quelleVideo,
        ?Fassung $quelleAudio,
        ?\DateTimeImmutable $erstelltAm,
    ): Fassung {
        $sha256 = hash_file('sha256', $file->getPathname());
        if (false === $sha256) {
            throw new \RuntimeException(sprintf('„%s“ ist nicht lesbar.', $originalname));
        }
        $fassung = $datei->neueFassung(
            $originalname,
            (int) $file->getSize(),
            $file->getMimeType() ?? 'application/octet-stream',
            $sha256,
            $herkunft,
            $erstelltAm,
            $quelleVideo,
            $quelleAudio,
        );
        try {
            $this->storage->speichern($fassung, $file->getPathname());
        } catch (\Throwable $e) {
            $datei->fassungVerwerfen($fassung);
            throw $e;
        }
        try {
            $this->em->persist($datei);
            $this->em->flush();
        } catch (\Throwable $e) {
            $datei->fassungVerwerfen($fassung);
            $this->storage->entfernen($fassung);
            throw $e;
        }

        return $fassung;
    }
}

<?php

declare(strict_types=1);

namespace App\Bibliothek;

use App\Bibliothek\Entity\Datei;
use App\Bibliothek\Entity\Fassung;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Einzige Stelle mit Dateisystem-Wissen. absolutePath() setzt den Local-Adapter voraus
 * (für ffmpeg und Range-Downloads) – bei einem Cloud-Speicher hier anpassen.
 */
final readonly class FassungStorage
{
    public function __construct(
        private FilesystemOperator $dateienStorage,
        #[Autowire('%app.dateien_verzeichnis%')]
        private string $basisVerzeichnis,
    ) {
    }

    /** Schreibt erst nach „.part“ und benennt dann um – nie eine halbe Datei unter dem echten Namen. */
    public function speichern(Fassung $fassung, string $quellPfad): void
    {
        $stream = fopen($quellPfad, 'r');
        if (false === $stream) {
            throw new \RuntimeException(sprintf('„%s“ ist nicht lesbar.', $quellPfad));
        }
        $teil = $fassung->getPfad().'.part';
        try {
            $this->dateienStorage->writeStream($teil, $stream);
            $this->dateienStorage->move($teil, $fassung->getPfad());
        } catch (\Throwable $e) {
            try {
                $this->dateienStorage->delete($teil);
            } catch (\Throwable) {
                // Aufräumen nach bestem Wissen; der ursprüngliche Fehler zählt.
            }
            throw $e;
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    public function entfernen(Fassung $fassung): void
    {
        $this->dateienStorage->delete($fassung->getPfad());
    }

    public function dateiEntfernen(Datei $datei): void
    {
        $this->dateienStorage->deleteDirectory($datei->getId()->toRfc4122());
    }

    public function existiert(Fassung $fassung): bool
    {
        return $this->dateienStorage->fileExists($fassung->getPfad());
    }

    public function absolutePath(Fassung $fassung): string
    {
        return $this->basisVerzeichnis.'/'.$fassung->getPfad();
    }
}

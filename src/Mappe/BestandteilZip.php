<?php

declare(strict_types=1);

namespace App\Mappe;

use App\Bibliothek\FassungStorage;
use App\Mappe\Entity\Mappe;

final readonly class BestandteilZip
{
    public function __construct(private FassungStorage $storage)
    {
    }

    /** @return string Pfad einer temporären ZIP-Datei – der Aufrufer löscht sie nach dem Senden */
    public function erstellen(Mappe $mappe): string
    {
        $fassungen = [];
        foreach ($mappe->getBestandteile() as $bestandteil) {
            $fassung = $bestandteil->getDatei()->aktuelleFassung();
            if (null !== $fassung) {
                $fassungen[] = $fassung;
            }
        }
        if ([] === $fassungen) {
            throw new \RuntimeException('Die Mappe hat keine Bestandteile.');
        }
        $namen = ZipEntryNames::eindeutig(array_map(static fn ($f): string => $f->getOriginalname(), $fassungen));
        $ziel = tempnam(sys_get_temp_dir(), 'mappe');
        if (false === $ziel) {
            throw new \RuntimeException('Temporäre Datei für das ZIP ließ sich nicht anlegen.');
        }
        try {
            $zip = new \ZipArchive();
            if (true !== $zip->open($ziel, \ZipArchive::OVERWRITE)) {
                throw new \RuntimeException('ZIP ließ sich nicht anlegen.');
            }
            foreach ($fassungen as $index => $fassung) {
                $pfad = $this->storage->absolutePath($fassung);
                if (!is_file($pfad)) {
                    $zip->close();

                    throw new \RuntimeException(sprintf('„%s“ fehlt im Speicher.', $fassung->getDatei()->getName()));
                }
                if (!$zip->addFile($pfad, $namen[$index])) {
                    $zip->close();

                    throw new \RuntimeException(sprintf('„%s“ ließ sich nicht ins ZIP aufnehmen.', $fassung->getDatei()->getName()));
                }
                $zip->setCompressionName($namen[$index], \ZipArchive::CM_STORE);
            }
            if (!$zip->close()) {
                throw new \RuntimeException('ZIP ließ sich nicht schreiben.');
            }
        } catch (\Throwable $e) {
            if (is_file($ziel)) {
                unlink($ziel);
            }

            throw $e;
        }

        return $ziel;
    }
}

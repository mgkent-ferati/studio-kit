<?php

declare(strict_types=1);

namespace App\Musik;

use App\Bibliothek\DateiManager;
use App\Bibliothek\Entity\Datei;
use App\Bibliothek\Entity\Fassung;
use App\Bibliothek\Enum\DateiTyp;
use App\Bibliothek\Enum\Herkunft;
use App\Bibliothek\FassungStorage;
use App\Mappe\Entity\Mappe;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

final readonly class MusikUnterleger
{
    private const int TIMEOUT_SEKUNDEN = 120;

    public function __construct(
        private MediaProbe $probe,
        private FassungStorage $storage,
        private DateiManager $dateiManager,
    ) {
    }

    public function unterlegen(Mappe $mappe, Datei $audio, float $lautstaerke, float $startSekunde, bool $originalBehalten): Fassung
    {
        $ergebnis = $mappe->getErgebnis();
        $video = $ergebnis?->aktuelleFassung();
        if (null === $ergebnis || null === $video || DateiTyp::Video !== $ergebnis->getTyp()) {
            throw new MusikUnterlegenFehlgeschlagen('Die Mappe hat noch kein Video als Ergebnis.');
        }
        $ton = $audio->aktuelleFassung();
        if (null === $ton || DateiTyp::Audio !== $audio->getTyp()) {
            throw new MusikUnterlegenFehlgeschlagen('Bitte eine Audio-Datei wählen.');
        }
        if ($lautstaerke < 0 || $lautstaerke > 2 || $startSekunde < 0) {
            throw new MusikUnterlegenFehlgeschlagen('Lautstärke muss zwischen 0 und 2 liegen, die Startposition darf nicht negativ sein.');
        }
        $videoPfad = $this->storage->absolutePath($video);
        $info = $this->probe->probe($videoPfad);
        if ($info->dauer <= 0) {
            throw new MusikUnterlegenFehlgeschlagen('Länge des Videos nicht lesbar.');
        }
        $ziel = tempnam(sys_get_temp_dir(), 'musik');
        if (false === $ziel) {
            throw new MusikUnterlegenFehlgeschlagen('Temporäre Datei ließ sich nicht anlegen.');
        }
        try {
            $process = new Process(FfmpegArguments::musikUnterlegen(
                $videoPfad,
                $this->storage->absolutePath($ton),
                $ziel,
                $info,
                $lautstaerke,
                $startSekunde,
                $originalBehalten,
            ));
            $process->setTimeout(self::TIMEOUT_SEKUNDEN);
            $process->run();
            if (!$process->isSuccessful()) {
                $zeilen = array_slice(explode("\n", trim($process->getErrorOutput())), -3);
                throw new MusikUnterlegenFehlgeschlagen('ffmpeg ist fehlgeschlagen: '.implode(' ', $zeilen));
            }

            return $this->dateiManager->neueFassung(
                $ergebnis,
                new File($ziel),
                sprintf('%s + %s.mp4', pathinfo($video->getOriginalname(), \PATHINFO_FILENAME), pathinfo($ton->getOriginalname(), \PATHINFO_FILENAME)),
                Herkunft::MusikUnterlegt,
                $video,
                $ton,
            );
        } catch (ProcessTimedOutException) {
            throw new MusikUnterlegenFehlgeschlagen(sprintf('ffmpeg hat länger als %d Sekunden gebraucht und wurde abgebrochen.', self::TIMEOUT_SEKUNDEN));
        } finally {
            (new Filesystem())->remove($ziel);
        }
    }
}

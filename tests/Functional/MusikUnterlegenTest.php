<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Bibliothek\DateiManager;
use App\Bibliothek\Entity\Datei;
use App\Bibliothek\Entity\Fassung;
use App\Bibliothek\Enum\Herkunft;
use App\Bibliothek\FassungStorage;
use App\Mappe\Entity\Mappe;
use App\Mappe\Repository\MappeRepository;
use App\Musik\MediaInfo;
use App\Musik\MediaProbe;
use App\Musik\MusikUnterleger;
use App\Tests\Support\StudioKitWebTestCase;
use App\Tests\Support\TestMedia;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\File;

final class MusikUnterlegenTest extends StudioKitWebTestCase
{
    public function testMusikUnterlegenErzeugtNeueErgebnisFassung(): void
    {
        $mappe = $this->mappeMit(TestMedia::video(2.0, true, 'libx264'));
        $form = $this->client->request('GET', '/mappen/'.$mappe->getId())->selectButton('Musik unterlegen')->form();
        $form['lautstaerke'] = '0.8';
        $form['start'] = '0.5';

        $this->client->submit($form);

        self::assertContains('Musik unterlegt – Ergebnis Fassung 2.', $this->flashes());
        $ergebnis = $this->ergebnis($mappe);
        $neu = $ergebnis->aktuelleFassung() ?? self::fail();
        self::assertSame(2, $neu->getNummer());
        self::assertSame(Herkunft::MusikUnterlegt, $neu->getHerkunft());
        self::assertSame(1, $neu->getQuelleVideo()?->getNummer());
        self::assertSame('audio-3.mp3', $neu->getQuelleAudio()?->getOriginalname());
        self::assertSame('video-2-ton-libx264 + audio-3.mp4', $neu->getOriginalname());
        $info = $this->probe($neu);
        self::assertEqualsWithDelta(2.0, $info->dauer, 0.15);
        self::assertSame('h264', $info->videoCodec);
        self::assertTrue($info->hatTon);
    }

    public function testVideoOhneTonBekommtMusik(): void
    {
        $mappe = $this->mappeMit(TestMedia::video(2.0, false, 'libx264'));
        $form = $this->client->request('GET', '/mappen/'.$mappe->getId())->selectButton('Musik unterlegen')->form();

        $this->client->submit($form);

        $neu = $this->ergebnis($mappe)->aktuelleFassung() ?? self::fail();
        self::assertSame(2, $neu->getNummer());
        self::assertTrue($this->probe($neu)->hatTon);
    }

    public function testNichtH264WirdUmkodiert(): void
    {
        $mappe = $this->mappeMit(TestMedia::video(2.0, true, 'mpeg4'));
        $form = $this->client->request('GET', '/mappen/'.$mappe->getId())->selectButton('Musik unterlegen')->form();

        $this->client->submit($form);

        $neu = $this->ergebnis($mappe)->aktuelleFassung() ?? self::fail();
        self::assertSame('h264', $this->probe($neu)->videoCodec);
    }

    public function testOhneVideoErgebnisGibtEsEineVerstaendlicheMeldung(): void
    {
        $manager = self::service(DateiManager::class);
        $mappe = new Mappe('Ohne Ergebnis');
        $mappe->bestandteilHinzufuegen($manager->hochladen(new File(TestMedia::audio()), 'song.mp3'));
        $em = self::service(EntityManagerInterface::class);
        $em->persist($mappe);
        $em->flush();
        $crawler = $this->client->request('GET', '/mappen/'.$mappe->getId());

        self::assertSelectorTextContains('.ergebnis', 'Zum Musikunterlegen braucht die Mappe ein Video als Ergebnis');
        self::assertCount(0, $crawler->selectButton('Musik unterlegen'));
    }

    public function testUngueltigeZahlLegtKeineFassungAn(): void
    {
        $mappe = $this->mappeMit(TestMedia::video(2.0, true, 'libx264'));
        $form = $this->client->request('GET', '/mappen/'.$mappe->getId())->selectButton('Musik unterlegen')->form();
        $form['lautstaerke'] = 'abc';

        $this->client->submit($form);

        self::assertContains('Lautstärke und Startposition müssen Zahlen sein.', $this->flashes());
        self::assertSame(1, $this->ergebnis($mappe)->aktuelleFassung()?->getNummer());
    }

    public function testDezimalkommaWirdAkzeptiert(): void
    {
        self::assertSame(1.5, MusikUnterleger::zahl('1,5'));
    }

    public function testFremdeAudioDateiWirdAbgelehnt(): void
    {
        $mappe = $this->mappeMit(TestMedia::video(2.0, true, 'libx264'));
        $fremd = self::service(DateiManager::class)->hochladen(new File(TestMedia::audio(4.0)), 'fremd.mp3');
        $form = $this->client->request('GET', '/mappen/'.$mappe->getId())->selectButton('Musik unterlegen')->form();
        $form['audio_id']->disableValidation();
        $form['audio_id'] = (string) $fremd->getId();

        $this->client->submit($form);

        self::assertContains('Bitte einen Audio-Bestandteil dieser Mappe wählen.', $this->flashes());
        self::assertSame(1, $this->ergebnis($mappe)->aktuelleFassung()?->getNummer());
    }

    public function testVideoMitUnlesbarerLaengeLegtKeineFassungAn(): void
    {
        $mappe = $this->mappeMit(TestMedia::datei('kaputt.mp4', 'kein video'));
        $form = $this->client->request('GET', '/mappen/'.$mappe->getId())->selectButton('Musik unterlegen')->form();

        $this->client->submit($form);

        self::assertContains('Länge des Videos nicht lesbar.', $this->flashes());
        self::assertSame(1, $this->ergebnis($mappe)->aktuelleFassung()?->getNummer());
    }

    public function testFfmpegFehlerHinterlaesstKeineTempDatei(): void
    {
        $mappe = $this->mappeMit(TestMedia::video(2.0, true, 'libx264'));
        $kaputt = self::service(DateiManager::class)->hochladen(new File(TestMedia::datei('kaputt.mp3', 'kein audio')), 'kaputt.mp3');
        $mappe->bestandteilHinzufuegen($kaputt);
        self::service(EntityManagerInterface::class)->flush();
        $form = $this->client->request('GET', '/mappen/'.$mappe->getId())->selectButton('Musik unterlegen')->form();
        $form['audio_id']->select((string) $kaputt->getId());
        $vorher = glob(sys_get_temp_dir().'/musik*') ?: [];

        $this->client->submit($form);

        self::assertNotEmpty(array_filter($this->flashes(), static fn (string $f): bool => str_contains($f, 'ffmpeg ist fehlgeschlagen')));
        self::assertSame($vorher, glob(sys_get_temp_dir().'/musik*') ?: []);
        self::assertSame(1, $this->ergebnis($mappe)->aktuelleFassung()?->getNummer());
    }

    private function mappeMit(string $video): Mappe
    {
        $manager = self::service(DateiManager::class);
        $mappe = new Mappe('Oktober');
        $mappe->setErgebnis($manager->hochladen(new File($video), basename($video), 'Oktober'));
        $mappe->bestandteilHinzufuegen($manager->hochladen(new File(TestMedia::audio()), 'audio-3.mp3'));
        $em = self::service(EntityManagerInterface::class);
        $em->persist($mappe);
        $em->flush();

        return $mappe;
    }

    private function ergebnis(Mappe $mappe): Datei
    {
        self::service(EntityManagerInterface::class)->clear();
        $neu = self::service(MappeRepository::class)->find($mappe->getId());

        return $neu?->getErgebnis() ?? self::fail('Kein Ergebnis');
    }

    private function probe(Fassung $fassung): MediaInfo
    {
        return self::service(MediaProbe::class)->probe(self::service(FassungStorage::class)->absolutePath($fassung));
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit\Bibliothek;

use App\Bibliothek\Entity\Datei;
use App\Bibliothek\Entity\Fassung;
use App\Bibliothek\Enum\DateiTyp;
use App\Bibliothek\Enum\Herkunft;
use PHPUnit\Framework\TestCase;

final class DateiTest extends TestCase
{
    public function testFassungenWerdenFortlaufendNummeriert(): void
    {
        $datei = new Datei('Oktober mit Fee', DateiTyp::Video);

        $erste = $datei->neueFassung('a.mp4', 10, 'video/mp4', str_repeat('a', 64), Herkunft::Upload);
        $zweite = $datei->neueFassung('b.mp4', 20, 'video/mp4', str_repeat('b', 64), Herkunft::Upload);

        self::assertSame(1, $erste->getNummer());
        self::assertSame(2, $zweite->getNummer());
        self::assertSame($zweite, $datei->aktuelleFassung());
        self::assertSame([$erste, $zweite], $datei->getFassungen());
    }

    public function testOhneFassungGibtEsKeineAktuelle(): void
    {
        self::assertNull((new Datei('leer', DateiTyp::Bild))->aktuelleFassung());
    }

    public function testPfadBestehtAusDateiIdNummerUndName(): void
    {
        $datei = new Datei('Logo', DateiTyp::Bild);
        $fassung = $datei->neueFassung('Faey freigestellt.png', 1, 'image/png', str_repeat('c', 64), Herkunft::Upload);

        self::assertSame($datei->getId()->toRfc4122().'/1-Faey freigestellt.png', $fassung->getPfad());
    }

    public function testPfadEnthaeltKeinePfadanteile(): void
    {
        self::assertSame('passwd', Fassung::sichererName('../../etc/passwd'));
        self::assertSame('böse.png', Fassung::sichererName('C:\\Users\\x\\böse.png'));
        self::assertSame('versteckt', Fassung::sichererName('.versteckt'));
        self::assertSame('datei', Fassung::sichererName('..'));
        self::assertSame('Faey v2 – final.mp4', Fassung::sichererName("Faey v2 – final.mp4\x00"));
    }

    public function testQuellfassungenWerdenGemerkt(): void
    {
        $video = new Datei('Video', DateiTyp::Video);
        $v1 = $video->neueFassung('v.mp4', 1, 'video/mp4', str_repeat('d', 64), Herkunft::Upload);
        $ton = new Datei('Ton', DateiTyp::Audio);
        $t1 = $ton->neueFassung('t.mp3', 1, 'audio/mpeg', str_repeat('e', 64), Herkunft::Upload);

        $v2 = $video->neueFassung('v + t.mp4', 1, 'video/mp4', str_repeat('f', 64), Herkunft::MusikUnterlegt, null, $v1, $t1);

        self::assertSame($v1, $v2->getQuelleVideo());
        self::assertSame($t1, $v2->getQuelleAudio());
        self::assertSame(Herkunft::MusikUnterlegt, $v2->getHerkunft());
    }
}

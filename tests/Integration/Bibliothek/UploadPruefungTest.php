<?php

declare(strict_types=1);

namespace App\Tests\Integration\Bibliothek;

use App\Bibliothek\DateiManager;
use App\Bibliothek\UploadPruefung;
use App\Tests\Support\StudioKitKernelTestCase;
use App\Tests\Support\TestMedia;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;

final class UploadPruefungTest extends StudioKitKernelTestCase
{
    public function testNeueDateiIstInOrdnung(): void
    {
        $pruefung = self::service(UploadPruefung::class);

        self::assertNull($pruefung->pruefen(new UploadedFile(TestMedia::bild(), 'bild.png', null, null, true), false));
    }

    public function testDuplikatWirdGemeldetAusserMitTrotzdem(): void
    {
        self::service(DateiManager::class)->hochladen(new File(TestMedia::bild()), 'Logo.png');
        $pruefung = self::service(UploadPruefung::class);
        $upload = new UploadedFile(TestMedia::bild(), 'kopie.png', null, null, true);

        self::assertSame(
            '„kopie.png“ gibt es schon als „Logo“ (Fassung 1) – nicht angelegt. Zum Anlegen „trotzdem anlegen“ ankreuzen und erneut hochladen.',
            $pruefung->pruefen($upload, false),
        );
        self::assertNull($pruefung->pruefen($upload, true));
    }

    public function testFehlgeschlagenerUploadWirdGemeldet(): void
    {
        $upload = new UploadedFile(TestMedia::bild(), 'gross.mp4', null, \UPLOAD_ERR_INI_SIZE, true);

        self::assertStringStartsWith('„gross.mp4“: ', (string) self::service(UploadPruefung::class)->pruefen($upload, false));
    }

    public function testAnfrageOhneInhaltAberMitLaengeGiltAlsZuGross(): void
    {
        $pruefung = self::service(UploadPruefung::class);

        self::assertTrue($pruefung->anfrageZuGross(Request::create('/dateien', 'POST', server: ['CONTENT_LENGTH' => '3000000000'])));
        self::assertFalse($pruefung->anfrageZuGross(Request::create('/dateien', 'POST', ['_token' => 'x'], server: ['CONTENT_LENGTH' => '10'])));
    }
}

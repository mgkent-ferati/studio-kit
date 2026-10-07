<?php

declare(strict_types=1);

namespace App\Tests\Integration\Bibliothek;

use App\Bibliothek\Entity\Datei;
use App\Bibliothek\Enum\DateiTyp;
use App\Bibliothek\Enum\Herkunft;
use App\Bibliothek\Repository\DateiRepository;
use App\Bibliothek\Repository\FassungRepository;
use App\Bibliothek\Repository\SchlagwortRepository;
use App\Mappe\Entity\Mappe;
use App\Mappe\Repository\MappeRepository;
use App\Tests\Support\StudioKitKernelTestCase;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

final class RepositoryTest extends StudioKitKernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        parent::setUp();
        $this->em = self::service(EntityManagerInterface::class);
    }

    public function testSucheFiltertNachTypSchlagwortVorlageUndText(): void
    {
        $schlagwoerter = self::service(SchlagwortRepository::class);
        $logo = $this->datei('Faey Logo', DateiTyp::Bild);
        $logo->setVorlage(true);
        $logo->setSchlagwoerter($schlagwoerter->holeOderErzeuge(['faey', 'maskottchen']));
        $this->datei('Oktober 100%', DateiTyp::Video);
        $this->em->flush();
        $repo = self::service(DateiRepository::class);

        self::assertSame([$logo], $repo->suche(typ: DateiTyp::Bild));
        self::assertSame([$logo], $repo->suche(schlagwort: ' FAEY '));
        self::assertSame([$logo], $repo->suche(vorlage: true));
        self::assertSame([$logo], $repo->suche(text: 'logo'));
        self::assertCount(1, $repo->suche(text: '100%'));
        self::assertCount(1, $repo->suche(text: '%'), '% ist ein normales Zeichen, kein Platzhalter – nur „Oktober 100%“.');
        self::assertCount(0, $repo->suche(text: '_'), '_ ist ein normales Zeichen, kein Platzhalter.');
        self::assertCount(2, $repo->suche());
    }

    public function testHoleOderErzeugeLegtNurFehlendeAn(): void
    {
        $repo = self::service(SchlagwortRepository::class);
        $erste = $repo->holeOderErzeuge(['faey']);
        $this->em->flush();

        $zweite = $repo->holeOderErzeuge(['faey', 'neu']);
        $this->em->flush();

        self::assertSame($erste[0], $zweite[0]);
        self::assertSame(['faey', 'neu'], array_map(static fn ($s) => $s->getName(), $repo->alle()));
    }

    public function testFassungLaesstSichPerPruefsummeFinden(): void
    {
        $datei = $this->datei('Clip', DateiTyp::Video);
        $this->em->flush();

        $treffer = self::service(FassungRepository::class)->findOneBySha256(str_repeat('a', 64));

        self::assertSame($datei->aktuelleFassung(), $treffer);
    }

    public function testFassungsnummerIstJeDateiEindeutig(): void
    {
        $this->datei('Clip', DateiTyp::Video);
        $this->em->flush();
        $this->expectException(UniqueConstraintViolationException::class);

        $this->em->getConnection()->executeStatement(
            'INSERT INTO fassung (datei_id, nummer, originalname, groesse, mime_typ, sha256, pfad, herkunft, erstellt_am)
             SELECT datei_id, nummer, originalname, groesse, mime_typ, sha256, pfad, herkunft, erstellt_am FROM fassung',
        );
    }

    public function testMappenZuEinerDateiUmfassenBestandteilUndErgebnis(): void
    {
        $datei = $this->datei('Logo', DateiTyp::Bild);
        $mitBestandteil = new Mappe('B');
        $mitBestandteil->bestandteilHinzufuegen($datei);
        $mitErgebnis = new Mappe('A');
        $mitErgebnis->setErgebnis($datei);
        $ohne = new Mappe('C');
        foreach ([$mitBestandteil, $mitErgebnis, $ohne] as $mappe) {
            $this->em->persist($mappe);
        }
        $this->em->flush();

        $mappen = self::service(MappeRepository::class)->findByDatei($datei);

        self::assertSame([$mitErgebnis, $mitBestandteil], $mappen);
    }

    private function datei(string $name, DateiTyp $typ): Datei
    {
        $datei = new Datei($name, $typ);
        $datei->neueFassung($name.'.bin', 1, 'application/octet-stream', str_repeat('a', 64), Herkunft::Upload);
        $this->em->persist($datei);

        return $datei;
    }
}

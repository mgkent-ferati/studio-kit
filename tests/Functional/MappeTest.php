<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Bibliothek\DateiManager;
use App\Bibliothek\Entity\Datei;
use App\Mappe\Entity\Mappe;
use App\Mappe\Repository\MappeRepository;
use App\Tests\Support\StudioKitWebTestCase;
use App\Tests\Support\TestMedia;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\File;

final class MappeTest extends StudioKitWebTestCase
{
    public function testMappeAnlegenUndBestandteilAusBibliothekHinzufuegen(): void
    {
        $logo = $this->datei(TestMedia::bild(), 'Faey Logo.png');
        $form = $this->client->request('GET', '/mappen')->selectButton('Mappe anlegen')->form();
        $form['name'] = 'Oktober mit Fee';
        $this->client->submit($form);
        self::assertSelectorTextContains('h1', 'Oktober mit Fee');

        $form = $this->client->getCrawler()->selectButton('Hinzufügen')->form();
        $this->checkboxField($form, 'datei_id')->select((string) $logo->getId());
        $this->client->submit($form);

        self::assertSelectorTextContains('.bestandteil', 'Faey Logo');
    }

    public function testBestandteilDirektHochladenLegtDateiInBibliothekAn(): void
    {
        $mappe = $this->mappe('Oktober');
        $form = $this->client->request('GET', '/mappen/'.$mappe->getId())->selectButton('Hochladen und hinzufügen')->form();
        $this->fileField($form, 'datei')->upload(TestMedia::audio());

        $this->client->submit($form);

        self::assertSelectorTextContains('.bestandteil', 'audio-3');
        $this->client->request('GET', '/?typ=audio');
        self::assertSelectorCount(1, '.kachel');
    }

    public function testErgebnisHochladenErzeugtFassungen(): void
    {
        $mappe = $this->mappe('Oktober');
        foreach ([1.0, 1.5] as $sekunden) {
            $form = $this->client->request('GET', '/mappen/'.$mappe->getId())->selectButton('Ergebnis hochladen')->form();
            $this->fileField($form, 'datei')->upload(TestMedia::video($sekunden));
            $this->client->submit($form);
        }

        self::assertSelectorTextContains('.ergebnis', 'Fassung 2');
        $ergebnis = self::service(MappeRepository::class)->find($mappe->getId())?->getErgebnis();
        self::assertSame('Oktober', $ergebnis?->getName());
    }

    public function testZipEnthaeltAlleBestandteileMitEindeutigenNamen(): void
    {
        $mappe = $this->mappe('Oktober');
        $mappe->bestandteilHinzufuegen($this->datei(TestMedia::bild(), 'logo.png'));
        $mappe->bestandteilHinzufuegen($this->datei(TestMedia::datei('anders.png', 'x'), 'logo.png'));
        $mappe->bestandteilHinzufuegen($this->datei(TestMedia::audio(), 'song.mp3'));
        self::service(EntityManagerInterface::class)->flush();

        $this->client->request('GET', '/mappen/'.$mappe->getId().'/zip');

        self::assertResponseIsSuccessful();
        $response = $this->client->getResponse();
        self::assertInstanceOf(BinaryFileResponse::class, $response);
        // deleteFileAfterSend: Die Datei ist nach dem Senden weg, der Test-Client hat den Inhalt aber mitgeschnitten.
        $kopie = tempnam(sys_get_temp_dir(), 'mappetest');
        self::assertNotFalse($kopie);
        file_put_contents($kopie, $this->client->getInternalResponse()->getContent());
        $zip = new \ZipArchive();
        self::assertTrue($zip->open($kopie));
        $namen = [];
        for ($i = 0; $i < $zip->numFiles; ++$i) {
            $namen[] = $zip->getNameIndex($i);
        }
        $zip->close();
        unlink($kopie);
        self::assertSame(['logo.png', 'logo (2).png', 'song.mp3'], $namen);
    }

    public function testZipOhneBestandteileZeigtHinweis(): void
    {
        $mappe = $this->mappe('Leer');

        $this->client->request('GET', '/mappen/'.$mappe->getId().'/zip');

        self::assertContains('Die Mappe hat keine Bestandteile.', $this->flashes());
    }

    public function testVerschiebenUndEntfernen(): void
    {
        $mappe = $this->mappe('Oktober');
        $mappe->bestandteilHinzufuegen($this->datei(TestMedia::bild(), 'a.png'));
        $mappe->bestandteilHinzufuegen($this->datei(TestMedia::audio(), 'b.mp3'));
        self::service(EntityManagerInterface::class)->flush();

        $crawler = $this->client->request('GET', '/mappen/'.$mappe->getId());
        $this->client->submit($crawler->filter('.bestandteil')->eq(1)->selectButton('↑')->form());
        self::assertSelectorTextContains('.bestandteil:first-child', 'b');

        $this->client->submit($this->client->getCrawler()->filter('.bestandteil')->eq(0)->selectButton('Entfernen')->form());
        self::assertSelectorCount(1, '.bestandteil');
    }

    public function testMappeLoeschenBehaeltDateien(): void
    {
        $mappe = $this->mappe('Oktober');
        $mappe->bestandteilHinzufuegen($this->datei(TestMedia::bild(), 'logo.png'));
        self::service(EntityManagerInterface::class)->flush();
        $form = $this->client->request('GET', '/mappen/'.$mappe->getId())->selectButton('Mappe löschen')->form();

        $this->client->submit($form);

        self::assertContains('Mappe „Oktober“ gelöscht.', $this->flashes());
        $this->client->request('GET', '/');
        self::assertSelectorCount(1, '.kachel');
    }

    public function testFilterNachSchlagwort(): void
    {
        $mappe = $this->mappe('Oktober');
        $form = $this->client->request('GET', '/mappen/'.$mappe->getId())->selectButton('Speichern')->form();
        $form['schlagwoerter'] = 'folge-1';
        $this->client->submit($form);
        $this->mappe('Andere');

        $this->client->request('GET', '/mappen?schlagwort=folge-1');

        self::assertSelectorCount(1, '.mappe');
    }

    private function datei(string $pfad, string $name): Datei
    {
        return self::service(DateiManager::class)->hochladen(new File($pfad), $name);
    }

    private function mappe(string $name): Mappe
    {
        $mappe = new Mappe($name);
        $em = self::service(EntityManagerInterface::class);
        $em->persist($mappe);
        $em->flush();

        return $mappe;
    }
}

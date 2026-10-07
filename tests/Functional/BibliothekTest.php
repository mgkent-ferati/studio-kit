<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Bibliothek\DateiManager;
use App\Bibliothek\Entity\Datei;
use App\Bibliothek\FassungStorage;
use App\Bibliothek\Repository\DateiRepository;
use App\Mappe\Entity\Mappe;
use App\Tests\Support\StudioKitWebTestCase;
use App\Tests\Support\TestMedia;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\File;

final class BibliothekTest extends StudioKitWebTestCase
{
    public function testHochladenMehrererDateienZeigtSieInDerBibliothek(): void
    {
        $crawler = $this->client->request('GET', '/');
        $form = $crawler->selectButton('Hochladen')->form();
        $this->fileField($form, 'dateien[0]')->upload(TestMedia::bild());
        $this->fileField($form, 'dateien[1]')->upload(TestMedia::datei('faey.riv', 'RIVE'));

        $this->client->submit($form);

        self::assertResponseIsSuccessful();
        self::assertContains('„bild“ hochgeladen.', $this->flashes());
        self::assertContains('„faey“ hochgeladen.', $this->flashes());
        self::assertSelectorCount(2, '.kachel');
    }

    public function testDuplikatWirdNichtAngelegt(): void
    {
        $this->dateiAnlegen(TestMedia::bild(), 'Logo.png');
        $form = $this->client->request('GET', '/')->selectButton('Hochladen')->form();
        $this->fileField($form, 'dateien[0]')->upload(TestMedia::bild());

        $this->client->submit($form);

        self::assertStringContainsString('gibt es schon als „Logo“', implode(' ', $this->flashes()));
        self::assertCount(1, self::service(DateiRepository::class)->findAll());
    }

    public function testZuGrosseAnfrageZeigtHinweis(): void
    {
        $this->client->request('POST', '/dateien', [], [], ['CONTENT_LENGTH' => '3000000000']);

        self::assertResponseIsSuccessful();
        self::assertContains('Die Anfrage war zu groß (über 2 GB) und ist nicht angekommen.', $this->flashes());
    }

    public function testFilterNachTyp(): void
    {
        $this->dateiAnlegen(TestMedia::bild(), 'Logo.png');
        $this->dateiAnlegen(TestMedia::audio(), 'Song.mp3');

        $this->client->request('GET', '/?typ=audio');

        self::assertSelectorCount(1, '.kachel');
        self::assertSelectorTextContains('.kachel', 'Song');
    }

    public function testDetailZeigtFassungenUndNeueFassungZaehltHoch(): void
    {
        $datei = $this->dateiAnlegen(TestMedia::video(1.0), 'Clip.mp4');
        $crawler = $this->client->request('GET', '/dateien/'.$datei->getId());
        $form = $crawler->selectButton('Neue Fassung hochladen')->form();
        $this->fileField($form, 'datei')->upload(TestMedia::video(1.5));

        $this->client->submit($form);

        self::assertSelectorCount(2, '.fassung');
        self::assertSelectorTextContains('.fassung-aktuell', 'Fassung 2');
    }

    public function testBearbeitenSetztNameSchlagwoerterUndVorlage(): void
    {
        $datei = $this->dateiAnlegen(TestMedia::bild(), 'logo.png');
        $form = $this->client->request('GET', '/dateien/'.$datei->getId())->selectButton('Speichern')->form();
        $form['name'] = 'Faey Logo';
        $form['schlagwoerter'] = 'Faey, maskottchen';
        $this->checkboxField($form, 'vorlage')->tick();

        $this->client->submit($form);

        self::assertSelectorTextContains('h1', 'Faey Logo');
        $this->client->request('GET', '/?schlagwort=faey&vorlage=1');
        self::assertSelectorCount(1, '.kachel');
    }

    public function testDownloadTraegtOriginalnamen(): void
    {
        $datei = $this->dateiAnlegen(TestMedia::bild(), 'Faey v2 – final.png');
        $fassung = $datei->aktuelleFassung() ?? self::fail();

        $this->client->request('GET', '/fassungen/'.$fassung->getId().'/download');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString("filename*=utf-8''Faey%20v2%20%E2%80%93%20final.png", (string) $this->client->getResponse()->headers->get('Content-Disposition'));
    }

    public function testFehlendeDateiImSpeicherGibt404(): void
    {
        $datei = $this->dateiAnlegen(TestMedia::bild(), 'weg.png');
        $fassung = $datei->aktuelleFassung() ?? self::fail();
        unlink(self::service(FassungStorage::class)->absolutePath($fassung));

        $this->client->request('GET', '/fassungen/'.$fassung->getId().'/download');

        self::assertResponseStatusCodeSame(404);
    }

    public function testLoeschenEinerVerwendetenDateiWirdVerweigert(): void
    {
        $datei = $this->dateiAnlegen(TestMedia::bild(), 'logo.png');
        $mappe = new Mappe('Oktober');
        $mappe->bestandteilHinzufuegen($datei);
        $em = self::service(EntityManagerInterface::class);
        $em->persist($mappe);
        $em->flush();
        $form = $this->client->request('GET', '/dateien/'.$datei->getId())->selectButton('Datei löschen')->form();

        $this->client->submit($form);

        self::assertContains('„logo“ steckt noch in: Oktober', $this->flashes());
        self::assertCount(1, self::service(DateiRepository::class)->findAll());
    }

    public function testLoeschenOhneTokenWirdAbgelehnt(): void
    {
        $datei = $this->dateiAnlegen(TestMedia::bild(), 'logo.png');

        $this->client->request('POST', '/dateien/'.$datei->getId().'/loeschen', ['_token' => 'falsch']);

        self::assertResponseStatusCodeSame(403);
        self::assertCount(1, self::service(DateiRepository::class)->findAll());
    }

    public function testLoeschenEntferntDatei(): void
    {
        $datei = $this->dateiAnlegen(TestMedia::bild(), 'logo.png');
        $form = $this->client->request('GET', '/dateien/'.$datei->getId())->selectButton('Datei löschen')->form();

        $this->client->submit($form);

        self::assertContains('„logo“ gelöscht.', $this->flashes());
        self::assertCount(0, self::service(DateiRepository::class)->findAll());
    }

    private function dateiAnlegen(string $pfad, string $name): Datei
    {
        return self::service(DateiManager::class)->hochladen(new File($pfad), $name);
    }
}

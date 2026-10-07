<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Field\ChoiceFormField;
use Symfony\Component\DomCrawler\Field\FileFormField;
use Symfony\Component\DomCrawler\Form;
use Symfony\Component\Filesystem\Filesystem;

abstract class StudioKitWebTestCase extends WebTestCase
{
    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->followRedirects();
        $verzeichnis = self::getContainer()->getParameter('app.dateien_verzeichnis');
        if (StudioKitKernelTestCase::TEST_ABLAGE !== $verzeichnis) {
            self::fail(sprintf('Abbruch: Test-Ablage ist "%s".', is_string($verzeichnis) ? $verzeichnis : get_debug_type($verzeichnis)));
        }
        (new Filesystem())->remove(StudioKitKernelTestCase::TEST_ABLAGE);
    }

    /** @return list<string> */
    protected function flashes(): array
    {
        return $this->client->getCrawler()->filter('.flash')->each(static fn ($n): string => trim($n->text()));
    }

    protected function fileField(Form $form, string $name): FileFormField
    {
        $field = $form->get($name);
        self::assertInstanceOf(FileFormField::class, $field);

        return $field;
    }

    protected function checkboxField(Form $form, string $name): ChoiceFormField
    {
        $field = $form->get($name);
        self::assertInstanceOf(ChoiceFormField::class, $field);

        return $field;
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $id
     *
     * @return T
     */
    protected static function service(string $id): object
    {
        $service = self::getContainer()->get($id);
        self::assertInstanceOf($id, $service);

        return $service;
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit\Mappe;

use App\Bibliothek\Entity\Datei;
use App\Bibliothek\Entity\Schlagwort;
use App\Bibliothek\Enum\DateiTyp;
use App\Mappe\Entity\Mappe;
use App\Mappe\Entity\MappeBestandteil;
use PHPUnit\Framework\TestCase;

final class MappeTest extends TestCase
{
    public function testBestandteileBehaltenReihenfolgeUndSindEindeutig(): void
    {
        $mappe = new Mappe('Oktober');
        $a = new Datei('a', DateiTyp::Bild);
        $b = new Datei('b', DateiTyp::Audio);

        $mappe->bestandteilHinzufuegen($a);
        $mappe->bestandteilHinzufuegen($b);
        $mappe->bestandteilHinzufuegen($a);

        self::assertSame([$a, $b], self::dateien($mappe));
    }

    public function testVerschiebenTauschtMitNachbarn(): void
    {
        $mappe = new Mappe('Oktober');
        $a = new Datei('a', DateiTyp::Bild);
        $b = new Datei('b', DateiTyp::Bild);
        $c = new Datei('c', DateiTyp::Bild);
        foreach ([$a, $b, $c] as $datei) {
            $mappe->bestandteilHinzufuegen($datei);
        }

        $mappe->bestandteilVerschieben($c, -1);
        self::assertSame([$a, $c, $b], self::dateien($mappe));

        $mappe->bestandteilVerschieben($a, -1);
        self::assertSame([$a, $c, $b], self::dateien($mappe), 'Am Anfang passiert nichts.');
    }

    public function testEntfernenNummeriertNeu(): void
    {
        $mappe = new Mappe('Oktober');
        $a = new Datei('a', DateiTyp::Bild);
        $b = new Datei('b', DateiTyp::Bild);
        $mappe->bestandteilHinzufuegen($a);
        $mappe->bestandteilHinzufuegen($b);

        $mappe->bestandteilEntfernen($a);

        self::assertSame([$b], self::dateien($mappe));
        self::assertSame(1, $mappe->getBestandteile()[0]->getPosition());
    }

    public function testAenderungenAnBestandteilenUndSchlagwoertern(): void
    {
        $mappe = new Mappe('Oktober');
        $a = new Datei('a', DateiTyp::Bild);
        $b = new Datei('b', DateiTyp::Bild);

        self::assertAdvances($mappe, static fn () => $mappe->bestandteilHinzufuegen($a));
        self::assertAdvances($mappe, static fn () => $mappe->bestandteilHinzufuegen($b));
        self::assertAdvances($mappe, static fn () => $mappe->bestandteilVerschieben($b, -1));
        self::assertAdvances($mappe, static fn () => $mappe->bestandteilEntfernen($a));
        self::assertAdvances($mappe, static fn () => $mappe->setSchlagwoerter([new Schlagwort('faey')]));
    }

    public function testNoOpsAendernDenZeitpunktNicht(): void
    {
        $mappe = new Mappe('Oktober');
        $a = new Datei('a', DateiTyp::Bild);
        $mappe->bestandteilHinzufuegen($a);

        $vorher = self::zurueckdatieren($mappe);
        $mappe->bestandteilHinzufuegen($a);
        $mappe->bestandteilVerschieben($a, -1);
        $mappe->bestandteilEntfernen(new Datei('fremd', DateiTyp::Bild));

        self::assertSame($vorher, $mappe->getGeaendertAm());
    }

    private static function assertAdvances(Mappe $mappe, callable $mutation): void
    {
        $vorher = self::zurueckdatieren($mappe);
        $mutation();
        self::assertGreaterThan($vorher, $mappe->getGeaendertAm());
    }

    private static function zurueckdatieren(Mappe $mappe): \DateTimeImmutable
    {
        $vergangen = new \DateTimeImmutable('2020-01-01 00:00:00');
        (new \ReflectionProperty(Mappe::class, 'geaendertAm'))->setValue($mappe, $vergangen);

        return $vergangen;
    }

    /** @return list<Datei> */
    private static function dateien(Mappe $mappe): array
    {
        return array_map(static fn (MappeBestandteil $b): Datei => $b->getDatei(), $mappe->getBestandteile());
    }
}

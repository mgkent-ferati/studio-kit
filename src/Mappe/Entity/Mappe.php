<?php

declare(strict_types=1);

namespace App\Mappe\Entity;

use App\Bibliothek\Entity\Datei;
use App\Bibliothek\Entity\Schlagwort;
use App\Mappe\Repository\MappeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MappeRepository::class)]
#[ORM\Table(name: 'mappe')]
#[ORM\HasLifecycleCallbacks]
class Mappe
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $beschreibung = null;

    #[ORM\Column]
    private bool $vorlage = false;

    #[ORM\ManyToOne(targetEntity: Datei::class)]
    #[ORM\JoinColumn(name: 'ergebnis_datei_id', nullable: true, onDelete: 'RESTRICT')]
    private ?Datei $ergebnis = null;

    /** @var Collection<int, MappeBestandteil> */
    #[ORM\OneToMany(targetEntity: MappeBestandteil::class, mappedBy: 'mappe', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $bestandteile;

    /** @var Collection<int, Schlagwort> */
    #[ORM\ManyToMany(targetEntity: Schlagwort::class)]
    #[ORM\JoinTable(name: 'mappe_schlagwort')]
    #[ORM\OrderBy(['name' => 'ASC'])]
    private Collection $schlagwoerter;

    #[ORM\Column]
    private \DateTimeImmutable $erstelltAm;

    #[ORM\Column]
    private \DateTimeImmutable $geaendertAm;

    public function __construct(string $name)
    {
        $this->name = $name;
        $this->bestandteile = new ArrayCollection();
        $this->schlagwoerter = new ArrayCollection();
        $this->erstelltAm = new \DateTimeImmutable();
        $this->geaendertAm = $this->erstelltAm;
    }

    #[ORM\PreUpdate]
    public function beimAendern(): void
    {
        $this->geaendertAm = new \DateTimeImmutable();
    }

    private function beruehren(): void
    {
        $this->geaendertAm = new \DateTimeImmutable();
    }

    public function bestandteilHinzufuegen(Datei $datei): void
    {
        if (null !== $this->bestandteilFuer($datei)) {
            return;
        }
        $this->bestandteile->add(new MappeBestandteil($this, $datei, $this->bestandteile->count() + 1));
        $this->beruehren();
    }

    public function bestandteilEntfernen(Datei $datei): void
    {
        $bestandteil = $this->bestandteilFuer($datei);
        if (null === $bestandteil) {
            return;
        }
        $this->bestandteile->removeElement($bestandteil);
        $this->neuNummerieren($this->getBestandteile());
        $this->beruehren();
    }

    /** @param int $richtung -1 = nach oben, +1 = nach unten */
    public function bestandteilVerschieben(Datei $datei, int $richtung): void
    {
        $liste = $this->getBestandteile();
        foreach ($liste as $index => $bestandteil) {
            if ($bestandteil->getDatei() !== $datei) {
                continue;
            }
            $ziel = $index + $richtung;
            if ($ziel < 0 || $ziel >= count($liste)) {
                return;
            }
            [$liste[$index], $liste[$ziel]] = [$liste[$ziel], $liste[$index]];
            $this->neuNummerieren($liste);
            $this->beruehren();

            return;
        }
    }

    /** @param array<int, MappeBestandteil> $liste */
    private function neuNummerieren(array $liste): void
    {
        foreach ($liste as $index => $bestandteil) {
            $bestandteil->setPosition($index + 1);
        }
    }

    private function bestandteilFuer(Datei $datei): ?MappeBestandteil
    {
        foreach ($this->bestandteile as $bestandteil) {
            if ($bestandteil->getDatei() === $datei) {
                return $bestandteil;
            }
        }

        return null;
    }

    /** @return list<MappeBestandteil> */
    public function getBestandteile(): array
    {
        $liste = array_values($this->bestandteile->toArray());
        usort($liste, static fn (MappeBestandteil $a, MappeBestandteil $b): int => $a->getPosition() <=> $b->getPosition());

        return $liste;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getBeschreibung(): ?string
    {
        return $this->beschreibung;
    }

    public function setBeschreibung(?string $beschreibung): void
    {
        $this->beschreibung = '' === $beschreibung ? null : $beschreibung;
    }

    public function isVorlage(): bool
    {
        return $this->vorlage;
    }

    public function setVorlage(bool $vorlage): void
    {
        $this->vorlage = $vorlage;
    }

    public function getErgebnis(): ?Datei
    {
        return $this->ergebnis;
    }

    public function setErgebnis(?Datei $ergebnis): void
    {
        $this->ergebnis = $ergebnis;
    }

    /** @return list<Schlagwort> */
    public function getSchlagwoerter(): array
    {
        return array_values($this->schlagwoerter->toArray());
    }

    /** @param list<Schlagwort> $schlagwoerter */
    public function setSchlagwoerter(array $schlagwoerter): void
    {
        if ($schlagwoerter === $this->getSchlagwoerter()) {
            return;
        }
        $this->schlagwoerter->clear();
        foreach ($schlagwoerter as $schlagwort) {
            $this->schlagwoerter->add($schlagwort);
        }
        $this->beruehren();
    }

    public function getErstelltAm(): \DateTimeImmutable
    {
        return $this->erstelltAm;
    }

    public function getGeaendertAm(): \DateTimeImmutable
    {
        return $this->geaendertAm;
    }
}

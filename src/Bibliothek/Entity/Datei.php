<?php

declare(strict_types=1);

namespace App\Bibliothek\Entity;

use App\Bibliothek\Enum\DateiTyp;
use App\Bibliothek\Enum\Herkunft;
use App\Bibliothek\Repository\DateiRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: DateiRepository::class)]
#[ORM\Table(name: 'datei')]
class Datei
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(length: 20, enumType: DateiTyp::class)]
    private DateiTyp $typ;

    #[ORM\Column]
    private bool $vorlage = false;

    #[ORM\Column]
    private \DateTimeImmutable $erstelltAm;

    /** @var Collection<int, Fassung> */
    #[ORM\OneToMany(targetEntity: Fassung::class, mappedBy: 'datei', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['nummer' => 'ASC'])]
    private Collection $fassungen;

    /** @var Collection<int, Schlagwort> */
    #[ORM\ManyToMany(targetEntity: Schlagwort::class)]
    #[ORM\JoinTable(name: 'datei_schlagwort')]
    #[ORM\OrderBy(['name' => 'ASC'])]
    private Collection $schlagwoerter;

    public function __construct(string $name, DateiTyp $typ, ?\DateTimeImmutable $erstelltAm = null)
    {
        $this->id = Uuid::v7();
        $this->name = $name;
        $this->typ = $typ;
        $this->erstelltAm = $erstelltAm ?? new \DateTimeImmutable();
        $this->fassungen = new ArrayCollection();
        $this->schlagwoerter = new ArrayCollection();
    }

    public function neueFassung(
        string $originalname,
        int $groesse,
        string $mimeTyp,
        string $sha256,
        Herkunft $herkunft,
        ?\DateTimeImmutable $erstelltAm = null,
        ?Fassung $quelleVideo = null,
        ?Fassung $quelleAudio = null,
    ): Fassung {
        $nummer = ($this->aktuelleFassung()?->getNummer() ?? 0) + 1;
        $fassung = new Fassung(
            $this,
            $nummer,
            $originalname,
            $groesse,
            $mimeTyp,
            $sha256,
            $herkunft,
            $erstelltAm ?? new \DateTimeImmutable(),
            $quelleVideo,
            $quelleAudio,
        );
        $this->fassungen->add($fassung);

        return $fassung;
    }

    public function aktuelleFassung(): ?Fassung
    {
        $aktuelle = null;
        foreach ($this->fassungen as $fassung) {
            if (null === $aktuelle || $fassung->getNummer() > $aktuelle->getNummer()) {
                $aktuelle = $fassung;
            }
        }

        return $aktuelle;
    }

    public function getId(): Uuid
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

    public function getTyp(): DateiTyp
    {
        return $this->typ;
    }

    public function isVorlage(): bool
    {
        return $this->vorlage;
    }

    public function setVorlage(bool $vorlage): void
    {
        $this->vorlage = $vorlage;
    }

    public function getErstelltAm(): \DateTimeImmutable
    {
        return $this->erstelltAm;
    }

    /** @return list<Fassung> */
    public function getFassungen(): array
    {
        $fassungen = array_values($this->fassungen->toArray());
        usort($fassungen, static fn (Fassung $a, Fassung $b): int => $a->getNummer() <=> $b->getNummer());

        return $fassungen;
    }

    /** @return list<Schlagwort> */
    public function getSchlagwoerter(): array
    {
        return array_values($this->schlagwoerter->toArray());
    }

    /** @param list<Schlagwort> $schlagwoerter */
    public function setSchlagwoerter(array $schlagwoerter): void
    {
        $this->schlagwoerter->clear();
        foreach ($schlagwoerter as $schlagwort) {
            $this->schlagwoerter->add($schlagwort);
        }
    }
}

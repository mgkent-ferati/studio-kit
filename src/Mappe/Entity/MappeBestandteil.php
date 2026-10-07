<?php

declare(strict_types=1);

namespace App\Mappe\Entity;

use App\Bibliothek\Entity\Datei;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'mappe_bestandteil')]
#[ORM\UniqueConstraint(name: 'mappe_bestandteil_eindeutig', columns: ['mappe_id', 'datei_id'])]
class MappeBestandteil
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Mappe::class, inversedBy: 'bestandteile')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Mappe $mappe;

    #[ORM\ManyToOne(targetEntity: Datei::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Datei $datei;

    #[ORM\Column]
    private int $position;

    public function __construct(Mappe $mappe, Datei $datei, int $position)
    {
        $this->mappe = $mappe;
        $this->datei = $datei;
        $this->position = $position;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMappe(): Mappe
    {
        return $this->mappe;
    }

    public function getDatei(): Datei
    {
        return $this->datei;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }
}

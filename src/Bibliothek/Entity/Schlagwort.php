<?php

declare(strict_types=1);

namespace App\Bibliothek\Entity;

use App\Bibliothek\Repository\SchlagwortRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SchlagwortRepository::class)]
#[ORM\Table(name: 'schlagwort')]
class Schlagwort
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100, unique: true)]
    private string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    /**
     * Kommagetrennte Eingabe → kleingeschriebene, getrimmte, eindeutige Namen.
     *
     * @return list<string>
     */
    public static function ausText(string $text): array
    {
        $namen = [];
        foreach (explode(',', $text) as $teil) {
            $name = mb_substr(mb_strtolower(trim($teil)), 0, 100);
            if ('' !== $name && !in_array($name, $namen, true)) {
                $namen[] = $name;
            }
        }

        return $namen;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }
}

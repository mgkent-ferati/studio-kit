<?php

declare(strict_types=1);

namespace App\Bibliothek\Entity;

use App\Bibliothek\Enum\Herkunft;
use App\Bibliothek\Repository\FassungRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FassungRepository::class)]
#[ORM\Table(name: 'fassung')]
#[ORM\UniqueConstraint(name: 'fassung_datei_nummer', columns: ['datei_id', 'nummer'])]
#[ORM\Index(name: 'fassung_sha256', columns: ['sha256'])]
class Fassung
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Datei::class, inversedBy: 'fassungen')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Datei $datei;

    #[ORM\Column]
    private int $nummer;

    #[ORM\Column(length: 255)]
    private string $originalname;

    #[ORM\Column(type: Types::BIGINT)]
    private int|string $groesse;

    #[ORM\Column(length: 127)]
    private string $mimeTyp;

    #[ORM\Column(length: 64)]
    private string $sha256;

    #[ORM\Column(length: 500)]
    private string $pfad;

    #[ORM\Column(length: 20, enumType: Herkunft::class)]
    private Herkunft $herkunft;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Fassung $quelleVideo;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Fassung $quelleAudio;

    #[ORM\Column]
    private \DateTimeImmutable $erstelltAm;

    public function __construct(
        Datei $datei,
        int $nummer,
        string $originalname,
        int $groesse,
        string $mimeTyp,
        string $sha256,
        Herkunft $herkunft,
        \DateTimeImmutable $erstelltAm,
        ?self $quelleVideo = null,
        ?self $quelleAudio = null,
    ) {
        $this->datei = $datei;
        $this->nummer = $nummer;
        $this->originalname = self::sichererName($originalname);
        $this->groesse = $groesse;
        $this->mimeTyp = $mimeTyp;
        $this->sha256 = $sha256;
        $this->herkunft = $herkunft;
        $this->erstelltAm = $erstelltAm;
        $this->quelleVideo = $quelleVideo;
        $this->quelleAudio = $quelleAudio;
        $this->pfad = sprintf('%s/%d-%s', $datei->getId()->toRfc4122(), $nummer, $this->originalname);
    }

    /** Letzter Pfadteil ohne Steuerzeichen und führende Punkte – verhindert Pfad-Ausbrüche. */
    public static function sichererName(string $originalname): string
    {
        $teile = explode('/', str_replace('\\', '/', $originalname));
        $name = (string) end($teile);
        $name = (string) preg_replace('/[\x00-\x1F\x7F]/u', '', $name);
        $name = ltrim($name, '.');

        return '' === $name ? 'datei' : mb_substr($name, -200);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDatei(): Datei
    {
        return $this->datei;
    }

    public function getNummer(): int
    {
        return $this->nummer;
    }

    public function getOriginalname(): string
    {
        return $this->originalname;
    }

    public function getGroesse(): int
    {
        return (int) $this->groesse;
    }

    public function getMimeTyp(): string
    {
        return $this->mimeTyp;
    }

    public function getSha256(): string
    {
        return $this->sha256;
    }

    public function getPfad(): string
    {
        return $this->pfad;
    }

    public function getHerkunft(): Herkunft
    {
        return $this->herkunft;
    }

    public function getQuelleVideo(): ?self
    {
        return $this->quelleVideo;
    }

    public function getQuelleAudio(): ?self
    {
        return $this->quelleAudio;
    }

    public function getErstelltAm(): \DateTimeImmutable
    {
        return $this->erstelltAm;
    }
}

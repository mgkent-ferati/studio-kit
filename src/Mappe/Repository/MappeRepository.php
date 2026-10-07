<?php

declare(strict_types=1);

namespace App\Mappe\Repository;

use App\Bibliothek\Entity\Datei;
use App\Mappe\Entity\Mappe;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Types\UuidType;

/** @extends ServiceEntityRepository<Mappe> */
final class MappeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Mappe::class);
    }

    /** @return list<Mappe> */
    public function findByDatei(Datei $datei): array
    {
        /** @var list<Mappe> $mappen */
        $mappen = $this->createQueryBuilder('m')
            ->leftJoin('m.bestandteile', 'b')
            ->where('b.datei = :datei OR m.ergebnis = :datei')
            ->setParameter('datei', $datei->getId(), UuidType::NAME)
            ->distinct()
            ->orderBy('m.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $mappen;
    }

    /** @return list<Mappe> */
    public function suche(?string $schlagwort = null, ?bool $vorlage = null, ?string $text = null): array
    {
        $qb = $this->createQueryBuilder('m')->orderBy('m.geaendertAm', 'DESC');
        if (null !== $vorlage) {
            $qb->andWhere('m.vorlage = :vorlage')->setParameter('vorlage', $vorlage);
        }
        if (null !== $text && '' !== trim($text)) {
            $qb->andWhere('LOWER(m.name) LIKE :text')
                ->setParameter('text', '%'.addcslashes(mb_strtolower(trim($text)), '%_\\').'%');
        }
        if (null !== $schlagwort && '' !== trim($schlagwort)) {
            $qb->innerJoin('m.schlagwoerter', 's')
                ->andWhere('s.name = :schlagwort')
                ->setParameter('schlagwort', mb_strtolower(trim($schlagwort)));
        }

        /** @var list<Mappe> $treffer */
        $treffer = $qb->getQuery()->getResult();

        return $treffer;
    }
}

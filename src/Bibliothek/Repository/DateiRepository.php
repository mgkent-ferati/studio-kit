<?php

declare(strict_types=1);

namespace App\Bibliothek\Repository;

use App\Bibliothek\Entity\Datei;
use App\Bibliothek\Enum\DateiTyp;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Datei> */
final class DateiRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Datei::class);
    }

    /** @return list<Datei> */
    public function suche(?DateiTyp $typ = null, ?string $schlagwort = null, ?bool $vorlage = null, ?string $text = null): array
    {
        $qb = $this->createQueryBuilder('d')->orderBy('d.erstelltAm', 'DESC');
        if (null !== $typ) {
            $qb->andWhere('d.typ = :typ')->setParameter('typ', $typ);
        }
        if (null !== $vorlage) {
            $qb->andWhere('d.vorlage = :vorlage')->setParameter('vorlage', $vorlage);
        }
        if (null !== $text && '' !== trim($text)) {
            $qb->andWhere('LOWER(d.name) LIKE :text')
                ->setParameter('text', '%'.addcslashes(mb_strtolower(trim($text)), '%_\\').'%');
        }
        if (null !== $schlagwort && '' !== trim($schlagwort)) {
            $qb->innerJoin('d.schlagwoerter', 's')
                ->andWhere('s.name = :schlagwort')
                ->setParameter('schlagwort', mb_strtolower(trim($schlagwort)));
        }

        /** @var list<Datei> $treffer */
        $treffer = $qb->getQuery()->getResult();

        return $treffer;
    }
}

<?php

declare(strict_types=1);

namespace App\Bibliothek\Repository;

use App\Bibliothek\Entity\Fassung;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Fassung> */
final class FassungRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Fassung::class);
    }

    public function findOneBySha256(string $sha256): ?Fassung
    {
        return $this->findOneBy(['sha256' => $sha256], ['id' => 'ASC']);
    }
}

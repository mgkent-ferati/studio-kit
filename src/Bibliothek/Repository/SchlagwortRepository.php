<?php

declare(strict_types=1);

namespace App\Bibliothek\Repository;

use App\Bibliothek\Entity\Schlagwort;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Schlagwort> */
final class SchlagwortRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Schlagwort::class);
    }

    /**
     * Neue Schlagwörter werden persistiert, aber nicht geflusht.
     *
     * @param list<string> $namen
     *
     * @return list<Schlagwort>
     */
    public function holeOderErzeuge(array $namen): array
    {
        if ([] === $namen) {
            return [];
        }
        $vorhandene = [];
        foreach ($this->findBy(['name' => $namen]) as $schlagwort) {
            $vorhandene[$schlagwort->getName()] = $schlagwort;
        }
        $ergebnis = [];
        foreach ($namen as $name) {
            if (!isset($vorhandene[$name])) {
                $vorhandene[$name] = new Schlagwort($name);
                $this->getEntityManager()->persist($vorhandene[$name]);
            }
            $ergebnis[] = $vorhandene[$name];
        }

        return $ergebnis;
    }

    /** @return list<Schlagwort> */
    public function alle(): array
    {
        return $this->findBy([], ['name' => 'ASC']);
    }
}

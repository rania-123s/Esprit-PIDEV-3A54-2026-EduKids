<?php

namespace App\Repository;

use App\Entity\Programme;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Programme>
 */
class ProgrammeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Programme::class);
    }

    /**
     * Trouve tous les programmes avec leurs événements associés
     */
    public function findAllWithEvenements(): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.evenement', 'e')
            ->addSelect('e')
            ->orderBy('e.dateEvenement', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les événements sans programme
     */
    public function findEvenementsSansProgramme(): array
    {
        $em = $this->getEntityManager();
        
        return $em->createQuery('
            SELECT e FROM App\Entity\Evenement e
            WHERE e.id NOT IN (
                SELECT IDENTITY(p.evenement) FROM App\Entity\Programme p
            )
        ')->getResult();
    }
}

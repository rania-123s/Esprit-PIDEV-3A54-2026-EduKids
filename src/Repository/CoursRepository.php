<?php

namespace App\Repository;

use App\Entity\Cours;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Cours>
 */
class CoursRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Cours::class);
    }

    //    /**
    //     * @return Cours[] Returns an array of Cours objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('c.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Cours
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }

    /**
     * Search courses by title, subject, or level
     */
    public function searchCours(string $query, ?string $sort = null): \Doctrine\ORM\QueryBuilder
    {
        $qb = $this->createQueryBuilder('c')
            ->andWhere('c.titre LIKE :query OR c.matiere LIKE :query OR c.niveau LIKE :query')
            ->setParameter('query', '%'.$query.'%');

        $this->applySorting($qb, $sort);
        
        return $qb;
    }

    /**
     * Get all courses with sorting
     */
    public function findAllSorted(?string $sort = null): \Doctrine\ORM\QueryBuilder
    {
        $qb = $this->createQueryBuilder('c');
        $this->applySorting($qb, $sort);
        return $qb;
    }

    /**
     * Apply sorting to query builder
     */
    private function applySorting($qb, ?string $sort = null): void
    {
        match($sort) {
            'a-z' => $qb->orderBy('c.titre', 'ASC'),
            'niveau-asc' => $qb->orderBy('c.niveau', 'ASC'),
            'niveau-desc' => $qb->orderBy('c.niveau', 'DESC'),
            default => $qb->orderBy('c.id', 'DESC'),
        };
    }
}

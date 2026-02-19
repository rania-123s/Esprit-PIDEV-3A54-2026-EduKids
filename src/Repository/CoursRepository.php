<?php

namespace App\Repository;

use App\Entity\Cours;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
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
    public function searchCours(string $query, ?string $sort = null): QueryBuilder
    {
        $qb = $this->createQueryBuilder('c');

        $terms = $this->tokenizeSearchQuery($query);

        foreach ($terms as $index => $term) {
            $likeTerm = '%' . $this->escapeLikeParameter($term) . '%';
            $orX = $qb->expr()->orX(
                "LOWER(c.titre) LIKE :term$index ESCAPE '\\\\'",
                "LOWER(c.matiere) LIKE :term$index ESCAPE '\\\\'",
                "LOWER(c.description) LIKE :term$index ESCAPE '\\\\'"
            );

            if (ctype_digit($term)) {
                $orX->add("c.niveau = :niveau$index");
                $qb->setParameter("niveau$index", (int) $term);
            }

            $qb->andWhere($orX)
               ->setParameter("term$index", $likeTerm);
        }

        $this->applySorting($qb, $sort);
        
        return $qb;
    }

    /**
     * Get all courses with sorting
     */
    public function findAllSorted(?string $sort = null): QueryBuilder
    {
        $qb = $this->createQueryBuilder('c');
        $this->applySorting($qb, $sort);
        return $qb;
    }

    /**
     * Get all front-office courses with their own lessons.
     */
    public function findAllWithLecons(): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.lecons', 'l')
            ->addSelect('l')
            ->orderBy('c.id', 'DESC')
            ->addOrderBy('l.ordre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * QueryBuilder for front-office courses with lessons (for pagination).
     */
    public function findAllWithLeconsQueryBuilder(): \Doctrine\ORM\QueryBuilder
    {
        return $this->createQueryBuilder('c')
            ->orderBy('c.id', 'DESC');
    }

    /**
     * Apply sorting to query builder
     */
    private function applySorting($qb, ?string $sort = null): void
    {
        match($sort) {
            'a-z' => $qb->orderBy('c.titre', 'ASC')->addOrderBy('c.id', 'DESC'),
            'niveau-asc' => $qb->orderBy('c.niveau', 'ASC')->addOrderBy('c.id', 'DESC'),
            'niveau-desc' => $qb->orderBy('c.niveau', 'DESC')->addOrderBy('c.id', 'DESC'),
            default => $qb->orderBy('c.id', 'DESC'),
        };
    }

    /**
     * @return string[]
     */
    private function tokenizeSearchQuery(string $query): array
    {
        $normalized = mb_strtolower(trim($query));
        $normalized = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $normalized) ?? '';

        if ($normalized === '') {
            return [];
        }

        $terms = preg_split('/\s+/', $normalized) ?: [];
        $terms = array_values(array_unique(array_filter($terms, static fn (string $term): bool => $term !== '')));

        return array_slice($terms, 0, 8);
    }

    private function escapeLikeParameter(string $value): string
    {
        return strtr($value, [
            '\\' => '\\\\',
            '%' => '\\%',
            '_' => '\\_',
        ]);
    }
}

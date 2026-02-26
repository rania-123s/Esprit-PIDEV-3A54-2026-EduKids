<?php

namespace App\Repository\Quiz;

use App\Entity\Quiz\Quiz;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Quiz>
 */
class QuizRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Quiz::class);
    }

    /**
     * Admin: search and sort.
     * @return Quiz[]
     */
    public function searchAndSort(?string $q = null, ?bool $published = null, ?string $sort = 'titre', ?string $order = 'ASC'): array
    {
        $qb = $this->createQueryBuilder('q');

        if (null !== $q && '' !== trim($q)) {
            $qb->andWhere('q.titre LIKE :q OR q.description LIKE :q')
                ->setParameter('q', '%' . trim($q) . '%');
        }
        if ($published !== null) {
            $qb->andWhere('q.published = :pub')->setParameter('pub', $published);
        }

        $order = strtoupper((string) $order) === 'DESC' ? 'DESC' : 'ASC';
        $sortField = match ($sort) {
            'createdAt', 'date' => 'q.createdAt',
            'updatedAt' => 'q.updatedAt',
            'id' => 'q.id',
            default => 'q.titre',
        };
        $qb->orderBy($sortField, $order);

        return $qb->getQuery()->getResult();
    }

    /**
     * Front: published quizzes only, optional search and sort.
     * @return Quiz[]
     */
    public function findPublished(?string $q = null, string $sort = 'titre', string $order = 'ASC'): array
    {
        $qb = $this->createQueryBuilder('q')
            ->andWhere('q.published = :pub')
            ->setParameter('pub', true);

        if (null !== $q && '' !== trim($q)) {
            $qb->andWhere('q.titre LIKE :q OR q.description LIKE :q')
                ->setParameter('q', '%' . trim($q) . '%');
        }

        $order = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';
        $sortField = match ($sort) {
            'createdAt', 'date' => 'q.createdAt',
            default => 'q.titre',
        };
        $qb->orderBy($sortField, $order);

        return $qb->getQuery()->getResult();
    }

    public function save(Quiz $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Quiz $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}

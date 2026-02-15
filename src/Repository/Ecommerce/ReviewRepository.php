<?php

namespace App\Repository\Ecommerce;

use App\Entity\Ecommerce\Produit;
use App\Entity\Ecommerce\Review;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Review>
 */
class ReviewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Review::class);
    }

    /**
     * @return Review[]
     */
    public function findByProduitApproved(Produit $produit, int $limit = 50): array
    {
        return $this->createQueryBuilder('r')
            ->innerJoin('r.user', 'u')->addSelect('u')
            ->andWhere('r.produit = :produit')
            ->andWhere('r.status = :status')
            ->setParameter('produit', $produit)
            ->setParameter('status', Review::STATUS_APPROVED)
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function getAverageRatingForProduit(Produit $produit): ?float
    {
        $result = $this->createQueryBuilder('r')
            ->select('AVG(r.rating) as avg_rating')
            ->andWhere('r.produit = :produit')
            ->andWhere('r.status = :status')
            ->setParameter('produit', $produit)
            ->setParameter('status', Review::STATUS_APPROVED)
            ->getQuery()
            ->getSingleScalarResult();
        return $result !== null ? (float) $result : null;
    }

    public function getCountForProduit(Produit $produit): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.produit = :produit')
            ->andWhere('r.status = :status')
            ->setParameter('produit', $produit)
            ->setParameter('status', Review::STATUS_APPROVED)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return Review[]
     */
    public function findPending(): array
    {
        return $this->createQueryBuilder('r')
            ->innerJoin('r.user', 'u')->addSelect('u')
            ->innerJoin('r.produit', 'p')->addSelect('p')
            ->andWhere('r.status = :status')
            ->setParameter('status', Review::STATUS_PENDING)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Review[]
     */
    public function findAllOrdered(int $limit = 200): array
    {
        return $this->createQueryBuilder('r')
            ->innerJoin('r.user', 'u')->addSelect('u')
            ->innerJoin('r.produit', 'p')->addSelect('p')
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function userHasReviewed(User $user, Produit $produit): bool
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.user = :user')
            ->andWhere('r.produit = :produit')
            ->setParameter('user', $user)
            ->setParameter('produit', $produit)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    /**
     * @param Produit[] $produits
     * @return array<int, array{avg: float, count: int}>
     */
    public function getAverageAndCountByProduits(array $produits): array
    {
        if (empty($produits)) {
            return [];
        }
        $ids = array_map(fn (Produit $p) => $p->getId(), $produits);
        $qb = $this->createQueryBuilder('r')
            ->select('IDENTITY(r.produit) as produit_id', 'AVG(r.rating) as avg_rating', 'COUNT(r.id) as cnt')
            ->andWhere('r.produit IN (:ids)')
            ->andWhere('r.status = :status')
            ->setParameter('ids', $ids)
            ->setParameter('status', Review::STATUS_APPROVED)
            ->groupBy('r.produit');
        $result = $qb->getQuery()->getResult();
        $map = [];
        foreach ($ids as $id) {
            $map[$id] = ['avg' => 0.0, 'count' => 0];
        }
        foreach ($result as $row) {
            $map[(int) $row['produit_id']] = [
                'avg' => (float) $row['avg_rating'],
                'count' => (int) $row['cnt'],
            ];
        }
        return $map;
    }

    /**
     * Fetch last approved reviews per product for catalogue hover. Returns [produit_id => Review[]] (max $limitPerProduit per product).
     * @param Produit[] $produits
     * @return array<int, Review[]>
     */
    public function findApprovedByProduitIdsGrouped(array $produits, int $limitPerProduit = 5): array
    {
        if (empty($produits)) {
            return [];
        }
        $ids = array_map(fn (Produit $p) => $p->getId(), $produits);
        $reviews = $this->createQueryBuilder('r')
            ->innerJoin('r.user', 'u')->addSelect('u')
            ->andWhere('r.produit IN (:ids)')
            ->andWhere('r.status = :status')
            ->setParameter('ids', $ids)
            ->setParameter('status', Review::STATUS_APPROVED)
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults(count($ids) * $limitPerProduit)
            ->getQuery()
            ->getResult();
        $grouped = [];
        foreach ($ids as $id) {
            $grouped[$id] = [];
        }
        foreach ($reviews as $review) {
            $pid = $review->getProduit()->getId();
            if (count($grouped[$pid]) < $limitPerProduit) {
                $grouped[$pid][] = $review;
            }
        }
        return $grouped;
    }

    public function save(Review $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Review $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}

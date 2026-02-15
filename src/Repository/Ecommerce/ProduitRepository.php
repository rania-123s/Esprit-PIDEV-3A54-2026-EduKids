<?php

namespace App\Repository\Ecommerce;

use App\Entity\Ecommerce\Produit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Produit>
 */
class ProduitRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Produit::class);
    }

    /**
     * Front: filter by category, price range, search, sort.
     * @return Produit[]
     */
    public function findByFilters(?int $categoryId = null, ?int $prixMin = null, ?int $prixMax = null, ?string $q = null, string $sort = 'nom', string $order = 'ASC'): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.category', 'cat')->addSelect('cat');

        if (null !== $categoryId) {
            $qb->andWhere('p.category = :catId')->setParameter('catId', $categoryId);
        }
        if (null !== $prixMin) {
            $qb->andWhere('p.prix >= :prixMin')->setParameter('prixMin', $prixMin);
        }
        if (null !== $prixMax) {
            $qb->andWhere('p.prix <= :prixMax')->setParameter('prixMax', $prixMax);
        }
        if (null !== $q && '' !== trim($q)) {
            $qb->andWhere('p.nom LIKE :q OR p.description LIKE :q')
                ->setParameter('q', '%' . trim($q) . '%');
        }

        $order = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';
        $sortField = match ($sort) {
            'prix' => 'p.prix',
            'nom' => 'p.nom',
            'id' => 'p.id',
            default => 'p.nom',
        };
        $qb->orderBy($sortField, $order);

        return $qb->getQuery()->getResult();
    }

    /**
     * Admin: search and sort.
     * @return Produit[]
     */
    public function searchAndSort(?string $q = null, ?int $categoryId = null, ?string $sort = 'nom', ?string $order = 'ASC'): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.category', 'cat')->addSelect('cat');

        if (null !== $q && '' !== trim($q)) {
            $qb->andWhere('p.nom LIKE :q OR p.description LIKE :q')
                ->setParameter('q', '%' . trim($q) . '%');
        }
        if (null !== $categoryId) {
            $qb->andWhere('p.category = :catId')->setParameter('catId', $categoryId);
        }

        $order = strtoupper((string) $order) === 'DESC' ? 'DESC' : 'ASC';
        $sortField = match ($sort) {
            'prix' => 'p.prix',
            'nom' => 'p.nom',
            'category' => 'cat.nom',
            'id' => 'p.id',
            default => 'p.nom',
        };
        $qb->orderBy($sortField, $order);

        return $qb->getQuery()->getResult();
    }

    public function save(Produit $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Produit $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}

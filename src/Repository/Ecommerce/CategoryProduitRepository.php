<?php

namespace App\Repository\Ecommerce;

use App\Entity\Ecommerce\CategoryProduit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CategoryProduit>
 */
class CategoryProduitRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CategoryProduit::class);
    }

    /**
     * @return CategoryProduit[]
     */
    public function searchAndSort(?string $q = null, ?string $sort = 'nom', ?string $order = 'ASC'): array
    {
        $qb = $this->createQueryBuilder('c');
        if (null !== $q && '' !== trim($q)) {
            $qb->andWhere('c.nom LIKE :q OR c.description LIKE :q')
                ->setParameter('q', '%' . trim($q) . '%');
        }
        $order = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';
        $sortField = match ($sort) {
            'nom' => 'c.nom',
            'description' => 'c.description',
            'id' => 'c.id',
            default => 'c.nom',
        };
        $qb->orderBy($sortField, $order);
        return $qb->getQuery()->getResult();
    }

    public function save(CategoryProduit $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(CategoryProduit $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}

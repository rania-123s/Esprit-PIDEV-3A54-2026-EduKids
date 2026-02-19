<?php

namespace App\Repository\Ecommerce;

use App\Entity\Ecommerce\LigneCommande;
use App\Entity\Ecommerce\Produit;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LigneCommande>
 */
class LigneCommandeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LigneCommande::class);
    }

    public function userHasPurchasedProduct(User $user, Produit $produit): bool
    {
        $validStatuses = ['paye', 'confirme', 'livre'];
        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->innerJoin('l.commande', 'c')
            ->andWhere('c.user = :user')
            ->andWhere('l.produit = :produit')
            ->andWhere('c.statut IN (:statuses)')
            ->setParameter('user', $user)
            ->setParameter('produit', $produit)
            ->setParameter('statuses', $validStatuses)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    /**
     * @param int[] $userIds
     * @return int[] User IDs that have purchased this product
     */
    public function userIdsWhoPurchasedProduct(Produit $produit, array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }
        $validStatuses = ['paye', 'confirme', 'livre'];
        $result = $this->createQueryBuilder('l')
            ->select('DISTINCT u.id')
            ->innerJoin('l.commande', 'c')
            ->innerJoin('c.user', 'u')
            ->andWhere('l.produit = :produit')
            ->andWhere('c.statut IN (:statuses)')
            ->andWhere('u.id IN (:userIds)')
            ->setParameter('produit', $produit)
            ->setParameter('statuses', $validStatuses)
            ->setParameter('userIds', $userIds)
            ->getQuery()
            ->getSingleColumnResult();
        return array_map('intval', $result);
    }
}

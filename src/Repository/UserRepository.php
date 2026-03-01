<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    //    /**
    //     * @return User[] Returns an array of User objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('u.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?User
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }

    /**
     * Returns one user with ROLE_ADMIN, or null if none.
     */
    public function findOneAdmin(): ?User
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.isActive = true')
            ->andWhere('u.roles LIKE :roleAdmin')
            ->setParameter('roleAdmin', '%ROLE_ADMIN%')
            ->orderBy('u.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Search users with ROLE_PARENT by first name, last name or email.
     * Excludes the given user ID and returns at most $limit results.
     *
     * @return User[]
     */
    public function searchParentsByName(string $query, int $excludeUserId, int $limit = 20): array
    {
        if (mb_strlen($query) < 2) {
            return [];
        }

        $term = '%' . addcslashes($query, '%_') . '%';

        return $this->createQueryBuilder('u')
            ->andWhere('u.id != :excludeId')
            ->andWhere('u.isActive = true')
            ->andWhere('u.roles LIKE :roleParent')
            ->andWhere(
                'u.firstName LIKE :term OR u.lastName LIKE :term OR u.email LIKE :term'
            )
            ->setParameter('excludeId', $excludeUserId)
            ->setParameter('roleParent', '%ROLE_PARENT%')
            ->setParameter('term', $term)
            ->orderBy('u.lastName', 'ASC')
            ->addOrderBy('u.firstName', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}

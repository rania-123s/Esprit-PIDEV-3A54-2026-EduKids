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

    /**
     * Search users by first name, last name, or email, optionally filtered by role
     *
     * @param string|null $searchTerm The search term (null or empty string returns all users)
     * @param string|null $role Optional role filter (e.g. 'ROLE_ADMIN')
     * @param string $sortBy Field to sort by (id, firstName, lastName, email)
     * @param string $sortOrder Sort direction (ASC or DESC)
     * @return User[] Returns an array of User objects
     */
    public function searchUsers(?string $searchTerm = null, ?string $role = null, string $sortBy = 'id', string $sortOrder = 'DESC'): array
    {
        $qb = $this->createQueryBuilder('u');

        if ($searchTerm && trim($searchTerm) !== '') {
            $searchTerm = '%' . strtolower(trim($searchTerm)) . '%';
            
            $qb->andWhere('LOWER(u.firstName) LIKE :search OR LOWER(u.lastName) LIKE :search OR LOWER(u.email) LIKE :search')
               ->setParameter('search', $searchTerm);
        }

        if ($role && trim($role) !== '') {
            $qb->andWhere('u.roles LIKE :role')
               ->setParameter('role', '%"' . $role . '"%');
        }

        // Validate sort field to prevent SQL injection
        $allowedSortFields = ['id', 'firstName', 'lastName', 'email', 'isActive'];
        if (!in_array($sortBy, $allowedSortFields)) {
            $sortBy = 'id';
        }
        $sortOrder = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';

        return $qb->orderBy('u.' . $sortBy, $sortOrder)
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Find users by specific role
     *
     * @param string $role The role to filter by (e.g. 'ROLE_ELEVE')
     * @param string|null $searchTerm Optional search term
     * @param string $sortBy Field to sort by
     * @param string $sortOrder Sort direction
     * @return User[] Returns an array of User objects
     */
    public function findByRole(string $role, ?string $searchTerm = null, string $sortBy = 'id', string $sortOrder = 'DESC'): array
    {
        $qb = $this->createQueryBuilder('u')
            ->andWhere('u.roles LIKE :role')
            ->setParameter('role', '%"' . $role . '"%');

        if ($searchTerm && trim($searchTerm) !== '') {
            $searchTerm = '%' . strtolower(trim($searchTerm)) . '%';
            $qb->andWhere('LOWER(u.firstName) LIKE :search OR LOWER(u.lastName) LIKE :search OR LOWER(u.email) LIKE :search')
               ->setParameter('search', $searchTerm);
        }

        // Validate sort field
        $allowedSortFields = ['id', 'firstName', 'lastName', 'email', 'isActive'];
        if (!in_array($sortBy, $allowedSortFields)) {
            $sortBy = 'id';
        }
        $sortOrder = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';

        return $qb->orderBy('u.' . $sortBy, $sortOrder)
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Count users by role
     */
    public function countByRole(string $role): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.roles LIKE :role')
            ->setParameter('role', '%"' . $role . '"%')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count total users
     */
    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count active users
     */
    public function countActive(): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.isActive = true')
            ->getQuery()
            ->getSingleScalarResult();
    }
}

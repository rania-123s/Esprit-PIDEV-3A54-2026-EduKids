<?php

namespace App\Repository;

use App\Entity\Cours;
use App\Entity\User;
use App\Entity\UserCoursProgress;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserCoursProgress>
 */
class UserCoursProgressRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserCoursProgress::class);
    }

    public function findOneByUserAndCours(User $user, Cours $cours): ?UserCoursProgress
    {
        return $this->createQueryBuilder('ucp')
            ->andWhere('ucp.user = :user')
            ->andWhere('ucp.cours = :cours')
            ->setParameter('user', $user)
            ->setParameter('cours', $cours)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return UserCoursProgress[]
     */
    public function findByUserOrdered(User $user): array
    {
        return $this->createQueryBuilder('ucp')
            ->addSelect('c')
            ->innerJoin('ucp.cours', 'c')
            ->andWhere('ucp.user = :user')
            ->setParameter('user', $user)
            ->orderBy('ucp.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return int[]
     */
    public function findCourseIdsByUser(User $user): array
    {
        $rows = $this->createQueryBuilder('ucp')
            ->select('IDENTITY(ucp.cours) AS courseId')
            ->andWhere('ucp.user = :user')
            ->setParameter('user', $user)
            ->orderBy('ucp.id', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_values(array_filter(array_map(
            static fn (array $row): int => (int) ($row['courseId'] ?? 0),
            $rows
        )));
    }
}

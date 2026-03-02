<?php

namespace App\Repository;

use App\Entity\UserEvenementInteraction;
use App\Entity\User;
use App\Entity\Evenement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserEvenementInteraction>
 */
class UserEvenementInteractionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserEvenementInteraction::class);
    }

    public function findInteraction(User $user, Evenement $evenement, string $type): ?UserEvenementInteraction
    {
        return $this->findOneBy([
            'user' => $user,
            'evenement' => $evenement,
            'typeInteraction' => $type
        ]);
    }

    public function hasInteraction(User $user, Evenement $evenement, string $type): bool
    {
        return $this->findInteraction($user, $evenement, $type) !== null;
    }

    public function getUserFavorites(User $user): array
    {
        return $this->createQueryBuilder('i')
            ->join('i.evenement', 'e')
            ->where('i.user = :user')
            ->andWhere('i.typeInteraction = :type')
            ->setParameter('user', $user)
            ->setParameter('type', UserEvenementInteraction::TYPE_FAVORITE)
            ->orderBy('i.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}

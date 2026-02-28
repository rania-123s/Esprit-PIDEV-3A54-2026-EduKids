<?php

namespace App\Repository;

use App\Entity\Notification;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    /**
     * @return Notification[]
     */
    public function findLatestForReceiver(User $receiver, int $limit = 20): array
    {
        $limit = max(1, min($limit, 100));

        return $this->createQueryBuilder('n')
            ->innerJoin('n.sender', 'sender')
            ->addSelect('sender')
            ->andWhere('n.receiver = :receiver')
            ->setParameter('receiver', $receiver)
            ->orderBy('n.createdAt', 'DESC')
            ->addOrderBy('n.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countUnreadForReceiver(User $receiver): int
    {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->andWhere('n.receiver = :receiver')
            ->andWhere('n.isRead = :isRead')
            ->setParameter('receiver', $receiver)
            ->setParameter('isRead', false)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findOneForReceiver(int $id, User $receiver): ?Notification
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.id = :id')
            ->andWhere('n.receiver = :receiver')
            ->setParameter('id', $id)
            ->setParameter('receiver', $receiver)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function markAllAsReadForReceiver(User $receiver): int
    {
        return $this->createQueryBuilder('n')
            ->update()
            ->set('n.isRead', ':isRead')
            ->andWhere('n.receiver = :receiver')
            ->andWhere('n.isRead = :currentlyUnread')
            ->setParameter('isRead', true)
            ->setParameter('currentlyUnread', false)
            ->setParameter('receiver', $receiver)
            ->getQuery()
            ->execute();
    }
}

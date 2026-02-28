<?php

namespace App\Repository;

use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ConversationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Conversation::class);
    }

    public function buildPrivateKey(int $userAId, int $userBId): string
    {
        $ids = [$userAId, $userBId];
        sort($ids);

        return sprintf('private:%d:%d', $ids[0], $ids[1]);
    }

    public function findPrivateBetweenUsers(User $userA, User $userB): ?Conversation
    {
        $userAId = $userA->getId();
        $userBId = $userB->getId();

        if ($userAId === null || $userBId === null || $userAId === $userBId) {
            return null;
        }

        $privateKey = $this->buildPrivateKey($userAId, $userBId);

        $foundByKey = $this->createQueryBuilder('c')
            ->andWhere('c.isGroup = false')
            ->andWhere('c.privateKey = :privateKey')
            ->setParameter('privateKey', $privateKey)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($foundByKey instanceof Conversation) {
            return $foundByKey;
        }

        // Fallback for old rows without private_key.
        return $this->createQueryBuilder('c')
            ->innerJoin('c.participants', 'pa', 'WITH', 'pa.user = :userA')
            ->innerJoin('c.participants', 'pb', 'WITH', 'pb.user = :userB')
            ->andWhere('c.isGroup = false')
            ->andWhere('2 = (
                SELECT COUNT(cpCount.id)
                FROM App\Entity\ConversationParticipant cpCount
                WHERE cpCount.conversation = c
            )')
            ->setParameter('userA', $userA)
            ->setParameter('userB', $userB)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return Conversation[]
     */
    public function findForUser(User $user, ?string $search = null): array
    {
        $lastMessageSubQuery = $this->getEntityManager()->createQueryBuilder()
            ->select('MAX(m2.id)')
            ->from(Message::class, 'm2')
            ->where('m2.conversation = c');

        $qb = $this->createQueryBuilder('c')
            ->innerJoin('c.participants', 'cp', 'WITH', 'cp.user = :user AND cp.deletedAt IS NULL AND cp.hiddenAt IS NULL')
            ->leftJoin('c.messages', 'lm', 'WITH', 'lm.id = (' . $lastMessageSubQuery->getDQL() . ')')
            ->addSelect('cp', 'lm')
            ->distinct()
            ->setParameter('user', $user)
            ->orderBy('lm.createdAt', 'DESC')
            ->addOrderBy('c.updatedAt', 'DESC')
            ->addOrderBy('c.id', 'DESC');

        if ($search !== null && trim($search) !== '') {
            $q = '%' . mb_strtolower(trim($search)) . '%';
            $qb
                ->leftJoin('c.participants', 'sp', 'WITH', 'sp.deletedAt IS NULL')
                ->leftJoin('sp.user', 'su')
                ->andWhere('
                    LOWER(COALESCE(c.title, \'\')) LIKE :q
                    OR LOWER(COALESCE(su.firstName, \'\')) LIKE :q
                    OR LOWER(COALESCE(su.lastName, \'\')) LIKE :q
                    OR LOWER(COALESCE(su.email, \'\')) LIKE :q
                ')
                ->setParameter('q', $q);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return Conversation[]
     */
    public function findVisibleForUser(User $user, ?string $search = null): array
    {
        return $this->findForUser($user, $search);
    }
}

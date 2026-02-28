<?php

namespace App\Repository;

use App\Entity\Conversation;
use App\Entity\ConversationParticipant;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ConversationParticipantRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ConversationParticipant::class);
    }

    public function findForConversationAndUser(Conversation $conversation, User $user): ?ConversationParticipant
    {
        return $this->createQueryBuilder('cp')
            ->andWhere('cp.conversation = :conversation')
            ->andWhere('cp.user = :user')
            ->setParameter('conversation', $conversation)
            ->setParameter('user', $user)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findActiveForConversationAndUser(Conversation $conversation, User $user): ?ConversationParticipant
    {
        return $this->createQueryBuilder('cp')
            ->andWhere('cp.conversation = :conversation')
            ->andWhere('cp.user = :user')
            ->andWhere('cp.deletedAt IS NULL')
            ->andWhere('cp.hiddenAt IS NULL')
            ->setParameter('conversation', $conversation)
            ->setParameter('user', $user)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function isActiveParticipant(Conversation $conversation, User $user): bool
    {
        return $this->findActiveForConversationAndUser($conversation, $user) instanceof ConversationParticipant;
    }

    public function isGroupAdmin(Conversation $conversation, User $user): bool
    {
        $participant = $this->findActiveForConversationAndUser($conversation, $user);

        return $participant instanceof ConversationParticipant && $participant->isAdmin();
    }

    /**
     * @return ConversationParticipant[]
     */
    public function findActiveMembers(Conversation $conversation): array
    {
        return $this->createQueryBuilder('cp')
            ->innerJoin('cp.user', 'u')
            ->addSelect('u')
            ->andWhere('cp.conversation = :conversation')
            ->andWhere('cp.deletedAt IS NULL')
            ->setParameter('conversation', $conversation)
            ->orderBy('cp.role', 'ASC')
            ->addOrderBy('cp.joinedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return ConversationParticipant[]
     */
    public function findHiddenActiveMembers(Conversation $conversation): array
    {
        return $this->createQueryBuilder('cp')
            ->andWhere('cp.conversation = :conversation')
            ->andWhere('cp.deletedAt IS NULL')
            ->andWhere('cp.hiddenAt IS NOT NULL')
            ->setParameter('conversation', $conversation)
            ->getQuery()
            ->getResult();
    }
}

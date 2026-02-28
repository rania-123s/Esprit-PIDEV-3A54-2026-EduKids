<?php

namespace App\Repository;

use App\Entity\MessageAttachment;
use App\Entity\MessageAttachmentSummary;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class MessageAttachmentSummaryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MessageAttachmentSummary::class);
    }

    public function findOneByAttachmentAndUser(MessageAttachment $attachment, User $user): ?MessageAttachmentSummary
    {
        return $this->createQueryBuilder('mas')
            ->andWhere('mas.attachment = :attachment')
            ->andWhere('mas.user = :user')
            ->setParameter('attachment', $attachment)
            ->setParameter('user', $user)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}

<?php

namespace App\Repository;

use App\Entity\Conversation;
use App\Entity\Message;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

class MessageRepository extends ServiceEntityRepository
{
    private const APP_TIMEZONE = 'Africa/Tunis';

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
    }

    public function getTotalMessages(): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.deletedAt IS NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getTopUser(): ?array
    {
        $result = $this->createQueryBuilder('m')
            ->select('u.id AS userId')
            ->addSelect('u.email AS email')
            ->addSelect('u.firstName AS firstName')
            ->addSelect('u.lastName AS lastName')
            ->addSelect('COUNT(m.id) AS messageCount')
            ->addSelect('MAX(m.createdAt) AS lastActivity')
            ->join('m.sender', 'u')
            ->andWhere('m.deletedAt IS NULL')
            ->groupBy('u.id, u.email, u.firstName, u.lastName')
            ->orderBy('messageCount', 'DESC')
            ->addOrderBy('lastActivity', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getArrayResult();

        return $result[0] ?? null;
    }

    public function getActiveUsersCount(int $days): int
    {
        $days = max(1, $days);
        $since = (new \DateTimeImmutable('now', new \DateTimeZone(self::APP_TIMEZONE)))
            ->sub(new \DateInterval(sprintf('P%dD', $days)));

        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(DISTINCT IDENTITY(m.sender))')
            ->andWhere('m.deletedAt IS NULL')
            ->andWhere('m.createdAt >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getMessagesByHour(int $days = 1): array
    {
        $days = max(1, min($days, 31));
        $timezone = new \DateTimeZone(self::APP_TIMEZONE);
        $now = new \DateTimeImmutable('now', $timezone);

        $startAt = $days === 1
            ? $now->setTime(0, 0, 0)
            : $now->sub(new \DateInterval(sprintf('P%dD', $days - 1)))->setTime(0, 0, 0);

        $endAt = $days === 1 ? $startAt->modify('+1 day') : $now;

        $hourExpr = $this->isPostgreSql()
            ? 'EXTRACT(HOUR FROM m.created_at)'
            : 'HOUR(m.created_at)';

        $sql = sprintf(
            'SELECT %s AS hour_key, COUNT(m.id) AS message_count
             FROM message m
             WHERE m.deleted_at IS NULL
               AND m.created_at >= :startAt
               AND m.created_at < :endAt
             GROUP BY 1
             ORDER BY 1 ASC',
            $hourExpr
        );

        $rows = $this->getEntityManager()->getConnection()->executeQuery(
            $sql,
            ['startAt' => $startAt, 'endAt' => $endAt],
            ['startAt' => Types::DATETIME_IMMUTABLE, 'endAt' => Types::DATETIME_IMMUTABLE]
        )->fetchAllAssociative();

        $counts = array_fill(0, 24, 0);
        foreach ($rows as $row) {
            $hour = (int) $row['hour_key'];
            if ($hour >= 0 && $hour <= 23) {
                $counts[$hour] = (int) $row['message_count'];
            }
        }

        $series = [];
        foreach (range(0, 23) as $hour) {
            $series[] = [
                'hour' => $hour,
                'count' => $counts[$hour],
            ];
        }

        return $series;
    }

    public function getMessagesByDay(int $days = 7): array
    {
        $days = max(1, min($days, 90));
        $timezone = new \DateTimeZone(self::APP_TIMEZONE);
        $now = new \DateTimeImmutable('now', $timezone);
        $startAt = $now->sub(new \DateInterval(sprintf('P%dD', $days - 1)))->setTime(0, 0, 0);
        $endAt = $now;

        $dateExpr = $this->isPostgreSql()
            ? "TO_CHAR(m.created_at, 'YYYY-MM-DD')"
            : "DATE_FORMAT(m.created_at, '%Y-%m-%d')";

        $sql = sprintf(
            'SELECT %s AS day_key, COUNT(m.id) AS message_count
             FROM message m
             WHERE m.deleted_at IS NULL
               AND m.created_at >= :startAt
               AND m.created_at < :endAt
             GROUP BY 1
             ORDER BY 1 ASC',
            $dateExpr
        );

        $rows = $this->getEntityManager()->getConnection()->executeQuery(
            $sql,
            ['startAt' => $startAt, 'endAt' => $endAt],
            ['startAt' => Types::DATETIME_IMMUTABLE, 'endAt' => Types::DATETIME_IMMUTABLE]
        )->fetchAllAssociative();

        $countsByDay = [];
        foreach ($rows as $row) {
            $countsByDay[(string) $row['day_key']] = (int) $row['message_count'];
        }

        $series = [];
        for ($i = 0; $i < $days; ++$i) {
            $day = $startAt->modify(sprintf('+%d day', $i))->format('Y-m-d');
            $series[] = [
                'date' => $day,
                'count' => $countsByDay[$day] ?? 0,
            ];
        }

        return $series;
    }

    public function getTopUsers(int $limit = 10): array
    {
        $limit = max(1, min($limit, 100));

        return $this->createQueryBuilder('m')
            ->select('u.id AS userId')
            ->addSelect('u.email AS email')
            ->addSelect('u.firstName AS firstName')
            ->addSelect('u.lastName AS lastName')
            ->addSelect('COUNT(m.id) AS messageCount')
            ->addSelect('MAX(m.createdAt) AS lastActivity')
            ->join('m.sender', 'u')
            ->andWhere('m.deletedAt IS NULL')
            ->groupBy('u.id, u.email, u.firstName, u.lastName')
            ->orderBy('messageCount', 'DESC')
            ->addOrderBy('lastActivity', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();
    }

    public function getMessagesCountBetween(\DateTimeImmutable $startAt, \DateTimeImmutable $endAt): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.deletedAt IS NULL')
            ->andWhere('m.createdAt >= :startAt')
            ->andWhere('m.createdAt < :endAt')
            ->setParameter('startAt', $startAt)
            ->setParameter('endAt', $endAt)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getLatestMessages(int $limit = 15, ?string $search = null): array
    {
        $limit = max(1, min($limit, 100));

        $qb = $this->createQueryBuilder('m')
            ->select('m.id AS id')
            ->addSelect('m.content AS content')
            ->addSelect('m.type AS type')
            ->addSelect('m.createdAt AS createdAt')
            ->addSelect('u.email AS email')
            ->addSelect('u.firstName AS firstName')
            ->addSelect('u.lastName AS lastName')
            ->join('m.sender', 'u')
            ->andWhere('m.deletedAt IS NULL')
            ->orderBy('m.createdAt', 'DESC')
            ->addOrderBy('m.id', 'DESC')
            ->setMaxResults($limit);

        $search = $search !== null ? trim($search) : null;
        if ($search !== null && $search !== '') {
            $qb
                ->andWhere('LOWER(m.content) LIKE :search')
                ->setParameter('search', '%' . mb_strtolower($search) . '%');
        }

        return $qb->getQuery()->getArrayResult();
    }

    /**
     * @return Message[]
     */
    public function findPaginatedForConversation(Conversation $conversation, int $page = 1, int $limit = 30): array
    {
        $page = max(1, $page);
        $limit = max(1, min($limit, 100));
        $offset = ($page - 1) * $limit;

        $messages = $this->createQueryBuilder('m')
            ->andWhere('m.conversation = :conversation')
            ->setParameter('conversation', $conversation)
            ->orderBy('m.createdAt', 'DESC')
            ->addOrderBy('m.id', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return array_reverse($messages);
    }

    /**
     * @return Message[]
     */
    public function findAllForConversation(Conversation $conversation): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.conversation = :conversation')
            ->setParameter('conversation', $conversation)
            ->orderBy('m.createdAt', 'ASC')
            ->addOrderBy('m.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Message[]
     */
    public function findForConversation(Conversation $conversation, ?\DateTimeImmutable $before = null, int $limit = 50): array
    {
        $limit = max(1, min($limit, 100));
        $qb = $this->createQueryBuilder('m')
            ->andWhere('m.conversation = :conversation')
            ->setParameter('conversation', $conversation)
            ->orderBy('m.createdAt', 'ASC')
            ->addOrderBy('m.id', 'ASC')
            ->setMaxResults($limit);

        if ($before !== null) {
            $qb->andWhere('m.createdAt < :before')
                ->setParameter('before', $before);
        }

        return $qb->getQuery()->getResult();
    }

    public function findLastMessage(Conversation $conversation): ?Message
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.conversation = :conversation')
            ->setParameter('conversation', $conversation)
            ->orderBy('m.createdAt', 'DESC')
            ->addOrderBy('m.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return Message[]
     */
    public function findLatestForConversation(Conversation $conversation, int $limit = 20): array
    {
        $messages = $this->createQueryBuilder('m')
            ->andWhere('m.conversation = :conversation')
            ->setParameter('conversation', $conversation)
            ->orderBy('m.createdAt', 'DESC')
            ->addOrderBy('m.id', 'DESC')
            ->setMaxResults(max(1, min($limit, 100)))
            ->getQuery()
            ->getResult();

        return array_reverse($messages);
    }

    /**
     * @return Message[]
     */
    public function findRecentImages(Conversation $conversation, int $limit = 12): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.conversation = :conversation')
            ->andWhere('m.deletedAt IS NULL')
            ->andWhere('(
                EXISTS (
                    SELECT 1 FROM App\Entity\MessageAttachment ma
                    WHERE ma.message = m AND ma.isImage = :isImage
                )
                OR (m.type = :legacyType AND m.filePath IS NOT NULL)
            )')
            ->setParameter('conversation', $conversation)
            ->setParameter('isImage', true)
            ->setParameter('legacyType', 'image')
            ->orderBy('m.createdAt', 'DESC')
            ->addOrderBy('m.id', 'DESC')
            ->setMaxResults(max(1, min($limit, 100)))
            ->getQuery()
            ->getResult();
    }

    private function isPostgreSql(): bool
    {
        $platform = $this->getEntityManager()->getConnection()->getDatabasePlatform();
        if ($platform instanceof PostgreSQLPlatform) {
            return true;
        }

        return str_contains(mb_strtolower($platform::class), 'postgres');
    }
}

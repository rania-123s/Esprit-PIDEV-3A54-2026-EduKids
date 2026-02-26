<?php

namespace App\Repository\Quiz;

use App\Entity\Quiz\Quiz;
use App\Entity\Quiz\QuizAttempt;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<QuizAttempt>
 */
class QuizAttemptRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, QuizAttempt::class);
    }

    /**
     * @return QuizAttempt[]
     */
    public function findByQuiz(Quiz $quiz, int $limit = 100): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.quiz = :quiz')
            ->setParameter('quiz', $quiz)
            ->orderBy('a.completedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countByQuiz(Quiz $quiz): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.quiz = :quiz')
            ->setParameter('quiz', $quiz)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Average score (percentage) for a quiz.
     */
    public function getAverageScoreForQuiz(Quiz $quiz): float
    {
        $attempts = $this->findByQuiz($quiz, 10000);
        if (empty($attempts)) {
            return 0.0;
        }
        $sum = 0.0;
        foreach ($attempts as $a) {
            $sum += $a->getPercentage();
        }
        return round($sum / \count($attempts), 1);
    }

    /**
     * Total attempts count (all quizzes) for stats.
     */
    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Attempts in date range for chart (last N days).
     * Returns ['labels' => [...], 'values' => [...]]
     */
    public function getAttemptsByPeriod(string $period = 'day', int $days = 30): array
    {
        $since = new \DateTimeImmutable('-'.$days.' days');
        $qb = $this->createQueryBuilder('a')
            ->andWhere('a.completedAt >= :since')
            ->setParameter('since', $since);
        $attempts = $qb->getQuery()->getResult();
        $grouped = [];
        foreach ($attempts as $a) {
            $key = $a->getCompletedAt()->format($period === 'day' ? 'Y-m-d' : 'Y-m');
            $grouped[$key] = ($grouped[$key] ?? 0) + 1;
        }
        ksort($grouped);
        return [
            'labels' => array_keys($grouped),
            'values' => array_values($grouped),
        ];
    }

    public function save(QuizAttempt $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}

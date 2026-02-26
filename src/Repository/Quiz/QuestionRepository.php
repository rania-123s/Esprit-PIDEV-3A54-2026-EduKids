<?php

namespace App\Repository\Quiz;

use App\Entity\Quiz\Question;
use App\Entity\Quiz\Quiz;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Question>
 */
class QuestionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Question::class);
    }

    /**
     * @return Question[]
     */
    /**
     * @return Question[]
     */
    public function findByQuizOrdered(Quiz $quiz): array
    {
        return $this->createQueryBuilder('q')
            ->leftJoin('q.questionOptions', 'o')->addSelect('o')
            ->andWhere('q.quiz = :quiz')
            ->setParameter('quiz', $quiz)
            ->orderBy('q.ordre', 'ASC')
            ->addOrderBy('q.id', 'ASC')
            ->addOrderBy('o.ordre', 'ASC')
            ->addOrderBy('o.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function save(Question $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Question $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}

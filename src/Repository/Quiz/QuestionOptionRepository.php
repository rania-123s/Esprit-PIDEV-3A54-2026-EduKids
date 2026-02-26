<?php

namespace App\Repository\Quiz;

use App\Entity\Quiz\Question;
use App\Entity\Quiz\QuestionOption;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<QuestionOption>
 */
class QuestionOptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, QuestionOption::class);
    }

    /**
     * @return QuestionOption[]
     */
    public function findByQuestionOrdered(Question $question): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.question = :question')
            ->setParameter('question', $question)
            ->orderBy('o.ordre', 'ASC')
            ->addOrderBy('o.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function save(QuestionOption $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(QuestionOption $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}

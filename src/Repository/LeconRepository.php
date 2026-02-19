<?php

namespace App\Repository;

use App\Entity\Lecon;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Lecon>
 */
class LeconRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Lecon::class);
    }

    /**
     * Search lessons by title, media type/url, course title, or order.
     */
    public function searchLecons(string $query, ?string $sort = null): QueryBuilder
    {
        $qb = $this->createQueryBuilder('l')
            ->leftJoin('l.cours', 'c')
            ->addSelect('c');

        $terms = $this->buildSearchTerms($query);

        if ($terms === []) {
            $this->applySorting($qb, $sort);

            return $qb;
        }

        foreach ($terms as $index => $term) {
            $likeTerm = '%' . $this->escapeLikeParameter($term) . '%';
            $orX = $qb->expr()->orX(
                "LOWER(l.titre) LIKE :term$index ESCAPE '!'",
                "LOWER(l.media_type) LIKE :term$index ESCAPE '!'",
                "LOWER(l.media_url) LIKE :term$index ESCAPE '!'",
                "LOWER(l.video_url) LIKE :term$index ESCAPE '!'",
                "LOWER(l.youtube_url) LIKE :term$index ESCAPE '!'",
                "LOWER(c.titre) LIKE :term$index ESCAPE '!'"
            );

            if (ctype_digit($term)) {
                $orX->add("l.ordre = :ordre$index");
                $qb->setParameter("ordre$index", (int) $term);
            }

            $qb->andWhere($orX)
                ->setParameter("term$index", $likeTerm);
        }

        $this->applySorting($qb, $sort);

        return $qb;
    }

    /**
     * Get all lessons with sorting.
     */
    public function findAllSorted(?string $sort = null): QueryBuilder
    {
        $qb = $this->createQueryBuilder('l')
            ->leftJoin('l.cours', 'c')
            ->addSelect('c');

        $this->applySorting($qb, $sort);

        return $qb;
    }

    public function countWithCours(): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->where('l.cours IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countWithoutCours(): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->where('l.cours IS NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function applySorting(QueryBuilder $qb, ?string $sort = null): void
    {
        match ($sort) {
            'a-z' => $qb->orderBy('l.titre', 'ASC')->addOrderBy('l.id', 'DESC'),
            'ordre-asc' => $qb->orderBy('l.ordre', 'ASC')->addOrderBy('l.id', 'DESC'),
            'ordre-desc' => $qb->orderBy('l.ordre', 'DESC')->addOrderBy('l.id', 'DESC'),
            default => $qb->orderBy('l.id', 'DESC'),
        };
    }

    /**
     * Build resilient searchable terms from user input.
     * Keeps useful punctuation for URL/type fragments while also extracting plain text tokens.
     *
     * @return string[]
     */
    private function buildSearchTerms(string $query): array
    {
        $normalized = mb_strtolower(trim($query));

        if ($normalized === '') {
            return [];
        }

        $terms = [];
        $chunks = preg_split('/\s+/u', $normalized) ?: [];

        foreach ($chunks as $chunk) {
            if ($chunk === '') {
                continue;
            }

            // Useful for media_url/media_type style searches (youtube.com, mp4_file, etc.).
            $rawToken = preg_replace('/[^\p{L}\p{N}\-_.:\/]+/u', '', $chunk) ?? '';
            if ($rawToken !== '' && ($this->isNumericToken($rawToken) || mb_strlen($rawToken) >= 2)) {
                $terms[] = $rawToken;
            }

            // Also extract plain text words for title/course fields.
            $plainChunk = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $chunk) ?? '';
            $plainTokens = preg_split('/\s+/u', trim($plainChunk)) ?: [];

            foreach ($plainTokens as $plainToken) {
                if ($plainToken !== '' && ($this->isNumericToken($plainToken) || mb_strlen($plainToken) >= 2)) {
                    $terms[] = $plainToken;
                }
            }
        }

        $terms = array_values(array_unique($terms));

        return array_slice($terms, 0, 10);
    }

    private function escapeLikeParameter(string $value): string
    {
        return strtr($value, [
            '!' => '!!',
            '%' => '!%',
            '_' => '!_',
        ]);
    }

    private function isNumericToken(string $value): bool
    {
        return ctype_digit($value);
    }

    //    /**
    //     * @return Lecon[] Returns an array of Lecon objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('l')
    //            ->andWhere('l.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('l.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Lecon
    //    {
    //        return $this->createQueryBuilder('l')
    //            ->andWhere('l.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}

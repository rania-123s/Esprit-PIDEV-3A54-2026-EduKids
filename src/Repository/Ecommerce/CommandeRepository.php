<?php

namespace App\Repository\Ecommerce;

use App\Entity\Ecommerce\Commande;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Commande>
 */
class CommandeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Commande::class);
    }

    /**
     * @return Commande[]
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.user = :user')
            ->setParameter('user', $user)
            ->orderBy('c.date', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Commande[]
     */
    public function findAllOrderByDate(): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.user', 'u')->addSelect('u')
            ->orderBy('c.date', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Admin: search and sort.
     * @return Commande[]
     */
    public function searchAndSort(?string $q = null, ?string $sort = 'date', ?string $order = 'DESC'): array
    {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.user', 'u')->addSelect('u');

        if (null !== $q && '' !== trim($q)) {
            $term = '%' . trim($q) . '%';
            $qb->setParameter('q', $term);
            if (is_numeric(trim($q))) {
                $qb->andWhere('u.email LIKE :q OR c.statut LIKE :q OR c.id = :qId')
                    ->setParameter('qId', (int) trim($q));
            } else {
                $qb->andWhere('u.email LIKE :q OR c.statut LIKE :q');
            }
        }

        $order = strtoupper((string) $order) === 'ASC' ? 'ASC' : 'DESC';
        $sortField = match ($sort) {
            'date' => 'c.date',
            'montant' => 'c.montantTotal',
            'statut' => 'c.statut',
            'id' => 'c.id',
            default => 'c.date',
        };
        $qb->orderBy($sortField, $order);

        return $qb->getQuery()->getResult();
    }

    public function save(Commande $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Commande $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function countOrdersInPeriod(?\DateTimeInterface $from, ?\DateTimeInterface $to, ?array $statuses = null): int
    {
        $qb = $this->createQueryBuilder('c')->select('COUNT(c.id)');
        if ($from) {
            $qb->andWhere('c.date >= :from')->setParameter('from', $from);
        }
        if ($to) {
            $qb->andWhere('c.date <= :to')->setParameter('to', $to);
        }
        if ($statuses !== null && $statuses !== []) {
            $qb->andWhere('c.statut IN (:statuses)')->setParameter('statuses', $statuses);
        }
        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function sumRevenueInPeriod(?\DateTimeInterface $from, ?\DateTimeInterface $to, ?array $statuses = null): int
    {
        $paid = ['paye', 'confirme', 'livre'];
        $statuses = $statuses ?? $paid;
        $qb = $this->createQueryBuilder('c')->select('COALESCE(SUM(c.montantTotal), 0)');
        if ($from) {
            $qb->andWhere('c.date >= :from')->setParameter('from', $from);
        }
        if ($to) {
            $qb->andWhere('c.date <= :to')->setParameter('to', $to);
        }
        if ($statuses !== []) {
            $qb->andWhere('c.statut IN (:statuses)')->setParameter('statuses', $statuses);
        }
        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @return array{statut: string, count: int}[]
     */
    public function countByStatus(): array
    {
        $rows = $this->createQueryBuilder('c')
            ->select('c.statut', 'COUNT(c.id) as cnt')
            ->groupBy('c.statut')
            ->getQuery()
            ->getResult();
        return array_map(fn ($r) => ['statut' => $r['statut'], 'count' => (int) $r['cnt']], $rows);
    }

    /**
     * Top products by quantity sold (paid orders only). Returns array of [produit_id, total_qty, total_revenue]
     * @return array<int, array{produit_id: int, total_qty: int, total_revenue: int}>
     */
    public function getTopProductsByQuantity(int $limit = 10, ?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = 'SELECT l.produit_id, SUM(l.quantite) as total_qty, SUM(l.quantite * l.prix_unitaire) as total_revenue
                FROM ecommerce_ligne_commande l
                INNER JOIN ecommerce_commande c ON c.id = l.commande_id
                WHERE c.statut IN (\'paye\', \'confirme\', \'livre\')';
        $params = [];
        if ($from) {
            $sql .= ' AND c.date >= :from';
            $params['from'] = $from->format('Y-m-d');
        }
        if ($to) {
            $sql .= ' AND c.date <= :to';
            $params['to'] = $to->format('Y-m-d 23:59:59');
        }
        $sql .= ' GROUP BY l.produit_id ORDER BY total_qty DESC LIMIT ' . (int) $limit;
        $stmt = $conn->executeQuery($sql, $params);
        $result = [];
        foreach ($stmt->fetchAllAssociative() as $row) {
            $result[(int) $row['produit_id']] = [
                'produit_id' => (int) $row['produit_id'],
                'total_qty' => (int) $row['total_qty'],
                'total_revenue' => (int) $row['total_revenue'],
            ];
        }
        return $result;
    }

    /**
     * Sales by period (day/week/month) for chart. Returns [labels, values] for revenue.
     * @return array{labels: string[], values: int[]}
     */
    public function getSalesByPeriod(string $groupBy = 'day', int $days = 30): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $format = match ($groupBy) {
            'month' => '%Y-%m',
            'week' => '%Y-%u',
            default => '%Y-%m-%d',
        };
        $interval = match ($groupBy) {
            'month' => 'INTERVAL ' . min(365, $days) . ' DAY',
            'week' => 'INTERVAL ' . min(365, $days) . ' DAY',
            default => 'INTERVAL ' . min(365, $days) . ' DAY',
        };
        $sql = "SELECT DATE_FORMAT(c.date, :format) as period, COALESCE(SUM(c.montant_total), 0) as total
                FROM ecommerce_commande c
                WHERE c.statut IN ('paye', 'confirme', 'livre') AND c.date >= DATE_SUB(CURDATE(), " . $interval . ")
                GROUP BY period ORDER BY period";
        $stmt = $conn->executeQuery(str_replace(':format', "'" . $format . "'", $sql));
        $labels = [];
        $values = [];
        foreach ($stmt->fetchAllAssociative() as $row) {
            $labels[] = $row['period'];
            $values[] = (int) $row['total'];
        }
        return ['labels' => $labels, 'values' => $values];
    }
}

<?php

namespace App\Repository;

use App\Entity\WorkLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WorkLog>
 */
class WorkLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WorkLog::class);
    }

    public function findActiveForUser(int $userId): ?WorkLog
    {
        return $this->createQueryBuilder('w')
            ->where('w.user = :userId')
            ->andWhere('w.loggedOutAt IS NULL')
            ->setParameter('userId', $userId)
            ->orderBy('w.loggedInAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Pointages d'un utilisateur sur une période.
     * @return WorkLog[]
     */
    public function findForUser(int $userId, string $from, string $to): array
    {
        return $this->createQueryBuilder('w')
            ->where('w.user = :userId')
            ->andWhere('DATE(w.loggedInAt) >= :from')
            ->andWhere('DATE(w.loggedInAt) <= :to')
            ->setParameter('userId', $userId)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('w.loggedInAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Liste filtrée pour admin/supervisor.
     * @return WorkLog[]
     */
    public function findFiltered(array $filters): array
    {
        $qb = $this->createQueryBuilder('w')
            ->leftJoin('w.user', 'u')
            ->addSelect('u')
            ->orderBy('w.loggedInAt', 'DESC')
            ->setMaxResults(500);

        if (!empty($filters['from'])) {
            $qb->andWhere('DATE(w.loggedInAt) >= :from')->setParameter('from', $filters['from']);
        }
        if (!empty($filters['to'])) {
            $qb->andWhere('DATE(w.loggedInAt) <= :to')->setParameter('to', $filters['to']);
        }
        if (!empty($filters['userId'])) {
            $qb->andWhere('w.user = :userId')->setParameter('userId', (int) $filters['userId']);
        }
        if (!empty($filters['approvalStatus'])) {
            $qb->andWhere('w.approvalStatus = :status')->setParameter('status', $filters['approvalStatus']);
        }
        if (!empty($filters['supervisorId'])) {
            $qb->andWhere('u.supervisor = :supervisorId')->setParameter('supervisorId', (int) $filters['supervisorId']);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Durée totale de présence en minutes pour un utilisateur sur une période.
     */
    public function totalMinutesForUser(int $userId, string $from, string $to): int
    {
        $result = $this->createQueryBuilder('w')
            ->select('SUM(TIMESTAMPDIFF(MINUTE, w.loggedInAt, COALESCE(w.loggedOutAt, CURRENT_TIMESTAMP()))) AS total')
            ->where('w.user = :userId')
            ->andWhere('DATE(w.loggedInAt) >= :from')
            ->andWhere('DATE(w.loggedInAt) <= :to')
            ->andWhere('w.loggedOutAt IS NOT NULL')
            ->setParameter('userId', $userId)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) ($result ?? 0);
    }

    /**
     * Nombre de sessions sur une période.
     */
    public function countSessionsForUser(int $userId, string $from, string $to): int
    {
        return (int) $this->createQueryBuilder('w')
            ->select('COUNT(w.id)')
            ->where('w.user = :userId')
            ->andWhere('DATE(w.loggedInAt) >= :from')
            ->andWhere('DATE(w.loggedInAt) <= :to')
            ->setParameter('userId', $userId)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Nombre d'agents actuellement loggés (sans loggedOutAt).
     */
    public function countActiveNow(): int
    {
        return (int) $this->createQueryBuilder('w')
            ->select('COUNT(DISTINCT w.user)')
            ->where('w.loggedOutAt IS NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }
}

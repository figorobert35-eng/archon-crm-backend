<?php

namespace App\Repository;

use App\Entity\BreakLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BreakLog>
 */
class BreakLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BreakLog::class);
    }

    public function findActiveForUser(int $userId): ?BreakLog
    {
        return $this->createQueryBuilder('b')
            ->where('b.user = :userId')
            ->andWhere('b.endedAt IS NULL')
            ->setParameter('userId', $userId)
            ->orderBy('b.startedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Vérifie si un type de pause a déjà été utilisé aujourd'hui.
     */
    public function hasUsedBreakToday(int $userId, string $breakType, string $today): bool
    {
        $count = (int) $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->where('b.user = :userId')
            ->andWhere('b.breakType = :type')
            ->andWhere('DATE(b.startedAt) = :today')
            ->setParameter('userId', $userId)
            ->setParameter('type', $breakType)
            ->setParameter('today', $today)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }
}

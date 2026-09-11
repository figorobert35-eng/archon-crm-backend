<?php

namespace App\Repository;

use App\Entity\Lead;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Lead>
 */
class LeadRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Lead::class);
    }

    /**
     * Liste filtrée (max 300) — pour la page portefeuille.
     * @return Lead[]
     */
    public function findFiltered(User $user, array $filters): array
    {
        return $this->buildQuery($user, $filters)
            ->setMaxResults(300)
            ->getQuery()
            ->getResult();
    }

    /**
     * Liste filtrée sans limite — pour l'export CSV.
     * @return Lead[]
     */
    public function findFilteredUnlimited(User $user, array $filters): array
    {
        return $this->buildQuery($user, $filters)
            ->getQuery()
            ->getResult();
    }

    /**
     * QueryBuilder partagé par findFiltered et findFilteredUnlimited.
     */
    private function buildQuery(User $user, array $filters): \Doctrine\ORM\QueryBuilder
    {
        $qb = $this->createQueryBuilder('l')
            ->leftJoin('l.assignedTo', 'u')
            ->leftJoin('l.campaign', 'c')
            ->addSelect('u', 'c')
            ->orderBy('l.updatedAt', 'DESC');

        if ($user->getRole() === 'agent') {
            $qb->andWhere('l.assignedTo = :currentUser')
               ->setParameter('currentUser', $user);
        }

        if (!empty($filters['status'])) {
            $qb->andWhere('l.status = :status')
               ->setParameter('status', $filters['status']);
        }

        if (!empty($filters['assigned'])) {
            if ($filters['assigned'] === 'none') {
                $qb->andWhere('l.assignedTo IS NULL');
            } else {
                $qb->andWhere('l.assignedTo = :assigned')
                   ->setParameter('assigned', (int) $filters['assigned']);
            }
        }

        if (!empty($filters['campaign'])) {
            $qb->andWhere('l.campaign = :campaign')
               ->setParameter('campaign', (int) $filters['campaign']);
        }

        if (!empty($filters['injection'])) {
            $qb->andWhere('l.injectionDate = :injection')
               ->setParameter('injection', new \DateTime($filters['injection']));
        }

        if (!empty($filters['q'])) {
            $q = '%' . $filters['q'] . '%';
            $qb->andWhere(
                $qb->expr()->orX(
                    'l.clientName LIKE :q',
                    'l.phone LIKE :q',
                    'l.phone2 LIKE :q',
                    'l.email LIKE :q',
                    'l.city LIKE :q',
                    'l.requestDetails LIKE :q'
                )
            )->setParameter('q', $q);
        }

        if (!empty($filters['quick'])) {
            $now = new \DateTime();
            match ($filters['quick']) {
                'new'      => $qb->andWhere('l.status = :qs')->setParameter('qs', 'Nouveau'),
                'late'     => $qb->andWhere('l.callbackAt < :now')->setParameter('now', $now),
                'today'    => $qb->andWhere('l.callbackAt >= :today AND l.callbackAt < :tomorrow')
                                 ->setParameter('today', new \DateTime('today'))
                                 ->setParameter('tomorrow', new \DateTime('tomorrow')),
                'callback' => $qb->andWhere('l.callbackAt IS NOT NULL'),
                default    => null,
            };
        }

        return $qb;
    }

    /**
     * Comptage par statut pour le dashboard.
     * @return array<string,int>
     */
    public function countByStatus(?User $user = null): array
    {
        $qb = $this->createQueryBuilder('l')
            ->select('l.status, COUNT(l.id) AS cnt')
            ->groupBy('l.status');

        if ($user && $user->getRole() === 'agent') {
            $qb->andWhere('l.assignedTo = :user')->setParameter('user', $user);
        }

        $result = [];
        foreach ($qb->getQuery()->getArrayResult() as $row) {
            $result[$row['status']] = (int) $row['cnt'];
        }
        return $result;
    }

    /**
     * Rappels du jour.
     * @return Lead[]
     */
    public function findCallbacksToday(User $user): array
    {
        $qb = $this->createQueryBuilder('l')
            ->where('l.callbackAt >= :today')
            ->andWhere('l.callbackAt < :tomorrow')
            ->setParameter('today', new \DateTime('today'))
            ->setParameter('tomorrow', new \DateTime('tomorrow'))
            ->orderBy('l.callbackAt', 'ASC');

        if ($user->getRole() === 'agent') {
            $qb->andWhere('l.assignedTo = :user')->setParameter('user', $user);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Tous les rappels planifiés avec filtre quick : all | late | today | future.
     * @return Lead[]
     */
    public function findReminders(User $user, string $quick = 'all'): array
    {
        $now      = new \DateTime();
        $today    = new \DateTime('today');
        $tomorrow = new \DateTime('tomorrow');

        $qb = $this->createQueryBuilder('l')
            ->leftJoin('l.assignedTo', 'u')
            ->leftJoin('l.campaign', 'c')
            ->addSelect('u', 'c')
            ->where('l.callbackAt IS NOT NULL')
            ->orderBy('l.callbackAt', 'ASC');

        if ($user->getRole() === 'agent') {
            $qb->andWhere('l.assignedTo = :user')->setParameter('user', $user);
        } elseif ($user->getRole() === 'supervisor') {
            $qb->andWhere('u.supervisor = :supervisor')->setParameter('supervisor', $user);
        }

        match ($quick) {
            'late'   => $qb->andWhere('l.callbackAt < :now')->setParameter('now', $now),
            'today'  => $qb->andWhere('l.callbackAt >= :today AND l.callbackAt < :tomorrow')
                           ->setParameter('today', $today)->setParameter('tomorrow', $tomorrow),
            'future' => $qb->andWhere('l.callbackAt >= :tomorrow')->setParameter('tomorrow', $tomorrow),
            default  => null,
        };

        return $qb->setMaxResults(200)->getQuery()->getResult();
    }

    /** Leads traités (codification) par un agent sur une période. */
    public function countTreatedByAgent(int $agentId, string $from, string $to): int
    {
        return (int) $this->getEntityManager()
            ->createQuery('
                SELECT COUNT(DISTINCT e.lead)
                FROM App\Entity\LeadEvent e
                WHERE e.user = :agentId
                  AND e.eventType = :type
                  AND DATE(e.createdAt) >= :from
                  AND DATE(e.createdAt) <= :to
            ')
            ->setParameter('agentId', $agentId)
            ->setParameter('type', 'codification')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getSingleScalarResult();
    }

    /** @return array<string,int> */
    public function countByStatusForAgent(int $agentId): array
    {
        $result = [];
        foreach ($this->createQueryBuilder('l')
            ->select('l.status, COUNT(l.id) AS cnt')
            ->where('l.assignedTo = :agentId')
            ->setParameter('agentId', $agentId)
            ->groupBy('l.status')
            ->getQuery()
            ->getArrayResult() as $row) {
            $result[$row['status']] = (int) $row['cnt'];
        }
        return $result;
    }

    /** @return array<string,int> */
    public function countByStatusGlobal(string $from, string $to, ?int $agentId = null): array
    {
        $qb = $this->getEntityManager()->createQueryBuilder()
            ->select('l.status, COUNT(l.id) AS cnt')
            ->from('App\Entity\Lead', 'l')
            ->where('DATE(l.updatedAt) >= :from')
            ->andWhere('DATE(l.updatedAt) <= :to')
            ->groupBy('l.status')
            ->setParameter('from', $from)
            ->setParameter('to', $to);

        if ($agentId) {
            $qb->andWhere('l.assignedTo = :agentId')->setParameter('agentId', $agentId);
        }

        $result = [];
        foreach ($qb->getQuery()->getArrayResult() as $row) {
            $result[$row['status']] = (int) $row['cnt'];
        }
        return $result;
    }

    public function countCreatedToday(): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->where('DATE(l.createdAt) = :today')
            ->setParameter('today', (new \DateTime())->format('Y-m-d'))
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countLateCallbacks(): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->where('l.callbackAt IS NOT NULL')
            ->andWhere('l.callbackAt < :now')
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getSingleScalarResult();
    }
}

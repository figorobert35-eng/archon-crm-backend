<?php

namespace App\Repository;

use App\Entity\Announcement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Announcement>
 */
class AnnouncementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Announcement::class);
    }

    /**
     * Retourne la première annonce active non lue pour cet utilisateur.
     */
    public function findPendingForUser(int $userId, string $role): ?Announcement
    {
        // Sous-requête : IDs des annonces déjà acquittées par cet utilisateur
        $ackedIds = $this->getEntityManager()
            ->createQuery('
                SELECT IDENTITY(r.announcement)
                FROM App\Entity\AnnouncementRead r
                WHERE r.user = :userId
                  AND r.acknowledgedAt IS NOT NULL
            ')
            ->setParameter('userId', $userId)
            ->getSingleColumnResult();

        // Sous-requête : IDs des annonces reportées (remind_after dans le futur)
        $snoozedIds = $this->getEntityManager()
            ->createQuery('
                SELECT IDENTITY(r.announcement)
                FROM App\Entity\AnnouncementRead r
                WHERE r.user = :userId
                  AND r.remindAfter IS NOT NULL
                  AND r.remindAfter > :now
            ')
            ->setParameter('userId', $userId)
            ->setParameter('now', new \DateTime())
            ->getSingleColumnResult();

        $excludedIds = array_unique(array_merge($ackedIds, $snoozedIds));

        $qb = $this->createQueryBuilder('a')
            ->where('a.active = true')
            ->andWhere('(a.targetRole = :all OR a.targetRole = :role)')
            ->setParameter('all', 'all')
            ->setParameter('role', $role)
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults(1);

        if (!empty($excludedIds)) {
            $qb->andWhere('a.id NOT IN (:excludedIds)')
               ->setParameter('excludedIds', $excludedIds);
        }

        return $qb->getQuery()->getOneOrNullResult();
    }
}

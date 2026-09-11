<?php

namespace App\Repository;

use App\Entity\LeadEvent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LeadEvent>
 */
class LeadEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LeadEvent::class);
    }

    /** @return LeadEvent[] */
    public function findByLead(int $leadId): array
    {
        return $this->createQueryBuilder('e')
            ->leftJoin('e.user', 'u')
            ->addSelect('u')
            ->where('e.lead = :leadId')
            ->setParameter('leadId', $leadId)
            ->orderBy('e.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}

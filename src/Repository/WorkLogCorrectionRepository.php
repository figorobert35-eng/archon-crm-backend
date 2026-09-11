<?php

namespace App\Repository;

use App\Entity\WorkLogCorrection;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WorkLogCorrection>
 */
class WorkLogCorrectionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WorkLogCorrection::class);
    }
}

<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>

namespace App\Repository;

use App\Entity\FormationModule;
use App\Entity\Formation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FormationModule>
 */
class FormationModuleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FormationModule::class);
    }

    public function findActiveByFormation(Formation $formation): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.formation = :formation')
            ->andWhere('m.isActive = :active')
            ->setParameter('formation', $formation)
            ->setParameter('active', true)
            ->orderBy('m.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getNextPosition(Formation $formation): int
    {
        $result = $this->createQueryBuilder('m')
            ->select('MAX(m.position)')
            ->andWhere('m.formation = :formation')
            ->setParameter('formation', $formation)
            ->getQuery()
            ->getSingleScalarResult();

        return ($result ?? 0) + 1;
    }

    public function getTotalDuration(Formation $formation): int
    {
        $result = $this->createQueryBuilder('m')
            ->select('SUM(m.duration)')
            ->andWhere('m.formation = :formation')
            ->andWhere('m.isActive = :active')
            ->setParameter('formation', $formation)
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ?? 0;
    }
}
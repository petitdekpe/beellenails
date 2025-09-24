<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>

namespace App\Repository;

use App\Entity\ModuleProgress;
use App\Entity\FormationEnrollment;
use App\Entity\FormationModule;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ModuleProgress>
 */
class ModuleProgressRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ModuleProgress::class);
    }

    public function findByEnrollment(FormationEnrollment $enrollment): array
    {
        return $this->createQueryBuilder('mp')
            ->andWhere('mp.enrollment = :enrollment')
            ->setParameter('enrollment', $enrollment)
            ->leftJoin('mp.module', 'm')
            ->orderBy('m.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByEnrollmentAndModule(FormationEnrollment $enrollment, FormationModule $module): ?ModuleProgress
    {
        return $this->createQueryBuilder('mp')
            ->andWhere('mp.enrollment = :enrollment')
            ->andWhere('mp.module = :module')
            ->setParameter('enrollment', $enrollment)
            ->setParameter('module', $module)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function getEnrollmentProgress(FormationEnrollment $enrollment): array
    {
        $result = $this->createQueryBuilder('mp')
            ->select('
                COUNT(mp.id) as totalModules,
                SUM(CASE WHEN mp.completed = true THEN 1 ELSE 0 END) as completedModules,
                SUM(CASE WHEN mp.started = true THEN 1 ELSE 0 END) as startedModules,
                SUM(mp.timeSpent) as totalTimeSpent,
                AVG(mp.completionPercentage) as avgCompletion
            ')
            ->andWhere('mp.enrollment = :enrollment')
            ->setParameter('enrollment', $enrollment)
            ->getQuery()
            ->getSingleResult();

        return [
            'totalModules' => (int) $result['totalModules'],
            'completedModules' => (int) $result['completedModules'],
            'startedModules' => (int) $result['startedModules'],
            'totalTimeSpent' => (int) ($result['totalTimeSpent'] ?? 0),
            'avgCompletion' => round((float) ($result['avgCompletion'] ?? 0), 1),
            'completionRate' => $result['totalModules'] > 0 ? 
                round(($result['completedModules'] / $result['totalModules']) * 100, 1) : 0
        ];
    }

    public function findNextModule(FormationEnrollment $enrollment): ?ModuleProgress
    {
        return $this->createQueryBuilder('mp')
            ->andWhere('mp.enrollment = :enrollment')
            ->andWhere('mp.completed = false')
            ->setParameter('enrollment', $enrollment)
            ->leftJoin('mp.module', 'm')
            ->orderBy('m.position', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findInProgressModules(FormationEnrollment $enrollment): array
    {
        return $this->createQueryBuilder('mp')
            ->andWhere('mp.enrollment = :enrollment')
            ->andWhere('mp.started = true')
            ->andWhere('mp.completed = false')
            ->setParameter('enrollment', $enrollment)
            ->leftJoin('mp.module', 'm')
            ->orderBy('mp.lastAccessedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function getTotalTimeSpentByUser(\App\Entity\User $user): int
    {
        $result = $this->createQueryBuilder('mp')
            ->select('SUM(mp.timeSpent)')
            ->leftJoin('mp.enrollment', 'e')
            ->andWhere('e.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) ($result ?? 0);
    }

    public function getCompletedModulesCountByUser(\App\Entity\User $user): int
    {
        return $this->createQueryBuilder('mp')
            ->select('COUNT(mp.id)')
            ->leftJoin('mp.enrollment', 'e')
            ->andWhere('e.user = :user')
            ->andWhere('mp.completed = true')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>

namespace App\Repository;

use App\Entity\FormationEnrollment;
use App\Entity\User;
use App\Entity\Formation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FormationEnrollment>
 */
class FormationEnrollmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FormationEnrollment::class);
    }

    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.user = :user')
            ->setParameter('user', $user)
            ->orderBy('e.enrolledAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findActiveByUser(User $user): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.user = :user')
            ->andWhere('e.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', 'active')
            ->orderBy('e.enrolledAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findCompletedByUser(User $user): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.user = :user')
            ->andWhere('e.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', 'completed')
            ->orderBy('e.completedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findUserEnrollment(User $user, Formation $formation): ?FormationEnrollment
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.user = :user')
            ->andWhere('e.formation = :formation')
            ->setParameter('user', $user)
            ->setParameter('formation', $formation)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findExpiringInDays(int $days): array
    {
        $targetDate = new \DateTime();
        $targetDate->add(new \DateInterval('P' . $days . 'D'));

        return $this->createQueryBuilder('e')
            ->andWhere('e.status = :status')
            ->andWhere('e.expiresAt <= :targetDate')
            ->andWhere('e.expiresAt > :now')
            ->andWhere('e.expirationNotifiedAt IS NULL OR e.expirationNotifiedAt < :notificationThreshold')
            ->setParameter('status', 'active')
            ->setParameter('targetDate', $targetDate)
            ->setParameter('now', new \DateTime())
            ->setParameter('notificationThreshold', (new \DateTime())->sub(new \DateInterval('P7D')))
            ->getQuery()
            ->getResult();
    }

    public function findExpired(): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.status = :status')
            ->andWhere('e.expiresAt < :now')
            ->setParameter('status', 'active')
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getResult();
    }

    public function getUserStats(User $user): array
    {
        $qb = $this->createQueryBuilder('e')
            ->select('
                COUNT(e.id) as total,
                SUM(CASE WHEN e.status = \'active\' THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN e.status = \'completed\' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN e.status = \'expired\' THEN 1 ELSE 0 END) as expired,
                AVG(e.progressPercentage) as avgProgress
            ')
            ->andWhere('e.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleResult();

        return [
            'total' => (int) $qb['total'],
            'active' => (int) $qb['active'],
            'completed' => (int) $qb['completed'],
            'expired' => (int) $qb['expired'],
            'avgProgress' => round((float) $qb['avgProgress'], 1)
        ];
    }

    public function findInProgressByUser(User $user): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.user = :user')
            ->andWhere('e.status = :status')
            ->andWhere('e.progressPercentage > 0')
            ->andWhere('e.progressPercentage < 100')
            ->setParameter('user', $user)
            ->setParameter('status', 'active')
            ->orderBy('e.lastAccessedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findRecentlyAccessedByUser(User $user, int $limit = 3): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.user = :user')
            ->andWhere('e.status = :status')
            ->andWhere('e.lastAccessedAt IS NOT NULL')
            ->setParameter('user', $user)
            ->setParameter('status', 'active')
            ->orderBy('e.lastAccessedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>

namespace App\Repository;

use App\Entity\FormationReview;
use App\Entity\Formation;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FormationReview>
 */
class FormationReviewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FormationReview::class);
    }

    public function findVisibleByFormation(Formation $formation): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.formation = :formation')
            ->andWhere('r.isVisible = :visible')
            ->andWhere('r.approved = :approved')
            ->setParameter('formation', $formation)
            ->setParameter('visible', true)
            ->setParameter('approved', true)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.user = :user')
            ->setParameter('user', $user)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function hasUserReviewedFormation(User $user, Formation $formation): bool
    {
        $result = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.user = :user')
            ->andWhere('r.formation = :formation')
            ->setParameter('user', $user)
            ->setParameter('formation', $formation)
            ->getQuery()
            ->getSingleScalarResult();

        return $result > 0;
    }

    public function getAverageRating(Formation $formation): ?float
    {
        $result = $this->createQueryBuilder('r')
            ->select('AVG(r.rating)')
            ->andWhere('r.formation = :formation')
            ->andWhere('r.isVisible = :visible')
            ->andWhere('r.approved = :approved')
            ->setParameter('formation', $formation)
            ->setParameter('visible', true)
            ->setParameter('approved', true)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ? round((float) $result, 1) : null;
    }

    public function getReviewsStats(Formation $formation): array
    {
        $qb = $this->createQueryBuilder('r')
            ->select('r.rating, COUNT(r.id) as count')
            ->andWhere('r.formation = :formation')
            ->andWhere('r.isVisible = :visible')
            ->andWhere('r.approved = :approved')
            ->setParameter('formation', $formation)
            ->setParameter('visible', true)
            ->setParameter('approved', true)
            ->groupBy('r.rating')
            ->orderBy('r.rating', 'DESC');

        $results = $qb->getQuery()->getResult();

        $stats = [
            5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0,
            'total' => 0,
            'average' => 0
        ];

        foreach ($results as $result) {
            $stats[$result['rating']] = $result['count'];
            $stats['total'] += $result['count'];
        }

        if ($stats['total'] > 0) {
            $average = $this->getAverageRating($formation);
            $stats['average'] = $average ?? 0;
        }

        return $stats;
    }

    public function findAllByFormation(Formation $formation): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.formation = :formation')
            ->setParameter('formation', $formation)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findPendingReviews(): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.approved = :approved')
            ->setParameter('approved', false)
            ->orderBy('r.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
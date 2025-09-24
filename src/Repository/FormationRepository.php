<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>


namespace App\Repository;

use App\Entity\Formation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Formation>
 *
 * @method Formation|null find($id, $lockMode = null, $lockVersion = null)
 * @method Formation|null findOneBy(array $criteria, array $orderBy = null)
 * @method Formation[]    findAll()
 * @method Formation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FormationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Formation::class);
    }

    public function findActive(): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('f.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findWithFilters(array $filters = []): array
    {
        $qb = $this->createQueryBuilder('f')
            ->andWhere('f.isActive = :active')
            ->setParameter('active', true);

        if (isset($filters['isFree']) && $filters['isFree'] !== '') {
            $qb->andWhere('f.isFree = :isFree')
               ->setParameter('isFree', (bool) $filters['isFree']);
        }

        if (!empty($filters['theme'])) {
            $qb->andWhere('f.theme = :theme')
               ->setParameter('theme', $filters['theme']);
        }

        if (!empty($filters['level'])) {
            $qb->andWhere('f.level = :level')
               ->setParameter('level', $filters['level']);
        }

        if (!empty($filters['minDuration'])) {
            $qb->andWhere('f.duration >= :minDuration')
               ->setParameter('minDuration', $filters['minDuration']);
        }

        if (!empty($filters['maxDuration'])) {
            $qb->andWhere('f.duration <= :maxDuration')
               ->setParameter('maxDuration', $filters['maxDuration']);
        }

        if (isset($filters['availability']) && $filters['availability'] === 'available') {
            $now = new \DateTime();
            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->eq('f.accessType', ':relativeAccess'),
                    $qb->expr()->andX(
                        $qb->expr()->eq('f.accessType', ':fixedAccess'),
                        $qb->expr()->lte('f.startDate', ':now'),
                        $qb->expr()->gte('f.endDate', ':now')
                    )
                )
            )
            ->setParameter('relativeAccess', 'relative')
            ->setParameter('fixedAccess', 'fixed')
            ->setParameter('now', $now);
        }

        if (!empty($filters['search'])) {
            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->like('f.Nom', ':search'),
                    $qb->expr()->like('f.Description', ':search'),
                    $qb->expr()->like('f.targetAudience', ':search')
                )
            )->setParameter('search', '%' . $filters['search'] . '%');
        }

        $orderBy = $filters['orderBy'] ?? 'newest';
        switch ($orderBy) {
            case 'price_asc':
                $qb->orderBy('f.Cout', 'ASC');
                break;
            case 'price_desc':
                $qb->orderBy('f.Cout', 'DESC');
                break;
            case 'duration_asc':
                $qb->orderBy('f.duration', 'ASC');
                break;
            case 'duration_desc':
                $qb->orderBy('f.duration', 'DESC');
                break;
            case 'name':
                $qb->orderBy('f.Nom', 'ASC');
                break;
            default: // newest
                $qb->orderBy('f.createdAt', 'DESC');
                break;
        }

        return $qb->getQuery()->getResult();
    }

    public function getAvailableThemes(): array
    {
        $result = $this->createQueryBuilder('f')
            ->select('DISTINCT f.theme')
            ->andWhere('f.isActive = :active')
            ->andWhere('f.theme IS NOT NULL')
            ->setParameter('active', true)
            ->orderBy('f.theme', 'ASC')
            ->getQuery()
            ->getScalarResult();

        return array_column($result, 'theme');
    }

    public function getAvailableLevels(): array
    {
        $result = $this->createQueryBuilder('f')
            ->select('DISTINCT f.level')
            ->andWhere('f.isActive = :active')
            ->andWhere('f.level IS NOT NULL')
            ->setParameter('active', true)
            ->orderBy('f.level', 'ASC')
            ->getQuery()
            ->getScalarResult();

        return array_column($result, 'level');
    }

    public function getDurationRange(): array
    {
        $result = $this->createQueryBuilder('f')
            ->select('MIN(f.duration) as minDuration, MAX(f.duration) as maxDuration')
            ->andWhere('f.isActive = :active')
            ->andWhere('f.duration IS NOT NULL')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleResult();

        return [
            'min' => $result['minDuration'] ?? 0,
            'max' => $result['maxDuration'] ?? 0
        ];
    }

    public function findPopular(int $limit = 6): array
    {
        return $this->createQueryBuilder('f')
            ->select('f, AVG(r.rating) as avgRating, COUNT(r.id) as reviewCount')
            ->leftJoin('f.reviews', 'r', 'WITH', 'r.isVisible = :visible')
            ->andWhere('f.isActive = :active')
            ->setParameter('active', true)
            ->setParameter('visible', true)
            ->groupBy('f.id')
            ->having('reviewCount > 0')
            ->orderBy('avgRating', 'DESC')
            ->addOrderBy('reviewCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}

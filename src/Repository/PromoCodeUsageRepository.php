<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>

namespace App\Repository;

use App\Entity\PromoCodeUsage;
use App\Entity\PromoCode;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PromoCodeUsage>
 */
class PromoCodeUsageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PromoCodeUsage::class);
    }

    /**
     * Compte les utilisations validées d'un code par un utilisateur
     */
    public function countValidatedUsagesByUser(PromoCode $promoCode, User $user): int
    {
        return $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.promoCode = :promoCode')
            ->andWhere('u.user = :user')
            ->andWhere('u.status = :status')
            ->setParameter('promoCode', $promoCode)
            ->setParameter('user', $user)
            ->setParameter('status', PromoCodeUsage::STATUS_VALIDATED)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Récupère les tentatives récentes d'un utilisateur (anti-spam)
     */
    public function findRecentAttemptsByUser(User $user, int $minutes = 5): array
    {
        $since = new \DateTime("-{$minutes} minutes");
        
        return $this->createQueryBuilder('u')
            ->where('u.user = :user')
            ->andWhere('u.attemptedAt >= :since')
            ->setParameter('user', $user)
            ->setParameter('since', $since)
            ->orderBy('u.attemptedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques globales des codes promo
     */
    public function getGlobalStats(): array
    {
        $totalAttempts = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $validatedUsages = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.status = :status')
            ->setParameter('status', PromoCodeUsage::STATUS_VALIDATED)
            ->getQuery()
            ->getSingleScalarResult();

        $totalDiscount = $this->createQueryBuilder('u')
            ->select('SUM(u.discountAmount)')
            ->where('u.status = :status')
            ->setParameter('status', PromoCodeUsage::STATUS_VALIDATED)
            ->getQuery()
            ->getSingleScalarResult();

        $revokedUsages = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.status = :status')
            ->setParameter('status', PromoCodeUsage::STATUS_REVOKED)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'totalAttempts' => (int) $totalAttempts,
            'validatedUsages' => (int) $validatedUsages,
            'revokedUsages' => (int) $revokedUsages,
            'totalDiscount' => (float) ($totalDiscount ?? 0),
            'successRate' => $totalAttempts > 0 ? ($validatedUsages / $totalAttempts) * 100 : 0
        ];
    }

    /**
     * Usage par période
     */
    public function getUsageByPeriod(\DateTime $from, \DateTime $to): array
    {
        $conn = $this->getEntityManager()->getConnection();
        
        $sql = '
            SELECT 
                DATE(validated_at) as date, 
                COUNT(id) as count, 
                SUM(discount_amount) as totalDiscount
            FROM promo_code_usage 
            WHERE status = :status 
            AND validated_at BETWEEN :from AND :to 
            GROUP BY DATE(validated_at)
            ORDER BY date ASC
        ';
        
        $result = $conn->executeQuery($sql, [
            'status' => PromoCodeUsage::STATUS_VALIDATED,
            'from' => $from->format('Y-m-d H:i:s'),
            'to' => $to->format('Y-m-d H:i:s')
        ]);
        
        return $result->fetchAllAssociative();
    }

    /**
     * Top des utilisateurs avec le plus de codes utilisés
     */
    public function findTopUsers(int $limit = 10): array
    {
        return $this->createQueryBuilder('u')
            ->select('user.id as userId, user.Nom as nom, user.Prenom as prenom, user.email as email, COUNT(u.id) as usageCount, SUM(u.discountAmount) as totalSaved')
            ->leftJoin('u.user', 'user')
            ->where('u.status = :status')
            ->setParameter('status', PromoCodeUsage::STATUS_VALIDATED)
            ->groupBy('user.id, user.Nom, user.Prenom, user.email')
            ->orderBy('usageCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche les tentatives suspectes (même IP, même user agent)
     */
    public function findSuspiciousAttempts(int $hours = 24): array
    {
        $since = new \DateTime("-{$hours} hours");
        
        return $this->createQueryBuilder('u')
            ->select('u.ipAddress, u.userAgent, COUNT(u.id) as attemptCount')
            ->where('u.attemptedAt >= :since')
            ->andWhere('u.ipAddress IS NOT NULL')
            ->setParameter('since', $since)
            ->groupBy('u.ipAddress, u.userAgent')
            ->having('attemptCount > 10')
            ->orderBy('attemptCount', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
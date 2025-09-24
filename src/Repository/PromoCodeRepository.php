<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>

namespace App\Repository;

use App\Entity\PromoCode;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PromoCode>
 */
class PromoCodeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PromoCode::class);
    }

    /**
     * Trouve un code promo par son code
     */
    public function findActiveByCode(string $code): ?PromoCode
    {
        return $this->createQueryBuilder('p')
            ->where('p.code = :code')
            ->andWhere('p.isActive = true')
            ->setParameter('code', strtoupper($code))
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Vérifie si un code peut être utilisé par un utilisateur
     */
    public function canBeUsedByUser(PromoCode $promoCode, User $user): bool
    {
        if ($promoCode->getMaxUsagePerUser() === null) {
            return true;
        }

        $usageCount = $this->getEntityManager()
            ->getRepository('App:PromoCodeUsage')
            ->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.promoCode = :promoCode')
            ->andWhere('u.user = :user')
            ->andWhere('u.status = :status')
            ->setParameter('promoCode', $promoCode)
            ->setParameter('user', $user)
            ->setParameter('status', 'validated')
            ->getQuery()
            ->getSingleScalarResult();

        return $usageCount < $promoCode->getMaxUsagePerUser();
    }

    /**
     * Récupère les codes promo actifs
     */
    public function findActivePromoCodes(): array
    {
        $now = new \DateTime();
        
        return $this->createQueryBuilder('p')
            ->where('p.isActive = true')
            ->andWhere('p.validFrom <= :now')
            ->andWhere('p.validUntil >= :now')
            ->andWhere('p.maxUsageGlobal IS NULL OR p.currentUsage < p.maxUsageGlobal')
            ->setParameter('now', $now)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques d'utilisation d'un code promo
     */
    public function getUsageStats(PromoCode $promoCode): array
    {
        $em = $this->getEntityManager();
        
        $totalAttempts = $em->createQuery('
            SELECT COUNT(u.id) 
            FROM App\Entity\PromoCodeUsage u 
            WHERE u.promoCode = :promoCode
        ')
        ->setParameter('promoCode', $promoCode)
        ->getSingleScalarResult();

        $validatedUsages = $em->createQuery('
            SELECT COUNT(u.id) 
            FROM App\Entity\PromoCodeUsage u 
            WHERE u.promoCode = :promoCode AND u.status = :status
        ')
        ->setParameter('promoCode', $promoCode)
        ->setParameter('status', 'validated')
        ->getSingleScalarResult();

        $totalDiscount = $em->createQuery('
            SELECT SUM(u.discountAmount) 
            FROM App\Entity\PromoCodeUsage u 
            WHERE u.promoCode = :promoCode AND u.status = :status
        ')
        ->setParameter('promoCode', $promoCode)
        ->setParameter('status', 'validated')
        ->getSingleScalarResult();

        return [
            'totalAttempts' => (int) $totalAttempts,
            'validatedUsages' => (int) $validatedUsages,
            'totalDiscount' => (float) ($totalDiscount ?? 0),
            'successRate' => $totalAttempts > 0 ? ($validatedUsages / $totalAttempts) * 100 : 0
        ];
    }

    /**
     * Codes promo expirant bientôt
     */
    public function findExpiringSoon(int $days = 7): array
    {
        $now = new \DateTime();
        $futureDate = new \DateTime("+{$days} days");
        
        return $this->createQueryBuilder('p')
            ->where('p.isActive = true')
            ->andWhere('p.validUntil <= :futureDate')
            ->andWhere('p.validUntil >= :now')
            ->setParameter('futureDate', $futureDate)
            ->setParameter('now', $now)
            ->orderBy('p.validUntil', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Top des codes les plus utilisés
     */
    public function findMostUsed(int $limit = 10): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.currentUsage > 0')
            ->orderBy('p.currentUsage', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
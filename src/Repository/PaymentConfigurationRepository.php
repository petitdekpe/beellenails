<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>

namespace App\Repository;

use App\Entity\PaymentConfiguration;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PaymentConfiguration>
 */
class PaymentConfigurationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PaymentConfiguration::class);
    }

    public function findActiveByType(string $type): ?PaymentConfiguration
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.type = :type')
            ->andWhere('p.isActive = :active')
            ->setParameter('type', $type)
            ->setParameter('active', true)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findAllActive(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('p.type', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getRendezvousAdvanceAmount(): float
    {
        $config = $this->findActiveByType('rendezvous_advance');
        return $config ? $config->getAmount() : 5000; // Default fallback
    }

    public function getFormationBaseAmount(): float
    {
        $config = $this->findActiveByType('formation_base');
        return $config ? $config->getAmount() : 0; // Default fallback
    }

    public function getAmountByType(string $paymentType): float
    {
        $config = $this->findActiveByType($paymentType);
        
        return $config ? $config->getAmount() : match($paymentType) {
            'rendezvous_advance' => 5000,
            'formation_full' => 0,
            'formation_advance' => 0,
            'custom' => 0,
            default => 0
        };
    }

    public function getConfigurationByType(string $paymentType): ?PaymentConfiguration
    {
        return $this->findActiveByType($paymentType);
    }
}
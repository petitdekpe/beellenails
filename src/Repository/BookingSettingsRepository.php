<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>

namespace App\Repository;

use App\Entity\BookingSettings;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BookingSettings>
 */
class BookingSettingsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BookingSettings::class);
    }

    /**
     * Retourne la ligne de réglages unique, en la créant avec les valeurs par défaut si absente.
     */
    public function getCurrent(): BookingSettings
    {
        $settings = $this->createQueryBuilder('s')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$settings) {
            $settings = new BookingSettings();
            $em = $this->getEntityManager();
            $em->persist($settings);
            $em->flush();
        }

        return $settings;
    }

    public function getHoldDurationMinutes(): int
    {
        return $this->getCurrent()->getHoldDurationMinutes();
    }
}

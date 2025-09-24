<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>

namespace App\Repository;

use App\Entity\FormationResource;
use App\Entity\Formation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FormationResource>
 */
class FormationResourceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FormationResource::class);
    }

    public function findByFormation(Formation $formation): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.formation = :formation')
            ->setParameter('formation', $formation)
            ->orderBy('r.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findDownloadableByFormation(Formation $formation): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.formation = :formation')
            ->andWhere('r.isDownloadable = :downloadable')
            ->setParameter('formation', $formation)
            ->setParameter('downloadable', true)
            ->orderBy('r.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByType(string $type): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.type = :type')
            ->setParameter('type', $type)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
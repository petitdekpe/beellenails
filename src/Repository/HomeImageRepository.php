<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>

namespace App\Repository;

use App\Entity\HomeImage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<HomeImage>
 *
 * @method HomeImage|null find($id, $lockMode = null, $lockVersion = null)
 * @method HomeImage|null findOneBy(array $criteria, array $orderBy = null)
 * @method HomeImage[]    findAll()
 * @method HomeImage[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class HomeImageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HomeImage::class);
    }

    public function findActiveByType(string $type): array
    {
        return $this->createQueryBuilder('h')
            ->andWhere('h.type = :type')
            ->andWhere('h.isActive = :active')
            ->setParameter('type', $type)
            ->setParameter('active', true)
            ->orderBy('h.position', 'ASC')
            ->addOrderBy('h.createdAt', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    public function findOneActiveByType(string $type): ?HomeImage
    {
        return $this->createQueryBuilder('h')
            ->andWhere('h.type = :type')
            ->andWhere('h.isActive = :active')
            ->setParameter('type', $type)
            ->setParameter('active', true)
            ->orderBy('h.position', 'ASC')
            ->addOrderBy('h.createdAt', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    public function findByTypeOrderedByPosition(string $type): array
    {
        return $this->createQueryBuilder('h')
            ->andWhere('h.type = :type')
            ->setParameter('type', $type)
            ->orderBy('h.position', 'ASC')
            ->addOrderBy('h.createdAt', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }
}
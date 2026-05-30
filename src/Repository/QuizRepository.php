<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>

namespace App\Repository;

use App\Entity\Quiz;
use App\Entity\FormationModule;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Quiz>
 */
class QuizRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Quiz::class);
    }

    public function findActiveByModule(FormationModule $module): ?Quiz
    {
        return $this->createQueryBuilder('q')
            ->andWhere('q.module = :module')
            ->andWhere('q.isActive = true')
            ->setParameter('module', $module)
            ->getQuery()
            ->getOneOrNullResult();
    }
}

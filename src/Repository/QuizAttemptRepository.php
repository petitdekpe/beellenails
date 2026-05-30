<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>

namespace App\Repository;

use App\Entity\QuizAttempt;
use App\Entity\Quiz;
use App\Entity\FormationEnrollment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<QuizAttempt>
 */
class QuizAttemptRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, QuizAttempt::class);
    }

    public function findByEnrollmentAndQuiz(FormationEnrollment $enrollment, Quiz $quiz): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.enrollment = :enrollment')
            ->andWhere('a.quiz = :quiz')
            ->setParameter('enrollment', $enrollment)
            ->setParameter('quiz', $quiz)
            ->orderBy('a.attemptNumber', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countByEnrollmentAndQuiz(FormationEnrollment $enrollment, Quiz $quiz): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.enrollment = :enrollment')
            ->andWhere('a.quiz = :quiz')
            ->setParameter('enrollment', $enrollment)
            ->setParameter('quiz', $quiz)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findBestAttempt(FormationEnrollment $enrollment, Quiz $quiz): ?QuizAttempt
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.enrollment = :enrollment')
            ->andWhere('a.quiz = :quiz')
            ->andWhere('a.completedAt IS NOT NULL')
            ->setParameter('enrollment', $enrollment)
            ->setParameter('quiz', $quiz)
            ->orderBy('a.score', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}

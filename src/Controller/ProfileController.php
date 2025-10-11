<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <jy.ahouanvoedo@gmail.com>

namespace App\Controller;

use App\Repository\FormationEnrollmentRepository;
use App\Repository\RendezvousRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class ProfileController extends AbstractController
{
    #[Route('/profil', name: 'app_profile')]
    public function index(
        RendezvousRepository $rendezvousRepository,
        FormationEnrollmentRepository $enrollmentRepository
    ): Response {
        $user = $this->getUser();

        // Statistiques des rendez-vous
        $totalRendezvous = $rendezvousRepository->count([
            'user' => $user,
            'status' => ['Rendez-vous pris', 'Rendez-vous confirmé']
        ]);

        $upcomingRendezvous = $rendezvousRepository->createQueryBuilder('r')
            ->where('r.user = :user')
            ->andWhere('r.status IN (:statuses)')
            ->andWhere('r.day >= :today')
            ->setParameter('user', $user)
            ->setParameter('statuses', ['Rendez-vous pris', 'Rendez-vous confirmé'])
            ->setParameter('today', new \DateTime('today'))
            ->orderBy('r.day', 'ASC')
            ->addOrderBy('r.creneau', 'ASC')
            ->setMaxResults(3)
            ->getQuery()
            ->getResult();

        // Statistiques des formations
        $activeEnrollments = $enrollmentRepository->findBy([
            'user' => $user,
            'status' => 'active'
        ]);

        $completedEnrollments = $enrollmentRepository->findBy([
            'user' => $user,
            'status' => 'completed'
        ]);

        $totalStudyTime = 0;
        $avgProgress = 0;

        $allEnrollments = array_merge($activeEnrollments, $completedEnrollments);
        if (!empty($allEnrollments)) {
            foreach ($allEnrollments as $enrollment) {
                $totalStudyTime += $enrollment->getTotalTimeSpent();
                $avgProgress += $enrollment->getProgressPercentage();
            }
            $avgProgress = round($avgProgress / count($allEnrollments));
        }

        return $this->render('profile/index.html.twig', [
            'user' => $user,
            'rendezvous_stats' => [
                'total' => $totalRendezvous,
                'upcoming' => $upcomingRendezvous,
            ],
            'formation_stats' => [
                'active' => count($activeEnrollments),
                'completed' => count($completedEnrollments),
                'total_study_time' => $totalStudyTime,
                'avg_progress' => $avgProgress,
            ]
        ]);
    }

    #[Route('/profil/rendezvous', name: 'app_profile_rendezvous')]
    public function rendezvous(RendezvousRepository $rendezvousRepository): Response
    {
        $user = $this->getUser();

        // Get all user appointments, ordered by date DESC
        $appointments = $rendezvousRepository->findBy(
            ['user' => $user],
            ['day' => 'DESC', 'creneau' => 'DESC']
        );

        return $this->render('profile/rendezvous.html.twig', [
            'user' => $user,
            'appointments' => $appointments,
        ]);
    }
}

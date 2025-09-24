<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>

namespace App\Controller;

use App\Repository\FormationRepository;
use App\Repository\FormationEnrollmentRepository;
use App\Repository\PaymentRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/dashboard/analytics')]
#[IsGranted('ROLE_ADMIN')]
class DashboardAnalyticsController extends AbstractController
{
    #[Route('/', name: 'app_dashboard_analytics', methods: ['GET'])]
    public function index(
        FormationRepository $formationRepository,
        FormationEnrollmentRepository $enrollmentRepository,
        PaymentRepository $paymentRepository,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        Request $request
    ): Response {
        $period = $request->query->get('period', '30days');
        
        // Calcul des dates selon la période
        $endDate = new \DateTime();
        $startDate = match($period) {
            '7days' => (clone $endDate)->sub(new \DateInterval('P7D')),
            '30days' => (clone $endDate)->sub(new \DateInterval('P30D')),
            '3months' => (clone $endDate)->sub(new \DateInterval('P3M')),
            '6months' => (clone $endDate)->sub(new \DateInterval('P6M')),
            '1year' => (clone $endDate)->sub(new \DateInterval('P1Y')),
            default => (clone $endDate)->sub(new \DateInterval('P30D'))
        };

        // Statistiques générales
        $totalFormations = $formationRepository->count([]);
        $activeFormations = $formationRepository->count(['isActive' => true]);
        $totalEnrollments = $enrollmentRepository->count([]);
        $activeEnrollments = $enrollmentRepository->count(['status' => 'active']);
        
        // Revenus par période
        $revenueData = $this->calculateRevenue($paymentRepository, $entityManager, $startDate, $endDate);
        
        // Formations les plus populaires
        $popularFormations = $this->getPopularFormations($enrollmentRepository, $startDate, $endDate);
        
        // Taux de complétion par formation
        $completionRates = $this->getCompletionRates($enrollmentRepository);
        
        // Nouvelles inscriptions par jour
        $enrollmentTrend = $this->getEnrollmentTrend($enrollmentRepository, $entityManager, $startDate, $endDate);
        
        // Utilisateurs les plus actifs
        $activeUsers = $this->getActiveUsers($enrollmentRepository, $startDate, $endDate);
        
        return $this->render('dashboard/analytics/index.html.twig', [
            'stats' => [
                'totalFormations' => $totalFormations,
                'activeFormations' => $activeFormations,
                'totalEnrollments' => $totalEnrollments,
                'activeEnrollments' => $activeEnrollments,
                'totalRevenue' => $revenueData['total'],
                'periodRevenue' => $revenueData['period'],
            ],
            'revenueData' => $revenueData,
            'popularFormations' => $popularFormations,
            'completionRates' => $completionRates,
            'enrollmentTrend' => $enrollmentTrend,
            'activeUsers' => $activeUsers,
            'period' => $period,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
    }

    #[Route('/revenue-chart', name: 'app_dashboard_analytics_revenue_chart', methods: ['GET'])]
    public function revenueChart(PaymentRepository $paymentRepository, EntityManagerInterface $entityManager, Request $request): JsonResponse
    {
        $period = $request->query->get('period', '30days');
        
        $endDate = new \DateTime();
        $startDate = match($period) {
            '7days' => (clone $endDate)->sub(new \DateInterval('P7D')),
            '30days' => (clone $endDate)->sub(new \DateInterval('P30D')),
            '3months' => (clone $endDate)->sub(new \DateInterval('P3M')),
            '6months' => (clone $endDate)->sub(new \DateInterval('P6M')),
            '1year' => (clone $endDate)->sub(new \DateInterval('P1Y')),
            default => (clone $endDate)->sub(new \DateInterval('P30D'))
        };

        // Utilisation de SQL natif pour éviter les problèmes de compatibilité DQL
        $connection = $entityManager->getConnection();
        $sql = "SELECT DATE(created_at) as date, SUM(amount) as revenue 
                FROM payment 
                WHERE created_at BETWEEN :start AND :end 
                AND status = :status 
                GROUP BY DATE(created_at) 
                ORDER BY date ASC";
        
        $revenueByDay = $connection->executeQuery($sql, [
            'start' => $startDate->format('Y-m-d H:i:s'),
            'end' => $endDate->format('Y-m-d H:i:s'),
            'status' => 'completed'
        ])->fetchAllAssociative();

        $labels = [];
        $data = [];
        
        foreach ($revenueByDay as $entry) {
            $labels[] = (new \DateTime($entry['date']))->format('d/m');
            $data[] = (float) $entry['revenue'];
        }

        return new JsonResponse([
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Revenus (F CFA)',
                    'data' => $data,
                    'borderColor' => 'rgb(236, 72, 153)',
                    'backgroundColor' => 'rgba(236, 72, 153, 0.1)',
                    'tension' => 0.1
                ]
            ]
        ]);
    }

    #[Route('/enrollments-chart', name: 'app_dashboard_analytics_enrollments_chart', methods: ['GET'])]
    public function enrollmentsChart(FormationEnrollmentRepository $enrollmentRepository, EntityManagerInterface $entityManager, Request $request): JsonResponse
    {
        $period = $request->query->get('period', '30days');
        
        $endDate = new \DateTime();
        $startDate = match($period) {
            '7days' => (clone $endDate)->sub(new \DateInterval('P7D')),
            '30days' => (clone $endDate)->sub(new \DateInterval('P30D')),
            '3months' => (clone $endDate)->sub(new \DateInterval('P3M')),
            '6months' => (clone $endDate)->sub(new \DateInterval('P6M')),
            '1year' => (clone $endDate)->sub(new \DateInterval('P1Y')),
            default => (clone $endDate)->sub(new \DateInterval('P30D'))
        };

        // Utilisation de SQL natif pour éviter les problèmes de compatibilité DQL
        $connection = $entityManager->getConnection();
        $sql = "SELECT DATE(enrolled_at) as date, COUNT(id) as count 
                FROM formation_enrollment 
                WHERE enrolled_at BETWEEN :start AND :end 
                GROUP BY DATE(enrolled_at) 
                ORDER BY date ASC";
        
        $enrollmentsByDay = $connection->executeQuery($sql, [
            'start' => $startDate->format('Y-m-d H:i:s'),
            'end' => $endDate->format('Y-m-d H:i:s')
        ])->fetchAllAssociative();

        $labels = [];
        $data = [];
        
        foreach ($enrollmentsByDay as $entry) {
            $labels[] = (new \DateTime($entry['date']))->format('d/m');
            $data[] = (int) $entry['count'];
        }

        return new JsonResponse([
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Nouvelles inscriptions',
                    'data' => $data,
                    'borderColor' => 'rgb(139, 92, 246)',
                    'backgroundColor' => 'rgba(139, 92, 246, 0.1)',
                    'tension' => 0.1
                ]
            ]
        ]);
    }

    private function calculateRevenue(PaymentRepository $paymentRepository, EntityManagerInterface $entityManager, \DateTime $startDate, \DateTime $endDate): array
    {
        // Revenus total (toute période)
        $totalRevenue = $paymentRepository->createQueryBuilder('p')
            ->select('SUM(p.amount)')
            ->where('p.status = :status')
            ->setParameter('status', 'completed')
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        // Revenus sur la période sélectionnée
        $periodRevenue = $paymentRepository->createQueryBuilder('p')
            ->select('SUM(p.amount)')
            ->where('p.createdAt BETWEEN :start AND :end')
            ->andWhere('p.status = :status')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->setParameter('status', 'completed')
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        return [
            'total' => (float) $totalRevenue,
            'period' => (float) $periodRevenue,
        ];
    }

    private function getPopularFormations(FormationEnrollmentRepository $enrollmentRepository, \DateTime $startDate, \DateTime $endDate): array
    {
        return $enrollmentRepository->createQueryBuilder('e')
            ->select('f.Nom as name, COUNT(e.id) as enrollments')
            ->join('e.formation', 'f')
            ->where('e.enrolledAt BETWEEN :start AND :end')
            ->groupBy('f.id')
            ->orderBy('enrollments', 'DESC')
            ->setMaxResults(10)
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->getQuery()
            ->getResult();
    }

    private function getCompletionRates(FormationEnrollmentRepository $enrollmentRepository): array
    {
        // Récupérer les stats de base pour chaque formation
        $baseStats = $enrollmentRepository->createQueryBuilder('e')
            ->select('
                f.id as formationId,
                f.Nom as name,
                COUNT(e.id) as totalEnrollments,
                AVG(e.progressPercentage) as avgProgress
            ')
            ->join('e.formation', 'f')
            ->groupBy('f.id')
            ->orderBy('avgProgress', 'DESC')
            ->getQuery()
            ->getResult();

        // Ajouter le nombre d'inscriptions complétées pour chaque formation
        foreach ($baseStats as &$stat) {
            $completedCount = $enrollmentRepository->createQueryBuilder('e2')
                ->select('COUNT(e2.id)')
                ->where('e2.formation = :formationId')
                ->andWhere('e2.status = :status')
                ->setParameter('formationId', $stat['formationId'])
                ->setParameter('status', 'completed')
                ->getQuery()
                ->getSingleScalarResult();
            
            $stat['completedEnrollments'] = (int) $completedCount;
            unset($stat['formationId']); // Nettoyer l'ID interne
        }

        return $baseStats;
    }

    private function getEnrollmentTrend(FormationEnrollmentRepository $enrollmentRepository, EntityManagerInterface $entityManager, \DateTime $startDate, \DateTime $endDate): array
    {
        // Utilisation de SQL natif pour éviter les problèmes de compatibilité DQL
        $connection = $entityManager->getConnection();
        $sql = "SELECT DATE(enrolled_at) as date, COUNT(id) as count 
                FROM formation_enrollment 
                WHERE enrolled_at BETWEEN :start AND :end 
                GROUP BY DATE(enrolled_at) 
                ORDER BY date ASC";
        
        return $connection->executeQuery($sql, [
            'start' => $startDate->format('Y-m-d H:i:s'),
            'end' => $endDate->format('Y-m-d H:i:s')
        ])->fetchAllAssociative();
    }

    private function getActiveUsers(FormationEnrollmentRepository $enrollmentRepository, \DateTime $startDate, \DateTime $endDate): array
    {
        return $enrollmentRepository->createQueryBuilder('e')
            ->select('
                u.Prenom as prenom,
                u.Nom as nom,
                u.email,
                COUNT(e.id) as enrollments,
                AVG(e.progressPercentage) as avgProgress
            ')
            ->join('e.user', 'u')
            ->where('e.enrolledAt BETWEEN :start AND :end')
            ->groupBy('u.id')
            ->orderBy('avgProgress', 'DESC')
            ->setMaxResults(20)
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->getQuery()
            ->getResult();
    }
}
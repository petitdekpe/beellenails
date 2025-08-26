<?php

namespace App\Controller;

use App\Form\DashboardPeriodType;
use App\Repository\RendezvousRepository;
use App\Repository\UserRepository;
use App\Repository\CreneauRepository;
use App\Repository\PaymentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class DashboardOverviewController extends AbstractController
{
    #[Route('/dashboard/overview', name: 'app_dashboard_overview')]
    #[IsGranted("ROLE_ADMIN")]
    public function index(
        Request $request,
        RendezvousRepository $rendezvousRepository,
        UserRepository $userRepository,
        CreneauRepository $creneauRepository,
        PaymentRepository $paymentRepository
    ): Response {
        // Créer le formulaire de période
        $form = $this->createForm(DashboardPeriodType::class);
        $form->handleRequest($request);

        // Déterminer les dates de début et fin selon la période sélectionnée
        [$startDate, $endDate] = $this->calculatePeriodDates($form->getData());

        // 1. Liste des rendez-vous de la période (seulement pris et confirmés)
        $appointments = $rendezvousRepository->createQueryBuilder('r')
            ->join('r.user', 'u')
            ->join('r.prestation', 'p')
            ->join('r.creneau', 'c')
            ->where('r.day BETWEEN :start AND :end')
            ->andWhere('r.status IN (:confirmedStatuses)')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->setParameter('confirmedStatuses', ['Rendez-vous pris', 'Rendez-vous confirmé'])
            ->orderBy('r.day', 'DESC')
            ->addOrderBy('c.startTime', 'ASC')
            ->getQuery()
            ->getResult();

        // 2. Liste des rendez-vous créés cette période
        $appointmentsCreated = $rendezvousRepository->createQueryBuilder('r')
            ->join('r.user', 'u')
            ->join('r.prestation', 'p')
            ->join('r.creneau', 'c')
            ->where('r.created_at BETWEEN :start AND :end')
            ->setParameter('start', $startDate->format('Y-m-d 00:00:00'))
            ->setParameter('end', $endDate->format('Y-m-d 23:59:59'))
            ->orderBy('r.created_at', 'DESC')
            ->getQuery()
            ->getResult();

        // 3. Liste des rendez-vous annulés de la période
        $cancelledAppointments = $rendezvousRepository->createQueryBuilder('r')
            ->join('r.user', 'u')
            ->join('r.prestation', 'p')
            ->join('r.creneau', 'c')
            ->where('r.day BETWEEN :start AND :end')
            ->andWhere('r.status = :cancelled')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->setParameter('cancelled', 'Annulé')
            ->orderBy('r.day', 'DESC')
            ->addOrderBy('c.startTime', 'ASC')
            ->getQuery()
            ->getResult();

        // 4. Liste des rendez-vous reportés dans la période
        // Utilise une méthode dédiée du repository pour identifier les vrais reports
        $rescheduledAppointments = $rendezvousRepository->findRescheduledAppointments($startDate, $endDate);

        // 5. Liste des clients créés dans la période (par date de création de compte)
        $newClients = $userRepository->createQueryBuilder('u')
            ->where('u.created_at BETWEEN :start AND :end')
            ->setParameter('start', $startDate->format('Y-m-d 00:00:00'))
            ->setParameter('end', $endDate->format('Y-m-d 23:59:59'))
            ->orderBy('u.created_at', 'DESC')
            ->getQuery()
            ->getResult();

        // 6. Prochains créneaux libres de la période
        $availableSlots = $this->getAvailableSlots($startDate, $endDate, $rendezvousRepository, $creneauRepository);

        // 7. Recette de la période
        $payments = $paymentRepository->createQueryBuilder('p')
            ->leftJoin('p.rendezvou', 'r')
            ->where('p.createdAt BETWEEN :start AND :end')
            ->andWhere('p.status = :successful')
            ->setParameter('start', $startDate->format('Y-m-d 00:00:00'))
            ->setParameter('end', $endDate->format('Y-m-d 23:59:59'))
            ->setParameter('successful', 'successful')
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        $totalRevenue = 0;
        foreach ($payments as $payment) {
            if ($payment->getRendezvous() && $payment->getRendezvous()->getTotalCost()) {
                $totalRevenue += (float) $payment->getRendezvous()->getTotalCost();
            }
        }

        return $this->render('dashboard/overview.html.twig', [
            'form' => $form->createView(),
            'period_start' => $startDate,
            'period_end' => $endDate,
            'period_label' => $this->getPeriodLabel($form->getData()),
            
            'appointments' => $appointments,
            'appointments_created' => $appointmentsCreated,
            'cancelled_appointments' => $cancelledAppointments,
            'rescheduled_appointments' => $rescheduledAppointments,
            'new_clients' => $newClients,
            'available_slots' => $availableSlots,
            'payments' => $payments,
            'total_revenue' => $totalRevenue,
        ]);
    }

    private function calculatePeriodDates(?array $data): array
    {
        $periodType = $data['period_type'] ?? 'today';
        $startDate = new \DateTime();
        $endDate = new \DateTime();

        switch ($periodType) {
            case 'today':
                // Même jour pour début et fin
                break;
                
            case 'tomorrow':
                $startDate = new \DateTime('tomorrow');
                $endDate = new \DateTime('tomorrow');
                break;
                
            case 'this_week':
                $startDate = new \DateTime('monday this week');
                $endDate = new \DateTime('sunday this week');
                break;
                
            case 'this_month':
                $startDate = new \DateTime('first day of this month');
                $endDate = new \DateTime('last day of this month');
                break;
                
            case 'this_year':
                $startDate = new \DateTime('first day of january this year');
                $endDate = new \DateTime('last day of december this year');
                break;
        }

        return [$startDate, $endDate];
    }

    private function getPeriodLabel(?array $data): string
    {
        $periodType = $data['period_type'] ?? 'today';
        
        return match($periodType) {
            'today' => 'Aujourd\'hui (' . (new \DateTime())->format('d/m/Y') . ')',
            'tomorrow' => 'Demain (' . (new \DateTime('tomorrow'))->format('d/m/Y') . ')',
            'this_week' => 'Cette semaine',
            'this_month' => 'Ce mois (' . (new \DateTime())->format('F Y') . ')',
            'this_year' => 'Cette année (' . (new \DateTime())->format('Y') . ')',
            default => 'Aujourd\'hui'
        };
    }

    private function getAvailableSlots(\DateTime $startDate, \DateTime $endDate, $rendezvousRepository, $creneauRepository): array
    {
        $availableSlots = [];
        $allCreneaux = $creneauRepository->findAll();
        
        $currentDate = clone $startDate;
        while ($currentDate <= $endDate) {
            // Éviter les dimanches et lundis
            if (!in_array($currentDate->format('w'), [0, 1])) {
                $occupiedSlots = $rendezvousRepository->createQueryBuilder('r')
                    ->select('c.id')
                    ->join('r.creneau', 'c')
                    ->where('r.day = :date')
                    ->andWhere('r.status IN (:occupiedStatuses)')
                    ->setParameter('date', $currentDate->format('Y-m-d'))
                    ->setParameter('occupiedStatuses', ['Rendez-vous confirmé', 'Rendez-vous pris', 'Congé'])
                    ->getQuery()
                    ->getScalarResult();
                
                $occupiedIds = array_column($occupiedSlots, 'id');
                
                foreach ($allCreneaux as $creneau) {
                    if (!in_array($creneau->getId(), $occupiedIds)) {
                        $availableSlots[] = [
                            'date' => clone $currentDate,
                            'creneau' => $creneau
                        ];
                    }
                }
            }
            $currentDate->modify('+1 day');
        }

        return array_slice($availableSlots, 0, 20); // Limiter à 20
    }
}
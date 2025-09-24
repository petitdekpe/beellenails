<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>

namespace App\Controller;

use App\Entity\Formation;
use App\Entity\FormationEnrollment;
use App\Entity\FormationModule;
use App\Entity\FormationResource;
use App\Form\FormationType;
use App\Form\FormationModuleType;
use App\Form\FormationResourceType;
use App\Repository\FormationRepository;
use App\Repository\FormationEnrollmentRepository;
use App\Repository\PaymentRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/dashboard/formations')]
#[IsGranted('ROLE_ADMIN')]
class DashboardFormationController extends AbstractController
{
    #[Route('/', name: 'app_dashboard_formations_index', methods: ['GET'])]
    public function index(
        Request $request, 
        FormationRepository $formationRepository,
        FormationEnrollmentRepository $enrollmentRepository,
        PaginatorInterface $paginator
    ): Response {
        $search = $request->query->get('search');
        $status = $request->query->get('status');
        $isFree = $request->query->get('is_free');
        
        $queryBuilder = $formationRepository->createQueryBuilder('f')
            ->orderBy('f.createdAt', 'DESC');
        
        // Recherche par nom
        if ($search) {
            $queryBuilder->andWhere('f.Nom LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }
        
        // Filtre par statut
        if ($status && $status !== 'all') {
            $queryBuilder->andWhere('f.isActive = :status')
                ->setParameter('status', $status === 'active');
        }
        
        // Filtre payant/gratuit
        if ($isFree && $isFree !== 'all') {
            $queryBuilder->andWhere('f.isFree = :isFree')
                ->setParameter('isFree', $isFree === 'true');
        }
        
        $formations = $paginator->paginate(
            $queryBuilder,
            $request->query->getInt('page', 1),
            10
        );
        
        // Ajouter le nombre d'inscriptions pour chaque formation
        foreach ($formations as $formation) {
            $enrollmentCount = $enrollmentRepository->count(['formation' => $formation]);
            $formation->enrollmentCount = $enrollmentCount;
        }
        
        // Statistiques générales
        $stats = [
            'total' => $formationRepository->count([]),
            'active' => $formationRepository->count(['isActive' => true]),
            'paid' => $formationRepository->count(['isFree' => false]),
            'free' => $formationRepository->count(['isFree' => true]),
            'totalEnrollments' => $enrollmentRepository->count([]),
            'activeEnrollments' => $enrollmentRepository->count(['status' => 'active']),
        ];
        
        return $this->render('dashboard/formations/index.html.twig', [
            'formations' => $formations,
            'stats' => $stats,
            'search' => $search,
            'status' => $status,
            'is_free' => $isFree,
        ]);
    }

    #[Route('/new', name: 'app_dashboard_formation_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $formation = new Formation();
        $form = $this->createForm(FormationType::class, $formation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $formation->setCreatedAt(new \DateTime());
            $formation->setUpdatedAt(new \DateTimeImmutable());
            
            $entityManager->persist($formation);
            $entityManager->flush();

            $this->addFlash('success', 'Formation créée avec succès.');
            return $this->redirectToRoute('app_dashboard_formations_index');
        }

        return $this->render('dashboard/formations/new.html.twig', [
            'formation' => $formation,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_dashboard_formation_show', methods: ['GET'])]
    public function show(
        Formation $formation, 
        FormationEnrollmentRepository $enrollmentRepository,
        PaymentRepository $paymentRepository
    ): Response {
        // Statistiques de la formation
        $enrollments = $enrollmentRepository->findBy(['formation' => $formation]);
        $stats = [
            'totalEnrollments' => count($enrollments),
            'activeEnrollments' => count(array_filter($enrollments, fn($e) => $e->getStatus() === 'active')),
            'completedEnrollments' => count(array_filter($enrollments, fn($e) => $e->getStatus() === 'completed')),
            'expiredEnrollments' => count(array_filter($enrollments, fn($e) => $e->getStatus() === 'expired')),
        ];
        
        // Calcul des revenus
        $revenue = 0;
        if (!$formation->isFree()) {
            // Pour l'instant, pas de calcul de revenus car il n'y a pas de lien direct
            // entre les paiements et les formations via l'entité Prestation
            // TODO: Implémenter le calcul des revenus quand la relation sera établie
            $revenue = 0;
        }
        
        $stats['revenue'] = $revenue;
        
        // Taux de complétion moyen
        if ($stats['totalEnrollments'] > 0) {
            $totalProgress = array_sum(array_map(fn($e) => $e->getProgressPercentage(), $enrollments));
            $stats['avgCompletion'] = round($totalProgress / $stats['totalEnrollments'], 1);
        } else {
            $stats['avgCompletion'] = 0;
        }

        return $this->render('dashboard/formations/show.html.twig', [
            'formation' => $formation,
            'enrollments' => $enrollments,
            'stats' => $stats,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_dashboard_formation_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Formation $formation, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(FormationType::class, $formation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $formation->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->flush();

            $this->addFlash('success', 'Formation modifiée avec succès.');
            return $this->redirectToRoute('app_dashboard_formation_show', ['id' => $formation->getId()]);
        }

        return $this->render('dashboard/formations/edit.html.twig', [
            'formation' => $formation,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/toggle-status', name: 'app_dashboard_formation_toggle_status', methods: ['POST'])]
    public function toggleStatus(Formation $formation, EntityManagerInterface $entityManager): JsonResponse
    {
        $formation->setIsActive(!$formation->isActive());
        $formation->setUpdatedAt(new \DateTimeImmutable());
        $entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'status' => $formation->isActive(),
            'message' => $formation->isActive() ? 'Formation activée' : 'Formation désactivée'
        ]);
    }

    #[Route('/{id}/delete', name: 'app_dashboard_formation_delete', methods: ['POST'])]
    public function delete(Request $request, Formation $formation, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $formation->getId(), $request->request->get('_token'))) {
            // Vérifier s'il y a des inscriptions actives
            $activeEnrollments = $entityManager->getRepository(FormationEnrollment::class)
                ->count(['formation' => $formation, 'status' => 'active']);
            
            if ($activeEnrollments > 0) {
                $this->addFlash('error', 'Impossible de supprimer cette formation car elle a des inscriptions actives.');
                return $this->redirectToRoute('app_dashboard_formation_show', ['id' => $formation->getId()]);
            }
            
            $entityManager->remove($formation);
            $entityManager->flush();
            $this->addFlash('success', 'Formation supprimée avec succès.');
        }

        return $this->redirectToRoute('app_dashboard_formations_index');
    }

    #[Route('/{id}/modules', name: 'app_dashboard_formation_modules', methods: ['GET'])]
    public function modules(Formation $formation): Response
    {
        return $this->render('dashboard/formations/modules.html.twig', [
            'formation' => $formation,
            'modules' => $formation->getModules(),
        ]);
    }

    #[Route('/{id}/modules/new', name: 'app_dashboard_formation_module_new', methods: ['GET', 'POST'])]
    public function newModule(Request $request, Formation $formation, EntityManagerInterface $entityManager): Response
    {
        $module = new FormationModule();
        $module->setFormation($formation);
        
        $form = $this->createForm(FormationModuleType::class, $module);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Auto-assign position
            $maxPosition = $entityManager->getRepository(FormationModule::class)
                ->createQueryBuilder('m')
                ->select('MAX(m.position)')
                ->where('m.formation = :formation')
                ->setParameter('formation', $formation)
                ->getQuery()
                ->getSingleScalarResult();
            
            $module->setPosition(($maxPosition ?? 0) + 1);
            $module->setCreatedAt(new \DateTime());
            
            $entityManager->persist($module);
            $entityManager->flush();

            $this->addFlash('success', 'Module ajouté avec succès.');
            return $this->redirectToRoute('app_dashboard_formation_modules', ['id' => $formation->getId()]);
        }

        return $this->render('dashboard/formations/module_new.html.twig', [
            'formation' => $formation,
            'module' => $module,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/enrollments', name: 'app_dashboard_formation_enrollments', methods: ['GET'])]
    public function enrollments(
        Formation $formation, 
        FormationEnrollmentRepository $enrollmentRepository,
        Request $request,
        PaginatorInterface $paginator
    ): Response {
        $status = $request->query->get('status');
        $search = $request->query->get('search');
        
        $queryBuilder = $enrollmentRepository->createQueryBuilder('e')
            ->join('e.user', 'u')
            ->where('e.formation = :formation')
            ->setParameter('formation', $formation)
            ->orderBy('e.enrolledAt', 'DESC');
        
        if ($status && $status !== 'all') {
            $queryBuilder->andWhere('e.status = :status')
                ->setParameter('status', $status);
        }
        
        if ($search) {
            $queryBuilder->andWhere('u.Nom LIKE :search OR u.Prenom LIKE :search OR u.email LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }
        
        $enrollments = $paginator->paginate(
            $queryBuilder,
            $request->query->getInt('page', 1),
            15
        );

        return $this->render('dashboard/formations/enrollments.html.twig', [
            'formation' => $formation,
            'enrollments' => $enrollments,
            'status' => $status,
            'search' => $search,
        ]);
    }

    #[Route('/enrollment/{id}/toggle-status', name: 'app_dashboard_enrollment_toggle_status', methods: ['POST'])]
    public function toggleEnrollmentStatus(
        FormationEnrollment $enrollment, 
        Request $request,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $newStatus = $request->request->get('status');
        $allowedStatuses = ['active', 'expired', 'completed'];
        
        if (!in_array($newStatus, $allowedStatuses)) {
            return new JsonResponse(['success' => false, 'message' => 'Statut invalide']);
        }
        
        $enrollment->setStatus($newStatus);
        if ($newStatus === 'completed' && !$enrollment->getCompletedAt()) {
            $enrollment->setCompletedAt(new \DateTime());
        }
        
        $entityManager->flush();
        
        return new JsonResponse([
            'success' => true,
            'status' => $newStatus,
            'message' => 'Statut mis à jour avec succès'
        ]);
    }

    #[Route('/enrollment/{id}/extend', name: 'app_dashboard_enrollment_extend', methods: ['POST'])]
    public function extendEnrollment(
        FormationEnrollment $enrollment,
        Request $request,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $days = (int) $request->request->get('days', 30);
        
        if ($enrollment->getExpiresAt()) {
            $newExpiration = clone $enrollment->getExpiresAt();
            $newExpiration->add(new \DateInterval('P' . $days . 'D'));
        } else {
            $newExpiration = (new \DateTime())->add(new \DateInterval('P' . $days . 'D'));
        }
        
        $enrollment->setExpiresAt($newExpiration);
        $enrollment->setStatus('active');
        $entityManager->flush();
        
        return new JsonResponse([
            'success' => true,
            'newExpiration' => $newExpiration->format('d/m/Y'),
            'message' => "Accès prolongé de {$days} jours"
        ]);
    }
}
<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>

namespace App\Controller;

use App\Entity\Formation;
use App\Entity\FormationModule;
use App\Entity\FormationEnrollment;
use App\Entity\ModuleProgress;
use App\Repository\FormationEnrollmentRepository;
use App\Repository\ModuleProgressRepository;
use App\Service\CertificateService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/mon-espace')]
#[IsGranted('ROLE_USER')]
class UserLearningController extends AbstractController
{
    public function __construct(
        private FormationEnrollmentRepository $enrollmentRepository,
        private ModuleProgressRepository $moduleProgressRepository,
        private EntityManagerInterface $entityManager
    ) {}

    #[Route('/', name: 'app_user_learning_dashboard')]
    public function dashboard(): Response
    {
        $user = $this->getUser();
        
        // Get user enrollments
        $activeEnrollments = $this->enrollmentRepository->findActiveByUser($user);
        $completedEnrollments = $this->enrollmentRepository->findCompletedByUser($user);
        $recentEnrollments = $this->enrollmentRepository->findRecentlyAccessedByUser($user, 3);
        
        // Get user stats
        $stats = $this->enrollmentRepository->getUserStats($user);
        $totalTimeSpent = $this->moduleProgressRepository->getTotalTimeSpentByUser($user);
        $completedModules = $this->moduleProgressRepository->getCompletedModulesCountByUser($user);

        return $this->render('user_learning/dashboard.html.twig', [
            'activeEnrollments' => $activeEnrollments,
            'completedEnrollments' => $completedEnrollments,
            'recentEnrollments' => $recentEnrollments,
            'stats' => $stats,
            'totalTimeSpent' => $totalTimeSpent,
            'completedModules' => $completedModules,
        ]);
    }

    #[Route('/formations', name: 'app_user_learning_formations')]
    public function formations(): Response
    {
        $user = $this->getUser();
        $enrollments = $this->enrollmentRepository->findByUser($user);

        return $this->render('user_learning/formations.html.twig', [
            'enrollments' => $enrollments,
        ]);
    }

    #[Route('/formation/{id}', name: 'app_user_learning_formation_detail')]
    public function formationDetail(FormationEnrollment $enrollment): Response
    {
        $this->denyAccessUnlessGranted('view', $enrollment);
        
        // Update last accessed
        $enrollment->setLastAccessedAt(new \DateTime());
        $this->entityManager->flush();
        
        // Get module progresses
        $moduleProgresses = $this->moduleProgressRepository->findByEnrollment($enrollment);
        $progressStats = $this->moduleProgressRepository->getEnrollmentProgress($enrollment);
        $nextModule = $this->moduleProgressRepository->findNextModule($enrollment);

        return $this->render('user_learning/formation_detail.html.twig', [
            'enrollment' => $enrollment,
            'moduleProgresses' => $moduleProgresses,
            'progressStats' => $progressStats,
            'nextModule' => $nextModule,
        ]);
    }

    #[Route('/module/{enrollmentId}/{moduleId}', name: 'app_user_learning_module')]
    public function moduleView(int $enrollmentId, int $moduleId): Response
    {
        $enrollment = $this->enrollmentRepository->find($enrollmentId);
        $module = $this->entityManager->getRepository(FormationModule::class)->find($moduleId);
        
        $this->denyAccessUnlessGranted('view', $enrollment);
        
        if (!$enrollment || !$module || $module->getFormation() !== $enrollment->getFormation()) {
            throw $this->createNotFoundException();
        }

        // Get or create module progress
        $moduleProgress = $this->moduleProgressRepository->findByEnrollmentAndModule($enrollment, $module);
        if (!$moduleProgress) {
            $moduleProgress = new ModuleProgress();
            $moduleProgress->setEnrollment($enrollment);
            $moduleProgress->setModule($module);
            $this->entityManager->persist($moduleProgress);
        }

        // Mark as started if not already
        if (!$moduleProgress->isStarted()) {
            $moduleProgress->setStarted(true);
        }
        
        $moduleProgress->setLastAccessedAt(new \DateTime());
        $enrollment->setLastAccessedAt(new \DateTime());
        $this->entityManager->flush();

        // Get all module progresses for navigation
        $allProgresses = $this->moduleProgressRepository->findByEnrollment($enrollment);

        return $this->render('user_learning/module_view.html.twig', [
            'enrollment' => $enrollment,
            'module' => $module,
            'moduleProgress' => $moduleProgress,
            'allProgresses' => $allProgresses,
        ]);
    }

    #[Route('/api/module-progress/{moduleProgressId}', name: 'app_api_update_module_progress', methods: ['POST'])]
    public function updateModuleProgress(int $moduleProgressId, Request $request): JsonResponse
    {
        $moduleProgress = $this->moduleProgressRepository->find($moduleProgressId);
        
        if (!$moduleProgress) {
            return new JsonResponse(['error' => 'Module progress not found'], 404);
        }
        
        $this->denyAccessUnlessGranted('view', $moduleProgress->getEnrollment());

        $data = json_decode($request->getContent(), true);
        
        // Update video position
        if (isset($data['videoPosition'])) {
            $moduleProgress->setVideoPosition((int) $data['videoPosition']);
        }
        
        // Add time spent
        if (isset($data['timeSpent'])) {
            $moduleProgress->addTimeSpent((int) $data['timeSpent']);
        }
        
        // Mark as completed if requested
        if (isset($data['completed']) && $data['completed']) {
            $moduleProgress->setCompleted(true);
            
            // Update enrollment progress
            $moduleProgress->getEnrollment()->updateProgress();
        }

        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'progress' => $moduleProgress->getCompletionPercentage(),
            'enrollmentProgress' => $moduleProgress->getEnrollment()->getProgressPercentage()
        ]);
    }

    #[Route('/api/enroll/{formationId}', name: 'app_api_enroll_formation', methods: ['POST'])]
    public function enrollInFormation(int $formationId): JsonResponse
    {
        $user = $this->getUser();
        $formation = $this->entityManager->getRepository(Formation::class)->find($formationId);
        
        if (!$formation || !$formation->isAvailable()) {
            return new JsonResponse(['error' => 'Formation not available'], 400);
        }

        // Check if already enrolled
        $existingEnrollment = $this->enrollmentRepository->findUserEnrollment($user, $formation);
        if ($existingEnrollment) {
            return new JsonResponse(['error' => 'Already enrolled'], 400);
        }

        // Create enrollment
        $enrollment = new FormationEnrollment();
        $enrollment->setUser($user);
        $enrollment->setFormation($formation);
        
        $this->entityManager->persist($enrollment);
        
        // Create module progresses for all modules
        foreach ($formation->getModules() as $module) {
            if ($module->isActive()) {
                $moduleProgress = new ModuleProgress();
                $moduleProgress->setEnrollment($enrollment);
                $moduleProgress->setModule($module);
                $this->entityManager->persist($moduleProgress);
            }
        }
        
        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'enrollmentId' => $enrollment->getId(),
            'message' => 'Inscription réussie !'
        ]);
    }

    #[Route('/certificat/{enrollmentId}', name: 'app_user_learning_certificate')]
    public function downloadCertificate(int $enrollmentId, CertificateService $certificateService): Response
    {
        $enrollment = $this->enrollmentRepository->find($enrollmentId);
        
        $this->denyAccessUnlessGranted('view', $enrollment);
        
        if (!$enrollment || $enrollment->getStatus() !== 'completed') {
            throw $this->createNotFoundException('Certificate not available');
        }

        // Generate certificate PDF
        $certificatePdf = $certificateService->generateCertificate($enrollment);
        
        // Mark certificate as generated
        if (!$enrollment->isCertificateGenerated()) {
            $enrollment->setCertificateGenerated(true);
            $this->entityManager->flush();
        }

        return new Response(
            $certificatePdf,
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="certificat-' . $enrollment->getFormation()->getNom() . '.pdf"'
            ]
        );
    }

    // #[Route('/profil', name: 'app_user_learning_profile')]
    // public function profile(): Response
    // {
    //     $user = $this->getUser();
    //     $stats = $this->enrollmentRepository->getUserStats($user);
    //     $totalTimeSpent = $this->moduleProgressRepository->getTotalTimeSpentByUser($user);
    //     $completedModules = $this->moduleProgressRepository->getCompletedModulesCountByUser($user);
    //
    //     // Get recent achievements
    //     $recentCompletions = $this->enrollmentRepository->findCompletedByUser($user);
    //     $recentCompletions = array_slice($recentCompletions, 0, 5);
    //
    //     return $this->render('user_learning/profile.html.twig', [
    //         'stats' => $stats,
    //         'totalTimeSpent' => $totalTimeSpent,
    //         'completedModules' => $completedModules,
    //         'recentCompletions' => $recentCompletions,
    //     ]);
    // }

    #[Route('/user-learning/api/complete/{enrollmentId}/{moduleId}', name: 'app_user_learning_api_complete', methods: ['POST'])]
    public function apiCompleteModule(int $enrollmentId, int $moduleId): JsonResponse
    {
        try {
            $enrollment = $this->entityManager->getRepository(FormationEnrollment::class)->find($enrollmentId);
            if (!$enrollment) {
                return $this->json(['success' => false, 'error' => 'Inscription introuvable'], 404);
            }

            $this->denyAccessUnlessGranted('view', $enrollment);

            $module = $this->entityManager->getRepository(FormationModule::class)->find($moduleId);
            if (!$module) {
                return $this->json(['success' => false, 'error' => 'Module introuvable'], 404);
            }

            $moduleProgress = $this->moduleProgressRepository->findOneBy([
                'enrollment' => $enrollment,
                'module' => $module
            ]);

            if (!$moduleProgress) {
                return $this->json(['success' => false, 'error' => 'Progrès du module introuvable'], 404);
            }

            // Marquer le module comme terminé
            $moduleProgress->setCompleted(true);
            $moduleProgress->setCompletedAt(new \DateTime());

            // Mettre à jour le progrès de l'inscription
            $enrollment->updateProgress();

            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Module marqué comme terminé',
                'progressPercentage' => $enrollment->getProgressPercentage()
            ]);

        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    #[Route('/user-learning/api/progress/{enrollmentId}/{moduleId}', name: 'app_user_learning_api_progress', methods: ['POST'])]
    public function apiSaveProgress(int $enrollmentId, int $moduleId, Request $request): JsonResponse
    {
        try {
            $enrollment = $this->entityManager->getRepository(FormationEnrollment::class)->find($enrollmentId);
            if (!$enrollment) {
                return $this->json(['success' => false, 'error' => 'Inscription introuvable'], 404);
            }

            $this->denyAccessUnlessGranted('view', $enrollment);

            $module = $this->entityManager->getRepository(FormationModule::class)->find($moduleId);
            if (!$module) {
                return $this->json(['success' => false, 'error' => 'Module introuvable'], 404);
            }

            $moduleProgress = $this->moduleProgressRepository->findOneBy([
                'enrollment' => $enrollment,
                'module' => $module
            ]);

            if (!$moduleProgress) {
                return $this->json(['success' => false, 'error' => 'Progrès du module introuvable'], 404);
            }

            $data = json_decode($request->getContent(), true);

            // Sauvegarder la position vidéo et le temps passé
            if (isset($data['videoPosition'])) {
                $moduleProgress->setVideoPosition((int) $data['videoPosition']);
            }

            if (isset($data['timeSpent'])) {
                $moduleProgress->setTimeSpent((int) $data['timeSpent']);
            }

            // Marquer comme commencé si ce n'est pas déjà fait
            if (!$moduleProgress->isStarted()) {
                $moduleProgress->setStarted(true);
                $moduleProgress->setStartedAt(new \DateTime());
            }

            $moduleProgress->setLastAccessedAt(new \DateTime());

            $this->entityManager->flush();

            return $this->json(['success' => true, 'message' => 'Progrès sauvegardé']);

        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>

namespace App\Controller;

use App\Entity\Formation;
use App\Entity\FormationReview;
use App\Entity\FormationModule;
use App\Entity\FormationResource;
use App\Entity\FormationEnrollment;
use App\Form\FormationType;
use App\Repository\FormationRepository;
use App\Repository\FormationReviewRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/formation')]
class FormationController extends AbstractController
{
    #[Route('/', name: 'app_formation_index', methods: ['GET'])]
    public function index(Request $request, FormationRepository $formationRepository): Response
    {
        $filters = [
            'isFree' => $request->query->get('isFree'),
            'theme' => $request->query->get('theme'),
            'level' => $request->query->get('level'),
            'minDuration' => $request->query->get('minDuration'),
            'maxDuration' => $request->query->get('maxDuration'),
            'availability' => $request->query->get('availability'),
            'search' => $request->query->get('search'),
            'orderBy' => $request->query->get('orderBy', 'newest'),
        ];

        $formations = $formationRepository->findWithFilters($filters);
        $availableThemes = $formationRepository->getAvailableThemes();
        $availableLevels = $formationRepository->getAvailableLevels();
        $durationRange = $formationRepository->getDurationRange();
        $popularFormations = $formationRepository->findPopular(3);

        return $this->render('formation/index.html.twig', [
            'formations' => $formations,
            'availableThemes' => $availableThemes,
            'availableLevels' => $availableLevels,
            'durationRange' => $durationRange,
            'popularFormations' => $popularFormations,
            'currentFilters' => $filters,
        ]);
    }

    #[Route('/new', name: 'app_formation_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $formation = new Formation();
        $form = $this->createForm(FormationType::class, $formation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Set default values
            if ($formation->isFree()) {
                $formation->setCout(0);
            }
            
            // Handle modules positions
            $position = 1;
            foreach ($formation->getModules() as $module) {
                if (!$module->getPosition()) {
                    $module->setPosition($position);
                }
                $position++;
            }
            
            $entityManager->persist($formation);
            $entityManager->flush();

            return $this->redirectToRoute('app_formation_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('formation/new.html.twig', [
            'formation' => $formation,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_formation_show', methods: ['GET'])]
    public function show(Formation $formation, FormationReviewRepository $reviewRepository, EntityManagerInterface $entityManager): Response
    {
        $reviews = $reviewRepository->findVisibleByFormation($formation);
        $reviewStats = $reviewRepository->getReviewsStats($formation);
        $modules = $formation->getModules()->filter(fn($module) => $module->isActive());
        $resources = $formation->getResources()->filter(fn($resource) => $resource->isDownloadable());

        // Check if user is enrolled in this formation
        $isEnrolled = false;
        $enrollment = null;
        if ($this->getUser()) {
            $enrollment = $entityManager->getRepository(FormationEnrollment::class)
                ->findOneBy([
                    'user' => $this->getUser(),
                    'formation' => $formation,
                    'status' => 'active'
                ]);
            $isEnrolled = $enrollment !== null;
        }

        return $this->render('formation/show.html.twig', [
            'formation' => $formation,
            'reviews' => $reviews,
            'reviewStats' => $reviewStats,
            'modules' => $modules,
            'resources' => $resources,
            'isEnrolled' => $isEnrolled,
            'enrollment' => $enrollment,
        ]);
    }

    #[Route('/{id}/review', name: 'app_formation_add_review', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function addReview(Request $request, Formation $formation, EntityManagerInterface $entityManager, FormationReviewRepository $reviewRepository): JsonResponse
    {
        $user = $this->getUser();
        
        // Check if user already reviewed this formation
        if ($reviewRepository->hasUserReviewedFormation($user, $formation)) {
            return new JsonResponse(['error' => 'Vous avez déjà donné votre avis sur cette formation'], 400);
        }

        $data = json_decode($request->getContent(), true);
        $rating = (int) ($data['rating'] ?? 0);
        $comment = trim($data['comment'] ?? '');

        if ($rating < 1 || $rating > 5) {
            return new JsonResponse(['error' => 'La note doit être entre 1 et 5'], 400);
        }

        try {
            $review = new FormationReview();
            $review->setFormation($formation);
            $review->setUser($user);
            $review->setRating($rating);
            $review->setComment($comment ?: null);

            $entityManager->persist($review);
            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Votre avis a été ajouté avec succès et sera visible après modération',
                'review' => [
                    'rating' => $review->getRating(),
                    'comment' => $review->getComment(),
                    'userName' => $user->getPrenom() . ' ' . substr($user->getNom(), 0, 1) . '.',
                    'createdAt' => $review->getCreatedAt()->format('d/m/Y'),
                ]
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Une erreur est survenue lors de l\'ajout de votre avis. Veuillez réessayer.'
            ], 500);
        }
    }

    #[Route('/{id}/download/{resourceId}', name: 'app_formation_download_resource', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function downloadResource(Formation $formation, int $resourceId, EntityManagerInterface $entityManager): Response
    {
        $resource = $entityManager->getRepository(FormationResource::class)->find($resourceId);
        
        if (!$resource || $resource->getFormation() !== $formation || !$resource->isDownloadable()) {
            throw $this->createNotFoundException('Resource not found or not downloadable');
        }

        $filePath = $this->getParameter('kernel.project_dir') . '/public/assets/files/formations/' . $resource->getFileName();
        
        if (!file_exists($filePath)) {
            throw $this->createNotFoundException('File not found');
        }

        $response = new BinaryFileResponse($filePath);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $resource->getOriginalName() ?: $resource->getFileName()
        );

        return $response;
    }

    #[Route('/{id}/edit', name: 'app_formation_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Formation $formation, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(FormationType::class, $formation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Handle modules positions
            $position = 1;
            foreach ($formation->getModules() as $module) {
                if (!$module->getPosition()) {
                    $module->setPosition($position);
                }
                $position++;
            }
            
            $entityManager->flush();

            return $this->redirectToRoute('app_formation_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('formation/edit.html.twig', [
            'formation' => $formation,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_formation_delete', methods: ['POST'])]
    public function delete(Request $request, Formation $formation, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$formation->getId(), $request->request->get('_token'))) {
            $entityManager->remove($formation);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_formation_index', [], Response::HTTP_SEE_OTHER);
    }
}

<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>

namespace App\Controller;

use App\Entity\FormationReview;
use App\Repository\FormationReviewRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/dashboard/reviews')]
#[IsGranted('ROLE_ADMIN')]
class DashboardReviewController extends AbstractController
{
    #[Route('/', name: 'app_dashboard_reviews_index', methods: ['GET'])]
    public function index(FormationReviewRepository $reviewRepository): Response
    {
        $pendingReviews = $reviewRepository->findPendingReviews();
        
        return $this->render('dashboard/reviews/index.html.twig', [
            'pendingReviews' => $pendingReviews,
        ]);
    }
    
    #[Route('/{id}/approve', name: 'app_dashboard_review_approve', methods: ['POST'])]
    public function approve(FormationReview $review, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            $review->setApproved(true);
            $entityManager->flush();
            
            return new JsonResponse([
                'success' => true,
                'message' => 'Avis approuvé avec succès'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur lors de l\'approbation de l\'avis'
            ], 500);
        }
    }
    
    #[Route('/{id}/reject', name: 'app_dashboard_review_reject', methods: ['POST'])]
    public function reject(FormationReview $review, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            // On supprime complètement l'avis rejeté
            $entityManager->remove($review);
            $entityManager->flush();
            
            return new JsonResponse([
                'success' => true,
                'message' => 'Avis rejeté et supprimé avec succès'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur lors du rejet de l\'avis'
            ], 500);
        }
    }
}
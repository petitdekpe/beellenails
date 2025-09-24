<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>

namespace App\Controller;

use App\Entity\PromoCode;
use App\Form\PromoCodeType;
use App\Repository\PromoCodeRepository;
use App\Repository\PromoCodeUsageRepository;
use App\Service\PromoCodeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Knp\Component\Pager\PaginatorInterface;

#[Route('/dashboard/promo-codes')]
#[IsGranted('ROLE_ADMIN')]
class PromoCodeController extends AbstractController
{
    public function __construct(
        private PromoCodeService $promoCodeService,
        private PromoCodeRepository $promoCodeRepository,
        private PromoCodeUsageRepository $usageRepository,
        private EntityManagerInterface $entityManager
    ) {}

    #[Route('', name: 'app_promo_codes', methods: ['GET'])]
    public function index(Request $request, PaginatorInterface $paginator): Response
    {
        $search = $request->query->get('search', '');
        $status = $request->query->get('status', 'all');

        $queryBuilder = $this->promoCodeRepository->createQueryBuilder('p');

        if ($search) {
            $queryBuilder->andWhere('p.code LIKE :search OR p.name LIKE :search')
                        ->setParameter('search', '%' . $search . '%');
        }

        if ($status === 'active') {
            $now = new \DateTime();
            $queryBuilder->andWhere('p.isActive = true')
                        ->andWhere('p.validFrom <= :now')
                        ->andWhere('p.validUntil >= :now')
                        ->setParameter('now', $now);
        } elseif ($status === 'expired') {
            $now = new \DateTime();
            $queryBuilder->andWhere('p.validUntil < :now')
                        ->setParameter('now', $now);
        } elseif ($status === 'inactive') {
            $queryBuilder->andWhere('p.isActive = false');
        }

        $queryBuilder->orderBy('p.createdAt', 'DESC');

        $promoCodes = $paginator->paginate(
            $queryBuilder->getQuery(),
            $request->query->getInt('page', 1),
            20
        );

        // Statistiques rapides
        $stats = $this->promoCodeService->getGlobalStatistics();
        $expiringSoon = $this->promoCodeRepository->findExpiringSoon(7);

        return $this->render('dashboard/promo-codes/index.html.twig', [
            'promoCodes' => $promoCodes,
            'search' => $search,
            'status' => $status,
            'stats' => $stats,
            'expiringSoon' => $expiringSoon,
        ]);
    }

    #[Route('/new', name: 'app_promo_code_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $promoCode = new PromoCode();
        
        // Valeurs par défaut
        $promoCode->setValidFrom(new \DateTime());
        $promoCode->setValidUntil(new \DateTime('+1 month'));
        $promoCode->setDiscountType('percentage'); // Valeur par défaut
        
        $form = $this->createForm(PromoCodeType::class, $promoCode);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->entityManager->persist($promoCode);
                $this->entityManager->flush();

                $this->addFlash('success', '✅ Code promo "' . $promoCode->getCode() . '" créé avec succès');
                return $this->redirectToRoute('app_promo_codes');

            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la création : ' . $e->getMessage());
            }
        }

        return $this->render('dashboard/promo-codes/new.html.twig', [
            'form' => $form,
            'promoCode' => $promoCode,
        ]);
    }

    #[Route('/{id}', name: 'app_promo_code_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(?PromoCode $promoCode): Response
    {
        if (!$promoCode) {
            $this->addFlash('error', 'Code promo non trouvé.');
            return $this->redirectToRoute('app_promo_codes');
        }

        $stats = $this->promoCodeRepository->getUsageStats($promoCode);
        
        // Récupérer les 10 dernières utilisations
        $recentUsages = $this->usageRepository->findBy(
            ['promoCode' => $promoCode], 
            ['attemptedAt' => 'DESC'], 
            10
        );

        return $this->render('dashboard/promo-codes/show.html.twig', [
            'promoCode' => $promoCode,
            'stats' => $stats,
            'recentUsages' => $recentUsages,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_promo_code_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, ?PromoCode $promoCode): Response
    {
        if (!$promoCode) {
            $this->addFlash('error', 'Code promo non trouvé.');
            return $this->redirectToRoute('app_promo_codes');
        }

        $form = $this->createForm(PromoCodeType::class, $promoCode);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->entityManager->flush();
                $this->addFlash('success', '✅ Code promo mis à jour avec succès');
                return $this->redirectToRoute('app_promo_code_show', ['id' => $promoCode->getId()]);

            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la mise à jour : ' . $e->getMessage());
            }
        }

        return $this->render('dashboard/promo-codes/edit.html.twig', [
            'form' => $form,
            'promoCode' => $promoCode,
        ]);
    }

    #[Route('/{id}/toggle-status', name: 'app_promo_code_toggle_status', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function toggleStatus(?PromoCode $promoCode): JsonResponse
    {
        if (!$promoCode) {
            return new JsonResponse(['success' => false, 'message' => 'Code promo non trouvé'], 404);
        }

        try {
            $promoCode->setIsActive(!$promoCode->isActive());
            $this->entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'newStatus' => $promoCode->isActive(),
                'message' => $promoCode->isActive() ? 'Code activé' : 'Code désactivé'
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/{id}/duplicate', name: 'app_promo_code_duplicate', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function duplicate(?PromoCode $originalPromoCode): Response
    {
        if (!$originalPromoCode) {
            $this->addFlash('error', 'Code promo non trouvé.');
            return $this->redirectToRoute('app_promo_codes');
        }

        try {
            $newPromoCode = new PromoCode();
            $newPromoCode->setCode($this->promoCodeService->generateRandomCode())
                        ->setName($originalPromoCode->getName() . ' (Copie)')
                        ->setDescription($originalPromoCode->getDescription())
                        ->setDiscountType($originalPromoCode->getDiscountType())
                        ->setDiscountValue($originalPromoCode->getDiscountValue())
                        ->setMinimumAmount($originalPromoCode->getMinimumAmount())
                        ->setMaximumDiscount($originalPromoCode->getMaximumDiscount())
                        ->setValidFrom(new \DateTime())
                        ->setValidUntil(new \DateTime('+1 month'))
                        ->setMaxUsageGlobal($originalPromoCode->getMaxUsageGlobal())
                        ->setMaxUsagePerUser($originalPromoCode->getMaxUsagePerUser())
                        ->setIsActive(false); // Créé inactif par sécurité

            // Copier les prestations éligibles
            foreach ($originalPromoCode->getEligiblePrestations() as $prestation) {
                $newPromoCode->addEligiblePrestation($prestation);
            }

            $this->entityManager->persist($newPromoCode);
            $this->entityManager->flush();

            $this->addFlash('success', '✅ Code promo dupliqué avec le code: ' . $newPromoCode->getCode());
            return $this->redirectToRoute('app_promo_code_edit', ['id' => $newPromoCode->getId()]);

        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la duplication : ' . $e->getMessage());
            return $this->redirectToRoute('app_promo_code_show', ['id' => $originalPromoCode->getId()]);
        }
    }

    #[Route('/{id}/delete', name: 'app_promo_code_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, ?PromoCode $promoCode): Response
    {
        if (!$promoCode) {
            $this->addFlash('error', 'Code promo non trouvé.');
            return $this->redirectToRoute('app_promo_codes');
        }

        if ($this->isCsrfTokenValid('delete' . $promoCode->getId(), $request->request->get('_token'))) {
            try {
                // Vérifier s'il y a des utilisations validées
                $validatedUsages = $this->usageRepository->count([
                    'promoCode' => $promoCode,
                    'status' => 'validated'
                ]);

                if ($validatedUsages > 0) {
                    $this->addFlash('warning', 
                        '⚠️ Impossible de supprimer ce code : ' . $validatedUsages . ' utilisation(s) validée(s). Désactivez-le plutôt.');
                } else {
                    $codeName = $promoCode->getCode();
                    $this->entityManager->remove($promoCode);
                    $this->entityManager->flush();
                    $this->addFlash('success', '✅ Code promo "' . $codeName . '" supprimé avec succès');
                }

            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la suppression : ' . $e->getMessage());
            }
        }

        return $this->redirectToRoute('app_promo_codes');
    }

    #[Route('/generate-code', name: 'app_promo_code_generate', methods: ['POST'])]
    public function generateCode(): JsonResponse
    {
        try {
            $code = $this->promoCodeService->generateRandomCode();
            return new JsonResponse(['code' => $code]);

        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Erreur de génération'], 500);
        }
    }

    #[Route('/stats', name: 'app_promo_codes_stats', methods: ['GET'])]
    public function stats(Request $request): Response
    {
        $period = $request->query->get('period', '30days');
        
        $from = match($period) {
            '7days' => new \DateTime('-7 days'),
            '30days' => new \DateTime('-30 days'),
            '90days' => new \DateTime('-90 days'),
            'year' => new \DateTime('-1 year'),
            default => new \DateTime('-30 days')
        };
        
        $to = new \DateTime();

        $globalStats = $this->promoCodeService->getGlobalStatistics();
        $usageByPeriod = $this->usageRepository->getUsageByPeriod($from, $to);
        $topCodes = $this->promoCodeRepository->findMostUsed(10);
        $topUsers = $this->usageRepository->findTopUsers(10);
        $suspiciousAttempts = $this->usageRepository->findSuspiciousAttempts();

        return $this->render('dashboard/promo-codes/stats.html.twig', [
            'globalStats' => $globalStats,
            'usageByPeriod' => $usageByPeriod,
            'topCodes' => $topCodes,
            'topUsers' => $topUsers,
            'suspiciousAttempts' => $suspiciousAttempts,
            'period' => $period,
            'from' => $from,
            'to' => $to,
        ]);
    }
}
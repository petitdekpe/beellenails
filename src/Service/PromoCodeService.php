<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>

namespace App\Service;

use App\Entity\PromoCode;
use App\Entity\PromoCodeUsage;
use App\Entity\User;
use App\Entity\Rendezvous;
use App\Entity\Prestation;
use App\Repository\PromoCodeRepository;
use App\Repository\PromoCodeUsageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Psr\Log\LoggerInterface;

class PromoCodeService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PromoCodeRepository $promoCodeRepository,
        private PromoCodeUsageRepository $usageRepository,
        private RequestStack $requestStack,
        private LoggerInterface $logger
    ) {}

    /**
     * Valide et applique un code promo
     */
    public function validateAndApplyPromoCode(
        string $code, 
        User $user, 
        Prestation $prestation,
        float $originalAmount
    ): array {
        try {
            // 1. Rechercher le code promo
            $promoCode = $this->promoCodeRepository->findActiveByCode($code);
            if (!$promoCode) {
                return $this->createResult(false, 'Code promo non trouvé ou inactif');
            }

            // 2. Vérifications de base
            $validationResult = $this->validatePromoCode($promoCode, $user, $prestation, $originalAmount);
            if (!$validationResult['isValid']) {
                $this->recordAttempt($promoCode, $user, $originalAmount, $validationResult['message']);
                return $validationResult;
            }

            // 3. Calculer la réduction
            $discountAmount = $this->calculateDiscount($promoCode, $originalAmount);
            $finalAmount = max(0, $originalAmount - $discountAmount);

            // 4. Enregistrer la tentative comme validée
            $usage = $this->recordValidatedUsage($promoCode, $user, $originalAmount, $discountAmount, $finalAmount);

            $this->logger->info('Code promo appliqué avec succès', [
                'code' => $code,
                'user_id' => $user->getId(),
                'original_amount' => $originalAmount,
                'discount_amount' => $discountAmount,
                'final_amount' => $finalAmount
            ]);

            return $this->createResult(true, 'Code promo appliqué avec succès', [
                'promoCode' => $promoCode,
                'originalAmount' => $originalAmount,
                'discountAmount' => $discountAmount,
                'finalAmount' => $finalAmount,
                'usage' => $usage
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de l\'application du code promo', [
                'code' => $code,
                'user_id' => $user->getId(),
                'error' => $e->getMessage()
            ]);
            
            return $this->createResult(false, 'Erreur technique lors de l\'application du code');
        }
    }

    /**
     * Valide un code promo par code sans l'appliquer définitivement
     */
    public function validatePromoCodeOnly(
        string $code, 
        User $user, 
        Prestation $prestation,
        float $originalAmount
    ): array {
        try {
            // 1. Rechercher le code promo
            $promoCode = $this->promoCodeRepository->findActiveByCode($code);
            if (!$promoCode) {
                return $this->createResult(false, 'Code promo non trouvé ou inactif');
            }

            // 2. Vérifications de base
            $validationResult = $this->validatePromoCode($promoCode, $user, $prestation, $originalAmount);
            if (!$validationResult['isValid']) {
                return $validationResult;
            }

            // 3. Calculer la réduction potentielle
            $discountAmount = $this->calculateDiscount($promoCode, $originalAmount);
            $finalAmount = max(0, $originalAmount - $discountAmount);

            return $this->createResult(true, 'Code promo valide', [
                'promoCode' => $promoCode,
                'originalAmount' => $originalAmount,
                'discountAmount' => $discountAmount,
                'finalAmount' => $finalAmount
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la validation du code promo', [
                'code' => $code,
                'user_id' => $user->getId(),
                'error' => $e->getMessage()
            ]);
            
            return $this->createResult(false, 'Erreur technique lors de la validation du code');
        }
    }

    /**
     * Applique définitivement un code promo en attente lors de la confirmation du rendez-vous
     */
    public function applyPendingPromoCode(Rendezvous $rendezvous): array
    {
        $pendingCode = $rendezvous->getPendingPromoCode();
        if (!$pendingCode) {
            return $this->createResult(false, 'Aucun code promo en attente');
        }

        try {
            // Rechercher le code promo
            $promoCode = $this->promoCodeRepository->findActiveByCode($pendingCode);
            if (!$promoCode) {
                return $this->createResult(false, 'Code promo non valide ou expiré');
            }

            // Re-valider le code (au cas où les conditions auraient changé)
            $validationResult = $this->validatePromoCode(
                $promoCode, 
                $rendezvous->getUser(), 
                $rendezvous->getPrestation(),
                (float)$rendezvous->getOriginalAmount()
            );
            
            if (!$validationResult['isValid']) {
                return $validationResult;
            }

            // Enregistrer la tentative comme validée
            $discountAmount = (float)$rendezvous->getDiscountAmount();
            $finalAmount = (float)$rendezvous->getTotalCost();
            
            $usage = $this->recordValidatedUsage(
                $promoCode, 
                $rendezvous->getUser(), 
                (float)$rendezvous->getOriginalAmount(),
                $discountAmount,
                $finalAmount
            );

            // Associer définitivement le code promo au rendez-vous
            $rendezvous->setPromoCode($promoCode);
            $rendezvous->setPendingPromoCode(null); // Nettoyer le code en attente

            $this->logger->info('Code promo appliqué définitivement', [
                'code' => $pendingCode,
                'rendezvous_id' => $rendezvous->getId(),
                'user_id' => $rendezvous->getUser()->getId(),
                'discount_amount' => $discountAmount
            ]);

            return $this->createResult(true, 'Code promo appliqué avec succès', [
                'promoCode' => $promoCode,
                'usage' => $usage,
                'discountAmount' => $discountAmount
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de l\'application définitive du code promo', [
                'code' => $pendingCode,
                'rendezvous_id' => $rendezvous->getId(),
                'error' => $e->getMessage()
            ]);
            
            return $this->createResult(false, 'Erreur technique lors de l\'application du code');
        }
    }

    /**
     * Valide un code promo sans l'appliquer (méthode interne)
     */
    private function validatePromoCode(
        PromoCode $promoCode, 
        User $user, 
        Prestation $prestation,
        float $originalAmount
    ): array {
        // Vérification temporelle
        if (!$promoCode->isValid()) {
            return $this->createResult(false, 'Ce code promo a expiré');
        }

        // Vérification de l'éligibilité de la prestation
        if (!$promoCode->isEligibleForPrestation($prestation)) {
            return $this->createResult(false, 'Ce code promo n\'est pas valide pour cette prestation');
        }

        // Vérification du montant minimum
        if ($promoCode->getMinimumAmount() && $originalAmount < (float)$promoCode->getMinimumAmount()) {
            return $this->createResult(false, sprintf(
                'Montant minimum de %.2f F CFA requis pour ce code promo',
                (float)$promoCode->getMinimumAmount()
            ));
        }

        // Vérification des limites d'utilisation globales
        if ($promoCode->getMaxUsageGlobal() && 
            $promoCode->getCurrentUsage() >= $promoCode->getMaxUsageGlobal()) {
            return $this->createResult(false, 'Ce code promo a atteint sa limite d\'utilisation');
        }

        // Vérification des limites par utilisateur
        if ($promoCode->getMaxUsagePerUser()) {
            $userUsageCount = $this->usageRepository->countValidatedUsagesByUser($promoCode, $user);
            if ($userUsageCount >= $promoCode->getMaxUsagePerUser()) {
                return $this->createResult(false, 'Vous avez déjà utilisé ce code promo le nombre maximum de fois');
            }
        }

        // Protection anti-spam
        $recentAttempts = $this->usageRepository->findRecentAttemptsByUser($user);
        if (count($recentAttempts) > 10) {
            return $this->createResult(false, 'Trop de tentatives récentes. Veuillez patienter.');
        }

        return $this->createResult(true, 'Code promo valide');
    }

    /**
     * Calcule le montant de la réduction
     */
    public function calculateDiscount(PromoCode $promoCode, float $originalAmount): float
    {
        $discountValue = (float)$promoCode->getDiscountValue();
        
        if ($promoCode->getDiscountType() === 'percentage') {
            $discount = $originalAmount * ($discountValue / 100);
        } else {
            $discount = $discountValue;
        }

        // Appliquer la limite maximale de réduction si définie
        if ($promoCode->getMaximumDiscount()) {
            $discount = min($discount, (float)$promoCode->getMaximumDiscount());
        }

        // La réduction ne peut pas être supérieure au montant original
        return min($discount, $originalAmount);
    }

    /**
     * Confirme l'utilisation d'un code promo après la finalisation du rendez-vous
     */
    public function confirmPromoCodeUsage(Rendezvous $rendezvous): bool
    {
        $promoCode = $rendezvous->getPromoCode();
        if (!$promoCode) {
            return true; // Pas de code promo à confirmer
        }

        // Vérifier que le rendez-vous est dans un état valide
        if (!in_array($rendezvous->getStatus(), ['Rendez-vous pris', 'Rendez-vous confirmé'])) {
            return false;
        }

        try {
            // Incrémenter le compteur d'utilisation du code promo
            $promoCode->incrementUsage();
            $this->entityManager->flush();

            $this->logger->info('Usage de code promo confirmé', [
                'promo_code' => $promoCode->getCode(),
                'rendezvous_id' => $rendezvous->getId(),
                'current_usage' => $promoCode->getCurrentUsage()
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la confirmation du code promo', [
                'promo_code' => $promoCode->getCode(),
                'rendezvous_id' => $rendezvous->getId(),
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Révoque l'utilisation d'un code promo (en cas d'annulation)
     */
    public function revokePromoCodeUsage(Rendezvous $rendezvous, string $reason = 'Annulation du rendez-vous'): bool
    {
        $promoCode = $rendezvous->getPromoCode();
        if (!$promoCode) {
            return true; // Pas de code promo à révoquer
        }

        try {
            // Trouver l'usage correspondant
            $usage = $this->usageRepository->findOneBy([
                'promoCode' => $promoCode,
                'user' => $rendezvous->getUser(),
                'rendezvous' => $rendezvous,
                'status' => PromoCodeUsage::STATUS_VALIDATED
            ]);

            if ($usage) {
                $usage->revoke($reason);
                $promoCode->decrementUsage();
                $this->entityManager->flush();

                $this->logger->info('Usage de code promo révoqué', [
                    'promo_code' => $promoCode->getCode(),
                    'rendezvous_id' => $rendezvous->getId(),
                    'reason' => $reason
                ]);
            }

            return true;

        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la révocation du code promo', [
                'promo_code' => $promoCode->getCode(),
                'rendezvous_id' => $rendezvous->getId(),
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Enregistre une tentative d'utilisation
     */
    private function recordAttempt(PromoCode $promoCode, User $user, float $originalAmount, string $notes = null): PromoCodeUsage
    {
        $usage = new PromoCodeUsage();
        $usage->setPromoCode($promoCode)
              ->setUser($user)
              ->setOriginalAmount((string)$originalAmount)
              ->setNotes($notes);

        $this->addRequestInfo($usage);
        
        $this->entityManager->persist($usage);
        $this->entityManager->flush();

        return $usage;
    }

    /**
     * Enregistre une utilisation validée
     */
    private function recordValidatedUsage(
        PromoCode $promoCode, 
        User $user, 
        float $originalAmount,
        float $discountAmount, 
        float $finalAmount
    ): PromoCodeUsage {
        $usage = new PromoCodeUsage();
        $usage->setPromoCode($promoCode)
              ->setUser($user)
              ->setOriginalAmount((string)$originalAmount)
              ->setDiscountAmount((string)$discountAmount)
              ->setFinalAmount((string)$finalAmount)
              ->validate();

        $this->addRequestInfo($usage);
        
        $this->entityManager->persist($usage);
        $this->entityManager->flush();

        return $usage;
    }

    /**
     * Ajoute les informations de la requête à l'usage
     */
    private function addRequestInfo(PromoCodeUsage $usage): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request) {
            $usage->setIpAddress($request->getClientIp())
                  ->setUserAgent($request->headers->get('User-Agent'));
        }
    }

    /**
     * Crée un résultat standardisé
     */
    private function createResult(bool $isValid, string $message, array $data = []): array
    {
        return array_merge([
            'isValid' => $isValid,
            'message' => $message
        ], $data);
    }

    /**
     * Statistiques globales des codes promo
     */
    public function getGlobalStatistics(): array
    {
        return $this->usageRepository->getGlobalStats();
    }

    /**
     * Génère un code promo aléatoire
     */
    public function generateRandomCode(int $length = 8): string
    {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $code = '';
        
        do {
            $code = '';
            for ($i = 0; $i < $length; $i++) {
                $code .= $characters[random_int(0, strlen($characters) - 1)];
            }
        } while ($this->promoCodeRepository->findOneBy(['code' => $code]));
        
        return $code;
    }
}
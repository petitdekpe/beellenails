<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>


namespace App\Controller;

use App\Form\TermsType;
use App\Entity\Rendezvous;
use App\Repository\BookingSettingsRepository;
use App\Repository\RendezvousRepository;
use App\Service\PromoCodeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class RecapController extends AbstractController
{
    public function __construct(
        private PromoCodeService $promoCodeService
    ) {}

    #[Route('/recap/{rendezvous}', name: 'app_recap')]
    #[IsGranted("ROLE_USER")]
    public function index(Rendezvous $rendezvous, Request $request, EntityManagerInterface $entityManager, RendezvousRepository $rendezvousRepository, BookingSettingsRepository $bookingSettingsRepository): Response
    {
        // Créer le formulaire TermsType 
        $form = $this->createForm(TermsType::class);
        $form->handleRequest($request);

        $user = $this->getUser();
        $rendezvous->setUser($user);
        $rendezvous->setStatus("Paiement en attente");
        $holdMinutes = $bookingSettingsRepository->getHoldDurationMinutes();
        $rendezvous->setExpiresAt((new \DateTime())->modify("+{$holdMinutes} minutes"));

        // Calculer et enregistrer le coût total
        $rendezvous->updateTotalCost();

        // Vérifier si un autre rendez-vous occupe déjà ce jour/créneau
        // (confirmé, ou tentative/paiement en attente pas encore expiré)
        if ($rendezvousRepository->hasActiveHoldOrConfirmedConflict($rendezvous->getDay(), $rendezvous->getCreneau(), $rendezvous->getId())) {
            $this->addFlash('error', 'Ce créneau vient d\'être réservé par un autre client. Merci d\'en choisir un autre.');
            return $this->redirectToRoute('app_calendar');
        }

        $entityManager->persist($rendezvous);
        $entityManager->flush();

        // Ajouter l'utilisateur actuel au rendez-vous

        if ($form->isSubmitted() && $form->isValid()) {
            // Traitement du code promo si fourni
            $promoCodeValue = $form->get('promoCode')->getData();
            if ($promoCodeValue) {
                try {
                    // Valider le code promo sans l'appliquer définitivement
                    $result = $this->promoCodeService->validatePromoCodeOnly(
                        $promoCodeValue, 
                        $this->getUser(),
                        $rendezvous->getPrestation(),
                        $rendezvous->getTotalCost()
                    );
                    
                    if ($result['isValid']) {
                        // Stocker temporairement le code promo en attente
                        $rendezvous->setPendingPromoCode($promoCodeValue);
                        
                        // Calculer et afficher la réduction potentielle
                        if (isset($result['discountAmount']) && $result['discountAmount'] > 0) {
                            $newTotalCost = $rendezvous->getTotalCost() - $result['discountAmount'];
                            $rendezvous->setOriginalAmount($rendezvous->getTotalCost());
                            $rendezvous->setTotalCost($newTotalCost);
                            $rendezvous->setDiscountAmount((string)$result['discountAmount']);
                        }
                        
                        $this->addFlash('success', sprintf(
                            'Code promo "%s" validé ! Réduction de %s FCFA appliquée.',
                            $promoCodeValue,
                            number_format($result['discountAmount'], 0, ',', ' ')
                        ));
                        
                        $entityManager->flush();
                    } else {
                        $this->addFlash('error', $result['message']);
                        // Retourner à la page actuelle pour afficher l'erreur
                        return $this->render('recap/index.html.twig', [
                            'form' => $form->createView(),
                            'supplements' => $rendezvous->getSupplement(),
                            'rendezvous' => $rendezvous,
                            'holdDurationMinutes' => $holdMinutes,
                        ]);
                    }
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Erreur lors de la validation du code promo : ' . $e->getMessage());
                    return $this->render('recap/index.html.twig', [
                        'form' => $form->createView(),
                        'supplements' => $rendezvous->getSupplement(),
                        'rendezvous' => $rendezvous,
                        'holdDurationMinutes' => $holdMinutes,
                    ]);
                }
            }

            return $this->redirectToRoute('payment_choice', ['rendezvous' => $rendezvous->getId()]);
        }

        // Récupérer les suppléments associés à ce rendez-vous
        $supplements = $rendezvous->getSupplement();

        return $this->render('recap/index.html.twig', [
            'form' => $form->createView(),
            'supplements' => $supplements, // Passer la liste des suppléments à la vue Twig
            'rendezvous' => $rendezvous, // Passer l'objet rendezvous avec le coût total calculé
            'holdDurationMinutes' => $holdMinutes,
        ]);
    }
}

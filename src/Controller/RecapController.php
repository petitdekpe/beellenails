<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>


namespace App\Controller;

use App\Form\TermsType;
use App\Entity\Rendezvous;
use App\Repository\RendezvousRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class RecapController extends AbstractController
{
    #[Route('/recap/{rendezvous}', name: 'app_recap')]
    #[IsGranted("ROLE_USER")]
    public function index(Rendezvous $rendezvous, Request $request, EntityManagerInterface $entityManager, RendezvousRepository $rendezvousRepository): Response
    {
        // Créer le formulaire TermsType 
        $form = $this->createForm(TermsType::class);
        $form->handleRequest($request);

        $user = $this->getUser();
        $rendezvous->setUser($user);
        $rendezvous->setStatus("Tentative échoué");

        // Calculer et enregistrer le coût total
        $rendezvous->updateTotalCost();

        // Vérifier si un rendez-vous avec le même jour et créneau existe déjà
        $existingRendezvous = $rendezvousRepository->findOneBy([
            'day' => $rendezvous->getDay(),
            'creneau' => $rendezvous->getCreneau(),
            'status' => 'Rendez-vous pris'
        ]);

        if ($existingRendezvous) {
            // Si un rendez-vous existe déjà, rediriger vers la page de prise de rendez-vous
            return $this->redirectToRoute('app_calendar');
        }

        $entityManager->persist($rendezvous);
        $entityManager->flush();

        // Ajouter l'utilisateur actuel au rendez-vous

        if ($form->isSubmitted() && $form->isValid()) {

            return $this->redirectToRoute('payment_choice', ['rendezvous' => $rendezvous->getId()]);
        }

        // Récupérer les suppléments associés à ce rendez-vous
        $supplements = $rendezvous->getSupplement();

        return $this->render('recap/index.html.twig', [
            'form' => $form->createView(),
            'supplements' => $supplements, // Passer la liste des suppléments à la vue Twig
            'rendezvous' => $rendezvous, // Passer l'objet rendezvous avec le coût total calculé
        ]);
    }
}

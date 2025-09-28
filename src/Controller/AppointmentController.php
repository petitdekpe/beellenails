<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>

namespace App\Controller;

use App\Entity\Rendezvous;
use App\Form\PaiementType;
use App\Form\ChoixDateType;
use App\Form\ChoixPrestationType;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\Id;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;


class AppointmentController extends AbstractController
{
    #[Route('/appointment', name: 'app_appointment')]
    public function index(): Response
    {
        return $this->render('appointment/index.html.twig', [
            'controller_name' => 'AppointmentController',
        ]);
    }

    #[Route('/appointment/prestation', name: 'app_appointment_prestation')]
    public function choixPrestation(Request $request): Response
    {
        $form = $this->createForm(ChoixPrestationType::class);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            // Stockez les données du formulaire dans la session
            $formData = $form->getData();
            $request->getSession()->set('choixPrestation_data', $formData);

            // Redirigez vers l'étape suivante
            return $this->redirectToRoute('app_appointment_creneau', [], Response::HTTP_SEE_OTHER);
        }

        // Si le formulaire n'est pas soumis ou n'est pas valide, affichez le formulaire
        return $this->render('appointment/choixPrestation.html.twig', [
            'form' => $form->createView(),
        ]);
    }
    #[Route('/appointment/creneau', name: 'app_appointment_creneau')]
    public function choixDate(Request $request): Response
    {
        $form = $this->createForm(ChoixDateType::class);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            // Stockez les données du formulaire dans la session
            $formData = $form->getData();
            $request->getSession()->set('choixDate_data', $formData);

            // Redirigez vers l'étape suivante
            return $this->redirectToRoute('app_appointment_end');
        }

        // Si le formulaire n'est pas soumis ou n'est pas valide, affichez le formulaire
        return $this->render('appointment/choixDate.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    

    #[Route('/appointment/end', name: 'app_appointment_end')]
    #[IsGranted("ROLE_USER")]
    public function Paiement(Request $request, SessionInterface $session, Security $security, EntityManagerInterface $entityManager): Response
    {
        // Récupérer d'autres données nécessaires pour compléter le rendez-vous
        $choixPrestation = $session->get('choixPrestation_data');
        $choixDate = $session->get('choixDate_data');
        
        // Créer une instance de Rendezvous
        $rendezvous = new Rendezvous();

        // Créer le formulaire PaiementType en passant le rendezvous nouvellement créé
        $form = $this->createForm(PaiementType::class, $rendezvous);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Récupérer d'autres données nécessaires pour compléter le rendez-vous
            $prestation = $choixPrestation['prestation'];
            $day = $choixDate['day'];
            $creneau = $choixDate['creneau'];
            $paid = "false";
            // Récupérer l'utilisateur actuel
            $user = $this->getUser(); 
            // Ajouter l'utilisateur actuel au rendez-vous
            $rendezvous->setUser($user);
            $rendezvous->setPrestation($prestation);
            $rendezvous->setDay($day);
            $rendezvous->setCreneau($creneau);
            $rendezvous->setPaid($paid);
            
            // Vérifier que le créneau appartient bien à la date sélectionnée (validation croisée)
            $creneauRepository = $entityManager->getRepository(\App\Entity\Creneau::class);
            $availableSlots = $creneauRepository->findAvailableSlots($day);
            $isSlotValid = false;

            foreach ($availableSlots as $slot) {
                if ($slot->getId() === $creneau->getId()) {
                    $isSlotValid = true;
                    break;
                }
            }

            if (!$isSlotValid) {
                $this->addFlash('error', 'Le créneau sélectionné n\'est pas disponible pour cette date.');
                return $this->render('appointment/paiement.html.twig', [
                    'rendezvous' => $rendezvous,
                    'form' => $form,
                    'choixPrestation' => $choixPrestation,
                    'choixDate' => $choixDate,
                ]);
            }

            // Vérifier si le créneau est déjà en congé (double vérification)
            $existingConge = $entityManager->getRepository(Rendezvous::class)->findOneBy([
                'day' => $day,
                'creneau' => $creneau,
                'status' => 'Congé'
            ]);

            if ($existingConge) {
                $this->addFlash('error', 'Ce créneau est indisponible (en congé).');
                return $this->render('appointment/paiement.html.twig', [
                    'rendezvous' => $rendezvous,
                    'form' => $form,
                    'choixPrestation' => $choixPrestation,
                    'choixDate' => $choixDate,
                ]);
            }

            // Enregistrer le rendez-vous
            $entityManager->persist($rendezvous);
            $entityManager->flush();

            $session->remove('choixPrestation_data');
            $session->remove('choixDate_data');

            return $this->redirectToRoute('payment_choice', ['rendezvous' => $rendezvous->getId()]);
        }

        // Si le formulaire n'est pas soumis ou n'est pas valide, affichez le formulaire
        return $this->render('appointment/paiement.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    
}

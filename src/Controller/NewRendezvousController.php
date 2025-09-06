<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <jy.ahouanvoedo@gmail.com>


namespace App\Controller;

use App\Entity\Prestation;
use App\Entity\Rendezvous;
use App\Form\PreRendezvousType;
use App\Repository\PrestationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class NewRendezvousController extends AbstractController
{
    #[Route('/newappointment/{prestation?}', name: 'app_new_rendezvous')]
    public function index(Request $request, EntityManagerInterface $entityManager, Prestation $prestation): Response
    {
   
        // Créer une nouvelle instance de Rendezvous
        $rendezvous = new Rendezvous();
        $rendezvous->setPrestation($prestation);
        //$rendezvous->setStatus("Validé");

        $form = $this->createForm(PreRendezvousType::class, $rendezvous, ['prestation' => $prestation]);
        $form->handleRequest($request);
        
        

        if ($form->isSubmitted() && $form->isValid()) {

            $formData = $form->getData();
            
            // Vérifier si le créneau est déjà en congé
            $existingConge = $entityManager->getRepository(Rendezvous::class)->findOneBy([
                'day' => $formData->getDay(),
                'creneau' => $formData->getCreneau(),
                'status' => 'Congé'
            ]);
            
            if ($existingConge) {
                $this->addFlash('error', 'Ce créneau est indisponible (en congé).');
                return $this->render('new_rendezvous/index.html.twig', [
                    'rendezvous' => $rendezvous,
                    'prestation' => $prestation,
                    'form' => $form,
                ]);
            }
            
            $request->getSession()->set('day',  $form->get('day')->getData());
            $request->getSession()->set('creneau',  $form->get('creneau')->getData());
            $request->getSession()->set('prestation', $prestation);
            

            $entityManager->persist($rendezvous);
            $entityManager->flush();
            

            return $this->redirectToRoute('app_recap', ['rendezvous' => $rendezvous->getId()]);
        }
        
        return $this->render('new_rendezvous/index.html.twig', [
            'controller_name' => 'NewRendezvousController',
            'rendezvous' => $rendezvous,
            'form' => $form,
        ]);
    }
}

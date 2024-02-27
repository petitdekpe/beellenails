<?php

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
        $rendezvou = new Rendezvous();
        $rendezvou->setPrestation($prestation);
        $rendezvou->setStatus("En attente");

        $form = $this->createForm(PreRendezvousType::class, $rendezvou, ['prestation' => $prestation]);
        $form->handleRequest($request);
        
        

        if ($form->isSubmitted() && $form->isValid()) {

            $formData = $form->getData();
            $request->getSession()->set('day',  $form->get('day')->getData());
            $request->getSession()->set('creneau',  $form->get('creneau')->getData());
            $request->getSession()->set('prestation', $prestation);
            

            $entityManager->persist($rendezvou);
            $entityManager->flush();
            

            return $this->redirectToRoute('app_recap', ['rendezvou' => $rendezvou->getId()]);
        }
        
        return $this->render('new_rendezvous/index.html.twig', [
            'controller_name' => 'NewRendezvousController',
            'rendezvou' => $rendezvou,
            'form' => $form,
        ]);
    }
}

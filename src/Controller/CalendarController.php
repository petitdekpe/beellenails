<?php

namespace App\Controller;

use App\Entity\Rendezvous;
use App\Form\RendezvousType;
use App\Repository\CreneauRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class CalendarController extends AbstractController
{
    #[Route('/prendrerdv/{prestationId?}/{categoryId?}', name: 'app_calendar')]
    #/[IsGranted("ROLE_USER")]
    public function index(Request $request, EntityManagerInterface $entityManager): Response
    {
        $rendezvou = new Rendezvous();
        $rendezvou->setStatus("Validé");
        $form = $this->createForm(RendezvousType::class, $rendezvou);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $formData = $form->getData();
            $request->getSession()->set('day',  $form->get('day')->getData());
            $request->getSession()->set('creneau',  $form->get('creneau')->getData());
            $request->getSession()->set('prestation',  $form->get('prestation')->getData());
            
            
            $entityManager->persist($rendezvou);
            $entityManager->flush();
            

            return $this->redirectToRoute('app_recap', ['rendezvou' => $rendezvou->getId()]);
        }
        
        return $this->render('calendar/index.html.twig', [
            'controller_name' => 'CalendarController',
            'rendezvou' => $rendezvou,
            'form' => $form,
        ]);
    }
    
    
    public function getAvailableSlots(Request $request, CreneauRepository $creneauRepository): JsonResponse
    {
        // Récupère la date sélectionnée depuis la requête
    $selectedDate = \DateTime::createFromFormat('Y-m-d', $request->request->get('date'));
    
    // Utilise la méthode personnalisée de ton repository pour récupérer les créneaux disponibles
    $availableSlots = $creneauRepository->findAvailableSlots($selectedDate);
    
    // Formate les créneaux disponibles pour les envoyer en réponse
    $formattedSlots = [];
    foreach ($availableSlots as $slot) {
        $formattedSlots[] = ['id' => $slot->getId(), 'libelle' => $slot->getLibelle()];
    }
        
        return new JsonResponse($formattedSlots);
    }
}
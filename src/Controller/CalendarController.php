<?php

namespace App\Controller;

use App\Entity\Rendezvous;
use App\Form\RendezvousType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class CalendarController extends AbstractController
{
    #[Route('/prendrerdv', name: 'app_calendar')]
    #/[IsGranted("ROLE_USER")]
    public function index(Request $request, EntityManagerInterface $entityManager): Response
    {
        $rendezvou = new Rendezvous();
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
}
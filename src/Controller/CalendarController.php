<?php

namespace App\Controller;

use App\Entity\Rendezvous;
use App\Form\RendezvousType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class CalendarController extends AbstractController
{
    #[Route('/prendrerdv', name: 'app_calendar')]
    public function index(Request $request, EntityManagerInterface $entityManager): Response
    {
        $rendezvou = new Rendezvous();
        $form = $this->createForm(RendezvousType::class, $rendezvou);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $entityManager->persist($rendezvou);
            $entityManager->flush();

            return $this->redirectToRoute('app_home', [], Response::HTTP_SEE_OTHER);
        }
        
        return $this->render('calendar/index.html.twig', [
            'controller_name' => 'CalendarController',
            'rendezvou' => $rendezvou,
            'form' => $form,
        ]);
    }
}
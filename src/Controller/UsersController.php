<?php

namespace App\Controller;

use App\Entity\Rendezvous;
use App\Repository\RendezvousRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class UsersController extends AbstractController
{
    #[Route('/users', name: 'app_users')]
    public function index(RendezvousRepository $rendezvousRepository): Response
    {
        // Get the currently logged-in user
        $user = $this->getUser();

        // Get the appointments for the logged-in user
        $appointments = $rendezvousRepository->findBy(['User' => $user]);

        return $this->render('users/index.html.twig', [
            'controller_name' => 'UsersController',
            'appointments' => $appointments,
        ]);
    }
        
}

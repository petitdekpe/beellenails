<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <jy.ahouanvoedo@gmail.com>


namespace App\Controller;

use App\Entity\User;
use App\Form\UserType;
use App\Entity\Rendezvous;
use App\Repository\RendezvousRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
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
        $appointments = $rendezvousRepository->findBy(['user' => $user], ['day' => 'DESC']);

        return $this->render('users/index.html.twig', [
            'controller_name' => 'UsersController',
            'appointments' => $appointments,
        ]);
    }
}

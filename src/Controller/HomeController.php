<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <jy.ahouanvoedo@gmail.com>


namespace App\Controller;

use App\Repository\HomeImageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(HomeImageRepository $homeImageRepository): Response
    {
        $homeImages = [
            'hero_slide' => $homeImageRepository->findActiveByType('hero_slide'),
            'muriel' => $homeImageRepository->findActiveByType('muriel'),
            'local' => $homeImageRepository->findActiveByType('local'),
            'prestations' => $homeImageRepository->findActiveByType('prestations'),
            'academie' => $homeImageRepository->findActiveByType('academie')
        ];

        return $this->render('home/index.html.twig', [
            'homeImages' => $homeImages,
        ]);
    }


}

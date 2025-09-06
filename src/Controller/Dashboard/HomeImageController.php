<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>

namespace App\Controller\Dashboard;

use App\Entity\HomeImage;
use App\Form\HomeImageType;
use App\Repository\HomeImageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/dashboard/home-images')]
#[IsGranted('ROLE_ADMIN')]
class HomeImageController extends AbstractController
{
    #[Route('/', name: 'dashboard_home_images_index', methods: ['GET'])]
    public function index(HomeImageRepository $homeImageRepository): Response
    {
        $imagesByType = [];
        foreach (HomeImage::getTypeChoices() as $label => $type) {
            $imagesByType[$type] = [
                'label' => $label,
                'images' => $homeImageRepository->findByTypeOrderedByPosition($type)
            ];
        }

        return $this->render('dashboard/home_images/index.html.twig', [
            'imagesByType' => $imagesByType,
        ]);
    }

    #[Route('/new', name: 'dashboard_home_images_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, HomeImageRepository $homeImageRepository): Response
    {
        $homeImage = new HomeImage();
        
        // Préremplir le type si passé en paramètre GET
        $preselectedType = $request->query->get('type');
        if ($preselectedType && in_array($preselectedType, HomeImage::getTypeChoices())) {
            $homeImage->setType($preselectedType);
            
            // Vérifier la limite pour le type Muriel
            if ($preselectedType === 'muriel') {
                $existingMurielImages = $homeImageRepository->findBy(['type' => 'muriel']);
                if (count($existingMurielImages) >= 1) {
                    $this->addFlash('error', 'Il ne peut y avoir qu\'une seule image pour Muriel. Supprimez l\'image existante avant d\'en ajouter une nouvelle.');
                    return $this->redirectToRoute('dashboard_home_images_index');
                }
            }
        }
        
        $form = $this->createForm(HomeImageType::class, $homeImage);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Double vérification pour Muriel au moment de la soumission
            if ($homeImage->getType() === 'muriel') {
                $existingMurielImages = $homeImageRepository->findBy(['type' => 'muriel']);
                if (count($existingMurielImages) >= 1) {
                    $this->addFlash('error', 'Il ne peut y avoir qu\'une seule image pour Muriel. Supprimez l\'image existante avant d\'en ajouter une nouvelle.');
                    return $this->redirectToRoute('dashboard_home_images_index');
                }
            }
            
            $entityManager->persist($homeImage);
            $entityManager->flush();

            $this->addFlash('success', 'Image ajoutée avec succès !');
            return $this->redirectToRoute('dashboard_home_images_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('dashboard/home_images/new.html.twig', [
            'home_image' => $homeImage,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'dashboard_home_images_show', methods: ['GET'])]
    public function show(HomeImage $homeImage): Response
    {
        return $this->render('dashboard/home_images/show.html.twig', [
            'home_image' => $homeImage,
        ]);
    }

    #[Route('/{id}/edit', name: 'dashboard_home_images_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, HomeImage $homeImage, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(HomeImageType::class, $homeImage);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Image modifiée avec succès !');
            return $this->redirectToRoute('dashboard_home_images_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('dashboard/home_images/edit.html.twig', [
            'home_image' => $homeImage,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/toggle', name: 'dashboard_home_images_toggle', methods: ['POST'])]
    public function toggle(HomeImage $homeImage, EntityManagerInterface $entityManager): Response
    {
        $homeImage->setIsActive(!$homeImage->getIsActive());
        $entityManager->flush();

        $status = $homeImage->getIsActive() ? 'activée' : 'désactivée';
        $this->addFlash('success', "Image {$status} avec succès !");

        return $this->redirectToRoute('dashboard_home_images_index');
    }

    #[Route('/{id}', name: 'dashboard_home_images_delete', methods: ['POST'])]
    public function delete(Request $request, HomeImage $homeImage, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$homeImage->getId(), $request->request->get('_token'))) {
            $entityManager->remove($homeImage);
            $entityManager->flush();
            
            $this->addFlash('success', 'Image supprimée avec succès !');
        }

        return $this->redirectToRoute('dashboard_home_images_index', [], Response::HTTP_SEE_OTHER);
    }
}
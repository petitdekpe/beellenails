<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>

namespace App\Controller;

use App\Entity\CategoryPrestation;
use App\Repository\PrestationRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class PrestationApiController extends AbstractController
{
    #[Route('/api/prestations/by-category/{categoryId}', name: 'api_prestations_by_category', methods: ['GET'])]
    public function getPrestationsByCategory(
        int $categoryId,
        PrestationRepository $prestationRepository
    ): JsonResponse {
        try {
            $prestations = $prestationRepository->findBy([
                'categoryPrestation' => $categoryId
            ], ['title' => 'ASC']);

            $prestationsData = [];
            foreach ($prestations as $prestation) {
                $prestationsData[] = [
                    'id' => $prestation->getId(),
                    'title' => $prestation->getTitle(),
                    'price' => $prestation->getPrice()
                ];
            }

            return new JsonResponse([
                'success' => true,
                'prestations' => $prestationsData
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Erreur lors du chargement des prestations'
            ], 500);
        }
    }

    #[Route('/api/prestations/all-by-categories', name: 'api_all_prestations_by_categories', methods: ['GET'])]
    public function getAllPrestationsByCategories(
        PrestationRepository $prestationRepository
    ): JsonResponse {
        try {
            $prestations = $prestationRepository->findAllGroupedByCategory();

            $groupedData = [];
            foreach ($prestations as $prestation) {
                $categoryId = $prestation->getCategoryPrestation()->getId();
                if (!isset($groupedData[$categoryId])) {
                    $groupedData[$categoryId] = [];
                }
                $groupedData[$categoryId][] = [
                    'id' => $prestation->getId(),
                    'title' => $prestation->getTitle(),
                    'price' => $prestation->getPrice()
                ];
            }

            return new JsonResponse([
                'success' => true,
                'prestationsByCategory' => $groupedData
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Erreur lors du chargement des prestations'
            ], 500);
        }
    }
}
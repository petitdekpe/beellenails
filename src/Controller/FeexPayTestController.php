<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <jy.ahouanvoedo@gmail.com>


namespace App\Controller;

use App\Service\FeexpayService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class FeexPayTestController extends AbstractController
{
    #[Route('/feexpay/test/{phone}/{operator}', name: 'feexpay_test')]
    public function testPaiement(
        string $phone,
        string $operator,
        FeexpayService $feexpayService
    ): Response {
        $fullname = 'Jean Testeur';
        $email = 'test@example.com';
        $customId = 'test_' . uniqid();

        // Montant fixe (5000 FCFA)
        $result = $feexpayService->paiementLocal(
            5000,
            $phone,
            $operator,
            $fullname,
            $email,
            $customId
        );

        return $this->json([
            'env_mode' => $feexpayService->getMode(),
            'phone' => $phone,
            'operator' => $operator,
            'response' => $result,
        ]);
    }
}

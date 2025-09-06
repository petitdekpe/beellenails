<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>


namespace App\Controller;

use App\Service\FeexpayService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class NewPaymentController extends AbstractController
{
    private $feexpayService;

    public function __construct(FeexpayService $feexpayService)
    {
        $this->feexpayService = $feexpayService;
    }

    #[Route('/pay', name: 'payment')]
    public function pay(Request $request): JsonResponse
    {
        $amount = 50; // Exemple de montant
        $phoneNumber = "22996693363";
        $operatorName = "MTN";
        $fullname = "Jon Doe";
        $email = "jondoe@gmail.com";
        $callback_info = "petitdekpe";
        $custom_id = "1";
        $otp = ""; // Facultatif

        $response = $this->feexpayService->paiementLocal($amount, $phoneNumber, $operatorName, $fullname, $email, $callback_info, $custom_id, $otp);
        // Vérification du statut du paiement
        $status = $this->feexpayService->getPaiementStatus($response);

        // Retourner le statut du paiement
        return new JsonResponse([
            'response' => $response,
            'status' => $status
        ]);
    }

    #[Route('/webhook', name: 'feexpay_webhook', methods: ['POST'])]
    public function handleWebhook(Request $request): JsonResponse
    {
        // Récupérer le contenu JSON de la requête
        $data = json_decode($request->getContent(), true);

        // Vérifier que les données JSON sont bien présentes
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new JsonResponse(['status' => 'error', 'message' => 'Invalid JSON'], 400);
        }

        // Extraire les informations du payload
        $transactionId = $data['reference'] ?? 'unknown';
        $amount = $data['amount'] ?? 'unknown';
        $status = $data['status'] ?? 'unknown';
        $callbackInfo = $data['callback_info'] ?? 'unknown';

        // Obtenir l'heure actuelle
        $currentDateTime = new \DateTime();
        $timestamp = $currentDateTime->format('Y-m-d H:i:s');

        // Enregistrer les informations dans un fichier texte
        $logEntry = sprintf(
            "[%s] Transaction ID: %s\nAmount: %s\nStatus: %s\nCallback Info: %s\n\n",
            $timestamp,
            $transactionId,
            $amount,
            $status,
            $callbackInfo
        );
        file_put_contents('public\assets\test.txt', $logEntry, FILE_APPEND);
        

        // Retourner une réponse JSON indiquant le succès
        return new JsonResponse(['status' => 'success']);
    }
}
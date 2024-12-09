<?php

namespace App\Controller;

use App\Service\FeexpayService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PaymentFeexController extends AbstractController
{
    private $feexPayService;

    public function __construct(FeexpayService $feexPayService)
    {
        $this->feexPayService = $feexPayService;
    }

    #[Route('/payment/mobile', name: 'payment_mobile')]
    public function payMobile(): Response
    {
        $response = $this->feexPayService->paiementLocal(
            100,                // Montant
            '22990765870',       // Numéro de téléphone
            'MTN',               // Réseau autorisé
            'Jon Doe',           // Nom complet du client
            'jondoe@gmail.com',   // Email du client
            'callback_info',     // Informations de callback
            'custom_id',         // Identifiant personnalisé
            '1234'               // OTP (optionnel)
        );

        return $this->json($response);
    }

}


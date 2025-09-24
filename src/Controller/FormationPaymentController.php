<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>

namespace App\Controller;

use App\Entity\Formation;
use App\Entity\User;
use App\Service\FormationPaymentService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Psr\Log\LoggerInterface;

#[Route('/formation/payment')]
class FormationPaymentController extends AbstractController
{
    public function __construct(
        private FormationPaymentService $paymentService,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {}

    #[Route('/initiate/{id}', name: 'app_formation_payment_initiate', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function initiatePayment(Formation $formation, Request $request): JsonResponse
    {
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);
        
        $provider = $data['provider'] ?? '';
        $accessType = $data['access_type'] ?? '30_days';
        
        if (!in_array($provider, ['fedapay', 'feexpay', 'celtiis'])) {
            return $this->json(['error' => 'Provider non supporté'], 400);
        }

        try {
            $paymentUrl = $this->createPaymentSession($formation, $user, $provider, $accessType);
            
            return $this->json([
                'success' => true,
                'payment_url' => $paymentUrl,
                'provider' => $provider
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Payment initiation failed', [
                'formation_id' => $formation->getId(),
                'user_id' => $user->getId(),
                'provider' => $provider,
                'error' => $e->getMessage()
            ]);
            
            return $this->json(['error' => 'Erreur lors de l\'initialisation du paiement'], 500);
        }
    }

    private function createPaymentSession(Formation $formation, User $user, string $provider, string $accessType): string
    {
        $amount = $formation->getPrice();
        $currency = 'XOF'; // Franc CFA
        
        $description = sprintf(
            'Formation: %s - Accès %s - Utilisateur: %s',
            $formation->getNom(),
            $accessType,
            $user->getEmail()
        );

        $successUrl = $this->generateUrl('app_formation_payment_success', [
            'formation' => $formation->getId(),
            'access_type' => $accessType
        ], true);

        $cancelUrl = $this->generateUrl('app_formation_payment_cancel', [
            'formation' => $formation->getId()
        ], true);

        return match($provider) {
            'fedapay' => $this->createFedapaySession($formation, $user, $amount, $currency, $description, $successUrl, $cancelUrl),
            'feexpay' => $this->createFeexpaySession($formation, $user, $amount, $currency, $description, $successUrl, $cancelUrl),
            'celtiis' => $this->createCeltiisSession($formation, $user, $amount, $currency, $description, $successUrl, $cancelUrl),
            default => throw new \Exception('Provider non supporté')
        };
    }

    private function createFedapaySession(Formation $formation, User $user, float $amount, string $currency, string $description, string $successUrl, string $cancelUrl): string
    {
        // Configuration FedaPay
        $apiKey = $_ENV['FEDAPAY_PRIVATE_KEY'] ?? '';
        $webhookUrl = $this->generateUrl('app_formation_payment_webhook_fedapay', [], true);
        
        $data = [
            'amount' => $amount,
            'currency' => ['iso' => $currency],
            'description' => $description,
            'callback_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'webhook_url' => $webhookUrl,
            'customer' => [
                'firstname' => $user->getPrenom(),
                'lastname' => $user->getNom(),
                'email' => $user->getEmail(),
                'phone_number' => $user->getTelephone()
            ],
            'custom_metadata' => [
                'formation_id' => $formation->getId(),
                'user_id' => $user->getId(),
                'access_type' => 'formation_access'
            ]
        ];

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://api.fedapay.com/v1/transactions',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json'
            ]
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($httpCode !== 200) {
            throw new \Exception('Erreur FedaPay: ' . $response);
        }

        $result = json_decode($response, true);
        return $result['v1/transaction']['klass'] ?? '';
    }

    private function createFeexpaySession(Formation $formation, User $user, float $amount, string $currency, string $description, string $successUrl, string $cancelUrl): string
    {
        // Configuration Feexpay
        $apiKey = $_ENV['FEEXPAY_API_KEY'] ?? '';
        $shopId = $_ENV['FEEXPAY_SHOP_ID'] ?? '';
        $webhookUrl = $this->generateUrl('app_formation_payment_webhook_feexpay', [], true);
        
        $data = [
            'shop_id' => $shopId,
            'amount' => $amount,
            'currency' => $currency,
            'description' => $description,
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'webhook_url' => $webhookUrl,
            'customer_name' => $user->getPrenom() . ' ' . $user->getNom(),
            'customer_email' => $user->getEmail(),
            'customer_phone' => $user->getTelephone(),
            'metadata' => json_encode([
                'formation_id' => $formation->getId(),
                'user_id' => $user->getId(),
                'access_type' => 'formation_access'
            ])
        ];

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://api.feexpay.me/api/v1/payments/initialize',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json'
            ]
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($httpCode !== 200) {
            throw new \Exception('Erreur Feexpay: ' . $response);
        }

        $result = json_decode($response, true);
        return $result['data']['payment_url'] ?? '';
    }

    private function createCeltiisSession(Formation $formation, User $user, float $amount, string $currency, string $description, string $successUrl, string $cancelUrl): string
    {
        // Configuration Celtiis
        $apiKey = $_ENV['CELTIIS_API_KEY'] ?? '';
        $merchantId = $_ENV['CELTIIS_MERCHANT_ID'] ?? '';
        $webhookUrl = $this->generateUrl('app_formation_payment_webhook_celtiis', [], true);
        
        $data = [
            'merchant_id' => $merchantId,
            'amount' => $amount,
            'currency' => $currency,
            'description' => $description,
            'return_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'notify_url' => $webhookUrl,
            'customer' => [
                'name' => $user->getPrenom() . ' ' . $user->getNom(),
                'email' => $user->getEmail(),
                'phone' => $user->getTelephone()
            ],
            'custom_data' => [
                'formation_id' => $formation->getId(),
                'user_id' => $user->getId(),
                'access_type' => 'formation_access'
            ]
        ];

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://api.celtiis.com/v1/payments',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json'
            ]
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($httpCode !== 200) {
            throw new \Exception('Erreur Celtiis: ' . $response);
        }

        $result = json_decode($response, true);
        return $result['data']['checkout_url'] ?? '';
    }

    #[Route('/enroll-free/{id}', name: 'app_formation_enroll_free', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function enrollForFree(Formation $formation): JsonResponse
    {
        $user = $this->getUser();
        
        if (!$formation->isFree()) {
            return $this->json(['error' => 'Cette formation n\'est pas gratuite'], 400);
        }

        try {
            $enrollment = $this->paymentService->createFormationEnrollment($user, $formation, 'lifetime');
            
            return $this->json([
                'success' => true,
                'message' => 'Inscription réussie !',
                'redirect_url' => $this->generateUrl('app_user_learning_formation_detail', ['id' => $enrollment->getId()])
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Free enrollment failed', [
                'formation_id' => $formation->getId(),
                'user_id' => $user->getId(),
                'error' => $e->getMessage()
            ]);
            
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/success/{formation}/{access_type}', name: 'app_formation_payment_success')]
    public function paymentSuccess(Formation $formation, string $accessType, Request $request): Response
    {
        return $this->render('formation/payment_success.html.twig', [
            'formation' => $formation,
            'access_type' => $accessType,
            'transaction_id' => $request->query->get('transaction_id')
        ]);
    }

    #[Route('/cancel/{formation}', name: 'app_formation_payment_cancel')]
    public function paymentCancel(Formation $formation): Response
    {
        return $this->render('formation/payment_cancel.html.twig', [
            'formation' => $formation
        ]);
    }

    #[Route('/webhook/fedapay', name: 'app_formation_payment_webhook_fedapay', methods: ['POST'])]
    public function fedapayWebhook(Request $request): JsonResponse
    {
        try {
            $result = $this->paymentService->processFedapayWebhook($request);
            return $this->json($result);
        } catch (\Exception $e) {
            $this->logger->error('FedaPay webhook error', ['error' => $e->getMessage()]);
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/webhook/feexpay', name: 'app_formation_payment_webhook_feexpay', methods: ['POST'])]
    public function feexpayWebhook(Request $request): JsonResponse
    {
        try {
            $result = $this->paymentService->processFeexpayWebhook($request);
            return $this->json($result);
        } catch (\Exception $e) {
            $this->logger->error('Feexpay webhook error', ['error' => $e->getMessage()]);
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/webhook/celtiis', name: 'app_formation_payment_webhook_celtiis', methods: ['POST'])]
    public function celtiisWebhook(Request $request): JsonResponse
    {
        try {
            $result = $this->paymentService->processCeltiisWebhook($request);
            return $this->json($result);
        } catch (\Exception $e) {
            $this->logger->error('Celtiis webhook error', ['error' => $e->getMessage()]);
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }
}
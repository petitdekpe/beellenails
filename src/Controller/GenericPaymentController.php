<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>

namespace App\Controller;

use App\Entity\Payment;
use App\Entity\User;
use App\Form\FeexPayFormType;
use App\Interface\PayableEntityInterface;
use App\Service\FedapayService;
use App\Service\FeexpayService;
use App\Service\PaymentTypeResolver;
use App\Service\PromoCodeService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class GenericPaymentController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PaymentTypeResolver $paymentTypeResolver,
        private readonly FedapayService $fedapayService,
        private readonly FeexpayService $feexpayService,
        private readonly LoggerInterface $logger
    ) {}

    #[Route('/payment/{provider}/{paymentType}/{entityType}/{entityId}', name: 'generic_payment_init', methods: ['GET', 'POST'])]
    public function initPayment(
        string $provider,
        string $paymentType,
        string $entityType,
        int $entityId,
        Request $request,
        ?User $user = null
    ): Response {
        // Validate provider
        if (!in_array($provider, ['fedapay', 'feexpay'])) {
            throw $this->createNotFoundException("Provider non supporté: {$provider}");
        }

        // Validate payment type and entity type combination
        if (!$this->paymentTypeResolver->validatePaymentTypeForEntity($paymentType, $entityType)) {
            throw $this->createNotFoundException("Combinaison type de paiement/entité non valide");
        }

        // Resolve entity
        $entity = $this->paymentTypeResolver->resolveEntityByTypeAndId($entityType, $entityId);
        
        // Get user for payment
        $paymentUser = $this->paymentTypeResolver->getUserForPayment($entity, $user);
        
        // Get payment amount (handle access_type for formations)
        $amount = $this->paymentTypeResolver->getPaymentAmount($paymentType, $entity);

        // For formations, handle different access types
        $accessType = null;
        if ($entityType === 'formation' && $entity instanceof \App\Entity\Formation) {
            $accessType = $request->query->get('access_type', '30_days');
            $amount = $this->calculateFormationPrice($entity, $accessType);

            // Update description to include configured access info
            if ($entity->getAccessType() === 'relative' && $entity->getAccessDuration()) {
                $description .= " - Accès " . $entity->getAccessDuration() . " jours";
            } elseif ($entity->getAccessType() === 'fixed') {
                $description .= " - Session fixe";
            } else {
                $description .= " - Accès illimité";
            }
        }

        if ($amount <= 0) {
            throw $this->createNotFoundException("Montant de paiement non configuré pour le type: {$paymentType}");
        }

        // Get payment description
        $description = $this->paymentTypeResolver->getPaymentDescription($paymentType, $entity);

        $this->logger->info('[Generic Payment Init] Initiating payment', [
            'provider' => $provider,
            'payment_type' => $paymentType,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'amount' => $amount,
            'user_email' => $paymentUser->getEmail(),
            'description' => $description
        ]);

        // Route to specific provider handler
        return match($provider) {
            'fedapay' => $this->handleFedaPayInit($paymentType, $entity, $paymentUser, $amount, $description),
            'feexpay' => $this->handleFeexPayInit($request, $paymentType, $entity, $paymentUser, $amount, $description),
            default => throw $this->createNotFoundException("Provider non implémenté: {$provider}")
        };
    }

    private function handleFedaPayInit(
        string $paymentType,
        PayableEntityInterface $entity,
        User $user,
        int $amount,
        string $description
    ): Response {
        try {
            // Initialize FedaPay transaction
            $transaction = $this->fedapayService->initTransaction($amount, $description, $user);
            $token = $this->fedapayService->generateToken();

            // Create payment record
            $payment = new Payment();
            $payment->parseTransaction($transaction)
                ->setCustomer($user)
                ->setPhoneNumber($user->getPhone() ?? '')
                ->setToken($token->token)
                ->setProvider('fedapay')
                ->setPaymentType($paymentType)
                ->setEntityType($entity->getEntityType())
                ->setEntityId($entity->getId());

            // Link to specific entity if needed
            if (method_exists($payment, 'setRendezvous') && $entity->getEntityType() === 'rendezvous') {
                $payment->setRendezvous($entity);
            }

            $this->entityManager->persist($user);
            $this->entityManager->persist($payment);
            $this->entityManager->flush();

            $this->logger->info('[Generic Payment FedaPay] Payment initialized', [
                'payment_id' => $payment->getId(),
                'transaction_id' => $transaction->id,
                'token_url' => $token->url
            ]);

            return $this->render('payment/redirect.html.twig', [
                'redirect_url' => $token->url,
                'payment' => $payment,
                'entity' => $entity
            ]);

        } catch (\Exception $e) {
            $this->logger->error('[Generic Payment FedaPay] Error during initialization', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->render('payment/error.html.twig', [
                'error' => 'Erreur lors de l\'initialisation du paiement FedaPay',
                'entity' => $entity
            ]);
        }
    }

    private function handleFeexPayInit(
        Request $request,
        string $paymentType,
        PayableEntityInterface $entity,
        User $user,
        int $amount,
        string $description
    ): Response {
        $form = $this->createForm(FeexPayFormType::class);

        if ($request->isMethod('GET')) {
            return $this->render('payment/feexpay_form.html.twig', [
                'form' => $form->createView(),
                'entity' => $entity,
                'amount' => $amount,
                'description' => $description,
                'user' => $user,
                'error' => null
            ]);
        }

        // Handle POST request
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->render('payment/feexpay_form.html.twig', [
                'form' => $form->createView(),
                'entity' => $entity,
                'amount' => $amount,
                'description' => $description,
                'user' => $user,
                'error' => 'Formulaire invalide'
            ]);
        }

        try {
            $data = $form->getData();
            $reference = $paymentType . '_' . $entity->getEntityType() . '_' . $entity->getId() . '_' . time();

            // Call FeexPay API
            $response = $this->feexpayService->paiementLocal(
                $amount,
                $data['phone'],
                $data['operator'],
                $user->__toString(),
                $user->getEmail(),
                $reference
            );

            $this->logger->info('[Generic Payment FeexPay] API response', [
                'reference' => $reference,
                'response' => $response
            ]);

            if (!isset($response['reference'])) {
                throw new \Exception($response['message'] ?? 'Erreur FeexPay inconnue');
            }

            // Create payment record
            $payment = new Payment();
            $payment->setTransactionID($response['reference'])
                ->setReference($response['reference'])
                ->setPhoneNumber($data['phone'])
                ->setCustomer($user)
                ->setAmount($amount)
                ->setCurrency('XOF')
                ->setStatus('pending')
                ->setMode($this->feexpayService->getMode())
                ->setProvider('feexpay')
                ->setPaymentType($paymentType)
                ->setEntityType($entity->getEntityType())
                ->setEntityId($entity->getId())
                ->setDescription($description)
                ->setCreatedAt(new \DateTimeImmutable());

            // Link to specific entity if needed
            if (method_exists($payment, 'setRendezvous') && $entity->getEntityType() === 'rendezvous') {
                $payment->setRendezvous($entity);
            }

            $this->entityManager->persist($payment);
            $this->entityManager->flush();

            $this->logger->info('[Generic Payment FeexPay] Payment initialized', [
                'payment_id' => $payment->getId(),
                'reference' => $response['reference']
            ]);

            return $this->redirectToRoute('generic_payment_pending', [
                'reference' => $response['reference']
            ]);

        } catch (\Exception $e) {
            $this->logger->error('[Generic Payment FeexPay] Error during initialization', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->render('payment/feexpay_form.html.twig', [
                'form' => $form->createView(),
                'entity' => $entity,
                'amount' => $amount,
                'description' => $description,
                'user' => $user,
                'error' => $e->getMessage()
            ]);
        }
    }

    #[Route('/payment/pending/{reference}', name: 'generic_payment_pending')]
    public function pendingPayment(string $reference): Response
    {
        $payment = $this->entityManager->getRepository(Payment::class)
            ->findOneBy(['reference' => $reference]);

        if (!$payment) {
            throw $this->createNotFoundException('Paiement introuvable');
        }

        $entity = $this->paymentTypeResolver->resolveEntity($payment);

        return $this->render('payment/pending.html.twig', [
            'payment' => $payment,
            'entity' => $entity,
            'reference' => $reference
        ]);
    }

    #[Route('/payment/success/{reference}', name: 'generic_payment_success')]
    public function successPayment(string $reference): Response
    {
        $payment = $this->entityManager->getRepository(Payment::class)
            ->findOneBy(['reference' => $reference]);

        if (!$payment) {
            throw $this->createNotFoundException('Paiement introuvable');
        }

        $entity = $this->paymentTypeResolver->resolveEntity($payment);

        return $this->render('payment/success.html.twig', [
            'payment' => $payment,
            'entity' => $entity
        ]);
    }

    #[Route('/payment/error/{reference}', name: 'generic_payment_error')]
    public function errorPayment(string $reference): Response
    {
        $payment = $this->entityManager->getRepository(Payment::class)
            ->findOneBy(['reference' => $reference]);

        if (!$payment) {
            throw $this->createNotFoundException('Paiement introuvable');
        }

        $entity = $this->paymentTypeResolver->resolveEntity($payment);

        return $this->render('payment/error.html.twig', [
            'payment' => $payment,
            'entity' => $entity,
            'error' => null
        ]);
    }

    #[Route('/payment/callback', name: 'generic_payment_callback')]
    public function paymentCallback(Request $request): Response
    {
        $transactionID = $request->get('id');
        $status = $request->get('status');

        $this->logger->info('[Generic Payment Callback] Callback reçu', [
            'transaction_id' => $transactionID,
            'status' => $status,
            'query_params' => $request->query->all()
        ]);

        if (!$transactionID) {
            $this->logger->error('[Generic Payment Callback] Transaction ID manquant');
            return $this->render('payment/error.html.twig', [
                'error' => 'Transaction ID manquant'
            ]);
        }

        try {
            // Find payment by transaction ID
            $payment = $this->entityManager->getRepository(Payment::class)
                ->findOneBy(['transactionID' => $transactionID]);

            if (!$payment) {
                $this->logger->error('[Generic Payment Callback] Paiement introuvable', [
                    'transaction_id' => $transactionID
                ]);
                throw $this->createNotFoundException('Paiement introuvable');
            }

            // Get fresh transaction data from FedaPay
            $transaction = $this->fedapayService->getTransaction($transactionID);
            
            // Update payment with fresh data
            $oldStatus = $payment->getStatus();
            $payment->parseTransaction($transaction);
            
            // Resolve entity
            $entity = $this->paymentTypeResolver->resolveEntity($payment);

            // Handle status change
            $newStatus = $payment->getStatus();
            if ($oldStatus !== $newStatus) {
                $this->logger->info('[Generic Payment Callback] Changement de statut', [
                    'payment_id' => $payment->getId(),
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus
                ]);

                // Process status change
                if (in_array($newStatus, ['approved', 'successful'])) {
                    $entity->onPaymentSuccess();
                    $this->entityManager->flush();
                    
                    return $this->redirectToRoute('generic_payment_success', [
                        'reference' => $payment->getReference()
                    ]);
                } else if (in_array($newStatus, ['declined', 'failed'])) {
                    $entity->onPaymentFailure();
                    $this->entityManager->flush();
                    
                    return $this->redirectToRoute('generic_payment_error', [
                        'reference' => $payment->getReference()
                    ]);
                }
            }

            $this->entityManager->flush();

            // Redirect based on status
            return match($status) {
                'approved', 'successful' => $this->redirectToRoute('generic_payment_success', [
                    'reference' => $payment->getReference()
                ]),
                default => $this->redirectToRoute('generic_payment_error', [
                    'reference' => $payment->getReference()
                ])
            };

        } catch (\Exception $e) {
            $this->logger->error('[Generic Payment Callback] Erreur lors du traitement', [
                'transaction_id' => $transactionID,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->render('payment/error.html.twig', [
                'error' => 'Erreur lors du traitement du callback'
            ]);
        }
    }

    #[Route('/payment/status/{reference}', name: 'generic_payment_status', methods: ['GET'])]
    public function checkStatus(string $reference, Request $request): JsonResponse
    {
        $payment = $this->entityManager->getRepository(Payment::class)
            ->findOneBy(['reference' => $reference]);

        if (!$payment) {
            return $this->json(['error' => 'Payment not found'], 404);
        }

        $forceApiCheck = $request->query->getBoolean('force_api', false);
        
        // Si force_api=true ET le paiement est encore pending ET c'est FeexPay
        if ($forceApiCheck && 
            $payment->getStatus() === 'pending' && 
            $payment->getProvider() === 'feexpay') {
            
            $this->logger->info('[Generic Payment] Force API check requested', [
                'reference' => $reference,
                'current_status' => $payment->getStatus()
            ]);
            
            // Appel API FeexPay pour vérifier le vrai statut
            try {
                $apiStatus = $this->checkFeexPayApiStatus($payment);
                
                if ($apiStatus && $apiStatus !== 'pending') {
                    $this->updatePaymentFromApiStatus($payment, $apiStatus);
                    
                    $this->logger->info('[Generic Payment] Status updated from API', [
                        'reference' => $reference,
                        'old_status' => 'pending',
                        'new_status' => $payment->getStatus()
                    ]);
                }
            } catch (\Exception $e) {
                $this->logger->error('[Generic Payment] API check failed', [
                    'reference' => $reference,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $this->json([
            'status' => $payment->getStatus(),
            'check_method' => $forceApiCheck ? 'api' : 'webhook',
            'payment_status' => $payment->getStatus(),
            'reference' => $reference,
            'provider' => $payment->getProvider()
        ]);
    }

    #[Route('/api/payment/status/{reference}', name: 'api_generic_payment_status', methods: ['GET'])]
    public function apiPaymentStatus(string $reference): Response
    {
        $payment = $this->entityManager->getRepository(Payment::class)
            ->findOneBy(['reference' => $reference]);

        if (!$payment) {
            return $this->json(['error' => 'Paiement introuvable'], 404);
        }

        $entity = null;
        try {
            $entity = $this->paymentTypeResolver->resolveEntity($payment);
        } catch (\Exception $e) {
            // Entity might not be resolvable, continue without it
        }

        return $this->json([
            'reference' => $reference,
            'status' => $payment->getStatus(),
            'amount' => $payment->getAmount(),
            'currency' => $payment->getCurrency(),
            'provider' => $payment->getProvider(),
            'payment_type' => $payment->getPaymentType(),
            'entity_type' => $payment->getEntityType(),
            'entity_id' => $payment->getEntityId(),
            'updated_at' => $payment->getUpdatedAt()?->format('Y-m-d H:i:s'),
            'is_successful' => in_array($payment->getStatus(), ['approved', 'successful']),
            'entity_status' => $entity ? ($entity->getEntityType() === 'rendezvous' && method_exists($entity, 'isPaid') ? $entity->isPaid() : null) : null
        ]);
    }

    private function checkFeexPayApiStatus(Payment $payment): ?string
    {
        try {
            // Appel à l'API FeexPay pour vérifier le statut
            $response = $this->feexpayService->getPaiementStatus($payment->getReference());
            
            $this->logger->info('[Generic Payment] FeexPay API Status Check', [
                'reference' => $payment->getReference(),
                'api_response' => $response
            ]);
            
            // Vérifier si la réponse indique une erreur d'API
            if (isset($response['error']) && $response['error'] === true) {
                throw new \Exception($response['message'] ?? 'Erreur API FeexPay');
            }
            
            // Mapper les statuts FeexPay vers nos statuts internes
            // FeexPay peut retourner: status, state, ou autres champs
            $apiStatus = $response['status'] ?? $response['state'] ?? $response['transaction_status'] ?? null;
            
            if ($apiStatus) {
                return match(strtolower($apiStatus)) {
                    'success', 'successful', 'completed', 'approved', 'paid' => 'successful',
                    'failed', 'declined', 'cancelled', 'error', 'rejected' => 'failed',
                    'pending', 'processing', 'waiting' => 'pending',
                    default => 'pending'
                };
            }
            
            return null;
            
        } catch (\Exception $e) {
            $this->logger->error('[Generic Payment] FeexPay API Status Check Error', [
                'reference' => $payment->getReference(),
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    private function updatePaymentFromApiStatus(Payment $payment, string $apiStatus): void
    {
        $payment->setStatus($apiStatus);
        $payment->setUpdatedAt(new \DateTimeImmutable());
        
        // Mettre à jour l'entité liée si le paiement est réussi
        if ($apiStatus === 'successful') {
            try {
                $entity = $this->paymentTypeResolver->resolveEntity($payment);
                if ($entity && method_exists($entity, 'onPaymentSuccess')) {
                    $entity->onPaymentSuccess();
                    $this->entityManager->persist($entity);
                }
            } catch (\Exception $e) {
                $this->logger->error('[Generic Payment] Error updating entity on payment success', [
                    'reference' => $payment->getReference(),
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        $this->entityManager->persist($payment);
        $this->entityManager->flush();
    }

    private function calculateFormationPrice(\App\Entity\Formation $formation, string $accessType): int
    {
        $basePrice = $formation->getCout() ?? 0;

        // Use the configured price from the formation regardless of access type
        // Access type is now configured in the formation itself, not chosen by user
        return $basePrice;
    }
}
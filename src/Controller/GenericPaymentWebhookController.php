<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>

namespace App\Controller;

use App\Entity\Payment;
use App\Service\PaymentTypeResolver;
use App\Service\PromoCodeService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;

class GenericPaymentWebhookController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PaymentTypeResolver $paymentTypeResolver,
        private readonly LoggerInterface $logger,
        private readonly MailerInterface $mailer,
        private readonly PromoCodeService $promoCodeService
    ) {}

    #[Route('/webhook/payment/{provider}', name: 'generic_payment_webhook', methods: ['POST'])]
    public function handleWebhook(string $provider, Request $request): JsonResponse
    {
        try {
            // Validate provider
            if (!in_array($provider, ['fedapay', 'feexpay'])) {
                $this->logger->error('[Generic Webhook] Provider non supporté', ['provider' => $provider]);
                return new JsonResponse(['error' => 'Provider non supporté'], Response::HTTP_BAD_REQUEST);
            }

            // Parse payload according to provider
            $payload = $this->parsePayload($request, $provider);
            
            if (!$payload) {
                $this->logger->error('[Generic Webhook] Payload invalide', ['provider' => $provider]);
                return new JsonResponse(['error' => 'Payload invalide'], Response::HTTP_BAD_REQUEST);
            }

            $this->logger->info('[Generic Webhook] Payload reçu', [
                'provider' => $provider,
                'payload' => $payload
            ]);

            // Find payment
            $payment = $this->findPaymentByReference($payload['reference']);
            
            if (!$payment) {
                $this->logger->warning("[Generic Webhook] Paiement introuvable", [
                    'provider' => $provider,
                    'reference' => $payload['reference']
                ]);
                return new JsonResponse(['error' => 'Payment not found'], Response::HTTP_NOT_FOUND);
            }

            // Update payment from payload
            $oldStatus = $payment->getStatus();
            $this->updatePaymentFromPayload($payment, $payload, $provider);

            // Resolve entity
            $entity = null;
            try {
                $entity = $this->paymentTypeResolver->resolveEntity($payment);
            } catch (\Exception $e) {
                $this->logger->warning("[Generic Webhook] Impossible de résoudre l'entité", [
                    'provider' => $provider,
                    'payment_id' => $payment->getId(),
                    'error' => $e->getMessage()
                ]);
            }

            // Process payment status
            $newStatus = $payment->getStatus();
            if ($oldStatus !== $newStatus) {
                $this->processPaymentStatusChange($payment, $entity, $oldStatus, $newStatus);
            }

            // Save changes
            $this->entityManager->flush();

            $this->logger->info('[Generic Webhook] Traitement terminé', [
                'provider' => $provider,
                'reference' => $payload['reference'],
                'old_status' => $oldStatus,
                'new_status' => $newStatus
            ]);

            return new JsonResponse([
                'success' => true,
                'reference' => $payload['reference'],
                'old_status' => $oldStatus,
                'new_status' => $newStatus
            ]);

        } catch (\Exception $e) {
            $this->logger->error('[Generic Webhook] Erreur lors du traitement', [
                'provider' => $provider,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return new JsonResponse([
                'error' => 'Internal server error'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function parsePayload(Request $request, string $provider): ?array
    {
        $content = json_decode($request->getContent(), true);
        
        if (!$content) {
            return null;
        }

        return match($provider) {
            'feexpay' => $this->parseFeexPayPayload($content),
            'fedapay' => $this->parseFedaPayPayload($content),
            default => null
        };
    }

    private function parseFeexPayPayload(array $content): ?array
    {
        if (!isset($content['reference']) || !isset($content['status'])) {
            return null;
        }

        return [
            'reference' => $content['reference'],
            'status' => $content['status'],
            'amount' => $content['amount'] ?? null,
            'raw_data' => $content
        ];
    }

    private function parseFedaPayPayload(array $content): ?array
    {
        // FedaPay webhook structure
        if (!isset($content['entity']['reference']) || !isset($content['entity']['status'])) {
            return null;
        }

        return [
            'reference' => $content['entity']['reference'],
            'status' => $content['entity']['status'],
            'amount' => $content['entity']['amount'] ?? null,
            'raw_data' => $content
        ];
    }

    private function findPaymentByReference(string $reference): ?Payment
    {
        return $this->entityManager->getRepository(Payment::class)
            ->findOneBy(['reference' => $reference]);
    }

    private function updatePaymentFromPayload(Payment $payment, array $payload, string $provider): void
    {
        // Convert status according to provider
        $internalStatus = match($provider) {
            'feexpay' => Payment::convertFeexStatus($payload['status']),
            'fedapay' => $payload['status'] // FedaPay uses compatible statuses
        };

        $payment->setStatus($internalStatus)->setUpdatedAt(new \DateTime());

        // Update amount if provided
        if ($payload['amount'] !== null && is_numeric($payload['amount'])) {
            $payment->setAmount((int)$payload['amount']);
            $this->logger->info('[Generic Webhook] Montant mis à jour', [
                'reference' => $payload['reference'],
                'amount' => $payload['amount']
            ]);
        }
    }

    private function processPaymentStatusChange(Payment $payment, $entity, string $oldStatus, string $newStatus): void
    {
        $this->logger->info('[Generic Webhook] Changement de statut détecté', [
            'payment_id' => $payment->getId(),
            'entity_type' => $payment->getEntityType(),
            'entity_id' => $payment->getEntityId(),
            'old_status' => $oldStatus,
            'new_status' => $newStatus
        ]);

        // Process according to new status
        switch ($newStatus) {
            case 'successful':
            case 'approved':
                if ($oldStatus !== $newStatus) {
                    $this->handlePaymentSuccess($payment, $entity);
                }
                break;

            case 'failed':
            case 'declined':
                $this->handlePaymentFailure($payment, $entity);
                break;

            case 'canceled':
                $this->handlePaymentCancellation($payment, $entity);
                break;
        }
    }

    private function handlePaymentSuccess(Payment $payment, $entity): void
    {
        $this->logger->info('[Generic Webhook] Traitement du paiement réussi', [
            'payment_id' => $payment->getId()
        ]);

        // Call entity success handler
        if ($entity) {
            $entity->onPaymentSuccess();

            // Handle promo codes for rendezvous
            if ($payment->getEntityType() === 'rendezvous' && method_exists($entity, 'getPendingPromoCode')) {
                if ($entity->getPendingPromoCode()) {
                    $result = $this->promoCodeService->applyPendingPromoCode($entity);
                    $this->logger->info('[Generic Webhook] Code promo traité', [
                        'entity_id' => $entity->getId(),
                        'result' => $result['isValid'] ? 'appliqué' : 'échoué',
                        'message' => $result['message']
                    ]);
                }
            }
        }

        // Send success emails
        $this->sendSuccessEmails($payment, $entity);
    }

    private function handlePaymentFailure(Payment $payment, $entity): void
    {
        $this->logger->info('[Generic Webhook] Traitement de l\'échec du paiement', [
            'payment_id' => $payment->getId()
        ]);

        if ($entity) {
            $entity->onPaymentFailure();

            // Revoke promo codes for rendezvous
            if ($payment->getEntityType() === 'rendezvous' && method_exists($entity, 'getPromoCode')) {
                if ($entity->getPromoCode()) {
                    $result = $this->promoCodeService->revokePromoCodeUsage($entity, 'Paiement échoué');
                    $this->logger->info('[Generic Webhook] Code promo révoqué', [
                        'entity_id' => $entity->getId(),
                        'reason' => 'Paiement échoué'
                    ]);
                }
            }
        }
    }

    private function handlePaymentCancellation(Payment $payment, $entity): void
    {
        $this->logger->info('[Generic Webhook] Traitement de l\'annulation du paiement', [
            'payment_id' => $payment->getId()
        ]);

        if ($entity) {
            $entity->onPaymentCancellation();

            // Revoke promo codes for rendezvous
            if ($payment->getEntityType() === 'rendezvous' && method_exists($entity, 'getPromoCode')) {
                if ($entity->getPromoCode()) {
                    $result = $this->promoCodeService->revokePromoCodeUsage($entity, 'Paiement annulé');
                    $this->logger->info('[Generic Webhook] Code promo révoqué', [
                        'entity_id' => $entity->getId(),
                        'reason' => 'Paiement annulé'
                    ]);
                }
            }
        }
    }

    private function sendSuccessEmails(Payment $payment, $entity): void
    {
        try {
            $emailType = $payment->getEntityType();
            $user = $payment->getCustomer();

            if (!$user || !$user->getEmail()) {
                $this->logger->warning('[Generic Webhook] Pas d\'email utilisateur pour l\'envoi', [
                    'payment_id' => $payment->getId()
                ]);
                return;
            }

            // Send client email
            $clientSubject = match($emailType) {
                'rendezvous' => 'Informations de rendez-vous !',
                'formation' => 'Confirmation d\'inscription à la formation !',
                default => 'Confirmation de paiement !'
            };

            $clientTemplate = match($emailType) {
                'rendezvous' => 'emails/rendezvous_created.html.twig',
                'formation' => 'emails/formation_enrollment_confirmation.html.twig',
                default => 'emails/payment_success.html.twig'
            };

            $clientEmail = (new Email())
                ->from('beellenailscare@beellenails.com')
                ->to($user->getEmail())
                ->subject($clientSubject)
                ->html($this->renderView($clientTemplate, [
                    'payment' => $payment,
                    'entity' => $entity,
                    'user' => $user
                ]));

            $this->mailer->send($clientEmail);

            // Send admin email
            $adminSubject = match($emailType) {
                'rendezvous' => 'Nouveau Rendez-vous !',
                'formation' => 'Nouvelle inscription formation !',
                default => 'Nouveau paiement !'
            };

            $adminTemplate = match($emailType) {
                'rendezvous' => 'emails/rendezvous_created_admin.html.twig',
                'formation' => 'emails/formation_enrollment_admin.html.twig',
                default => 'emails/payment_success_admin.html.twig'
            };

            $adminEmail = (new Email())
                ->from('beellenailscare@beellenails.com')
                ->to('murielahodode@gmail.com')
                ->subject($adminSubject)
                ->html($this->renderView($adminTemplate, [
                    'payment' => $payment,
                    'entity' => $entity,
                    'user' => $user
                ]));

            $this->mailer->send($adminEmail);

            $this->logger->info('[Generic Webhook] Emails de succès envoyés', [
                'payment_id' => $payment->getId(),
                'client_email' => $user->getEmail()
            ]);

        } catch (\Exception $e) {
            $this->logger->error('[Generic Webhook] Erreur envoi emails', [
                'payment_id' => $payment->getId(),
                'error' => $e->getMessage()
            ]);
        }
    }
}
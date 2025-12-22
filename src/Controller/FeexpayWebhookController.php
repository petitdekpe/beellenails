<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>


namespace App\Controller;

use App\Entity\Payment;
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

class FeexpayWebhookController extends AbstractController
{
    #[Route('/webhook/feexpay', name: 'feexpay_webhook', methods: ['POST'])]
    public function webhook(
        Request $request,
        EntityManagerInterface $em,
        LoggerInterface $logger,
        MailerInterface $mailer
    ): JsonResponse {
        try {
            // Récupérer le payload JSON
            $payload = json_decode($request->getContent(), true);

            if (!$payload) {
                $logger->error('[FeexPay Webhook] Payload JSON invalide');
                return new JsonResponse(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
            }

            $logger->info('[FeexPay Webhook] Payload reçu', $payload);

            // Vérifier les champs requis
            if (!isset($payload['reference']) || !isset($payload['status'])) {
                $logger->error('[FeexPay Webhook] Champs requis manquants', $payload);
                return new JsonResponse(['error' => 'Missing required fields'], Response::HTTP_BAD_REQUEST);
            }

            $reference = $payload['reference'];
            $feexStatus = $payload['status'];
            $amount = $payload['amount'] ?? null;
            $callbackInfo = $payload['callback_info'] ?? null;

            // Trouver le paiement correspondant
            $payment = $em->getRepository(Payment::class)->findOneBy(['reference' => $reference]);

            if (!$payment) {
                $logger->warning("[FeexPay Webhook] Paiement introuvable pour la référence: {$reference}");
                return new JsonResponse(['error' => 'Payment not found'], Response::HTTP_NOT_FOUND);
            }

            // Convertir le statut FeexPay vers notre statut interne
            $internalStatus = Payment::convertFeexStatus($feexStatus);
            $oldStatus = $payment->getStatus();

            // Mettre à jour le paiement
            $payment->setStatus($internalStatus)->setUpdatedAt(new \DateTime());
            
            // Mettre à jour le montant si fourni dans la réponse de l'API
            if ($amount !== null && is_numeric($amount)) {
                $payment->setAmount((int)$amount);
                $logger->info("[FeexPay Webhook] Montant mis à jour", [
                    'reference' => $reference,
                    'amount' => $amount
                ]);
            }

            $rendezvous = $payment->getRendezvous();

            // Traiter selon le statut
            switch ($internalStatus) {
                case 'successful':
                    if ($oldStatus !== 'successful') { // Éviter les doublons
                        $rendezvous->setPaid(true)->setStatus('Rendez-vous pris');

                        // Appliquer le code promo en attente s'il y en a un
                        if ($rendezvous->getPendingPromoCode()) {
                            $promoCodeService = $this->container->get(PromoCodeService::class);
                            $result = $promoCodeService->applyPendingPromoCode($rendezvous);
                            $logger->info("[FeexPay Webhook] Code promo traité", [
                                'rendezvous_id' => $rendezvous->getId(),
                                'promo_result' => $result['isValid'] ? 'appliqué' : 'échoué',
                                'message' => $result['message']
                            ]);
                        }

                        // Envoi d'email au client
                        $this->sendClientSuccessEmail($rendezvous, $mailer, $logger);

                        // Envoi d'email à l'admin
                        $this->sendAdminNotificationEmail($rendezvous, $mailer, $logger);

                        $logger->info("[FeexPay Webhook] Paiement réussi - Emails envoyés pour RDV #{$rendezvous->getId()}");
                    }
                    break;

                case 'failed':
                    $rendezvous->setStatus('Échec du paiement');
                    // Révoquer le code promo si il y en a un
                    if ($rendezvous->getPromoCode()) {
                        $promoCodeService = $this->container->get(PromoCodeService::class);
                        $result = $promoCodeService->revokePromoCodeUsage($rendezvous, 'Paiement échoué');
                        $logger->info("[FeexPay Webhook] Code promo révoqué suite à l'échec", [
                            'rendezvous_id' => $rendezvous->getId(),
                            'reason' => 'Paiement échoué'
                        ]);
                    }
                    $logger->info("[FeexPay Webhook] Paiement échoué pour RDV #{$rendezvous->getId()}");
                    break;

                case 'canceled':
                    $rendezvous->setStatus('Paiement annulé');
                    // Révoquer le code promo si il y en a un
                    if ($rendezvous->getPromoCode()) {
                        $promoCodeService = $this->container->get(PromoCodeService::class);
                        $result = $promoCodeService->revokePromoCodeUsage($rendezvous, 'Paiement annulé');
                        $logger->info("[FeexPay Webhook] Code promo révoqué suite à l'annulation", [
                            'rendezvous_id' => $rendezvous->getId(),
                            'reason' => 'Paiement annulé'
                        ]);
                    }
                    $logger->info("[FeexPay Webhook] Paiement annulé pour RDV #{$rendezvous->getId()}");
                    break;

                case 'pending':
                    $rendezvous->setStatus('Paiement en attente');
                    break;
            }

            // Sauvegarder en base
            $em->flush();

            $logger->info(sprintf(
                '[FeexPay Webhook] Traitement terminé - Réf: %s, Ancien statut: %s, Nouveau statut: %s',
                $reference,
                $oldStatus,
                $internalStatus
            ));

            return new JsonResponse([
                'success' => true,
                'reference' => $reference,
                'old_status' => $oldStatus,
                'new_status' => $internalStatus
            ]);
        } catch (\Exception $e) {
            $logger->error('[FeexPay Webhook] Erreur lors du traitement', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return new JsonResponse([
                'error' => 'Internal server error'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function sendClientSuccessEmail($rendezvous, MailerInterface $mailer, LoggerInterface $logger): void
    {
        try {
            $userEmail = $rendezvous->getUser()->getEmail();
            $email = (new Email())
                ->from('BeElle Nails Care <reservation@beellegroup.com>')
                ->to($userEmail)
                ->replyTo('reservation@beellegroup.com')
                ->subject('Informations de rendez-vous!')
                ->html($this->renderView('emails/rendezvous_created.html.twig', [
                    'rendezvous' => $rendezvous
                ]));
            $email->getHeaders()
                ->addTextHeader('X-Mailer', 'BeElle Nails Booking System')
                ->addTextHeader('X-Auto-Response-Suppress', 'OOF, DR, RN, NRN, AutoReply');

            $mailer->send($email);
            $logger->info("[FeexPay Webhook] Email client envoyé à: {$userEmail}");
        } catch (\Exception $e) {
            $logger->error('[FeexPay Webhook] Erreur envoi email client', [
                'error' => $e->getMessage()
            ]);
        }
    }

    private function sendAdminNotificationEmail($rendezvous, MailerInterface $mailer, LoggerInterface $logger): void
    {
        try {
            $adminEmail = (new Email())
                ->from('BeElle Nails Care <reservation@beellegroup.com>')
                ->to('murielahodode@gmail.com')
                ->replyTo('reservation@beellegroup.com')
                ->subject('Nouveau Rendez-vous !')
                ->html($this->renderView('emails/rendezvous_created_admin.html.twig', [
                    'rendezvous' => $rendezvous
                ]));
            $adminEmail->getHeaders()
                ->addTextHeader('X-Mailer', 'BeElle Nails Booking System')
                ->addTextHeader('X-Auto-Response-Suppress', 'OOF, DR, RN, NRN, AutoReply');

            $mailer->send($adminEmail);
            $logger->info('[FeexPay Webhook] Email admin envoyé');
        } catch (\Exception $e) {
            $logger->error('[FeexPay Webhook] Erreur envoi email admin', [
                'error' => $e->getMessage()
            ]);
        }
    }
}

<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <jy.ahouanvoedo@gmail.com>


namespace App\Controller;

use App\Entity\Payment;
use App\Entity\Rendezvous;
use App\Form\FeexPayFormType;
use App\Service\FeexpayService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class PaymentFeexController extends AbstractController
{
    #[Route('/payment/feexpay/form/{rendezvous}', name: 'feexpay_form')]
    public function form(Request $request, Rendezvous $rendezvous): Response
    {
        $form = $this->createForm(FeexPayFormType::class);

        return $this->render('feexpay/form.html.twig', [
            'form' => $form->createView(),
            'rendezvous' => $rendezvous,
        ]);
    }

    #[Route('/payment/feexpay/init/{rendezvous}', name: 'feexpay_payment_init', methods: ['POST'])]
    public function init(
        Request $request,
        Rendezvous $rendezvous,
        FeexpayService $feexpayService,
        EntityManagerInterface $em,
        LoggerInterface $logger
    ): Response {
        $form = $this->createForm(FeexPayFormType::class);
        $form->handleRequest($request);

        // Debug: Log form submission status
        $logger->info('[FeexPay Init] Form submission status', [
            'rendezvous_id' => $rendezvous->getId(),
            'is_submitted' => $form->isSubmitted(),
            'is_valid' => $form->isValid(),
            'request_method' => $request->getMethod(),
            'post_data' => $request->request->all(),
            'form_errors' => $form->getErrors(true)
        ]);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $logger->warning('[FeexPay Init] Form validation failed', [
                'rendezvous_id' => $rendezvous->getId(),
                'form_errors' => $form->getErrors(true, false)
            ]);
            
            return $this->render('feexpay/form.html.twig', [
                'form' => $form->createView(),
                'rendezvous' => $rendezvous,
                'error' => 'Formulaire invalide',
            ]);
        }

        $data = $form->getData();
        $user = $rendezvous->getUser();

        $logger->info('[FeexPay Init] Initiating payment', [
            'rendezvous_id' => $rendezvous->getId(),
            'amount' => 100,
            'phone' => $data['phone'],
            'operator' => $data['operator'],
            'user_email' => $user->getEmail(),
            'reference_id' => 'rendezvous_' . $rendezvous->getId()
        ]);

        $response = $feexpayService->paiementLocal(
            100,
            $data['phone'],
            $data['operator'],
            $user->__toString(),
            $user->getEmail(),
            'rendezvous_' . $rendezvous->getId()
        );

        $logger->info('[FeexPay Init] FeexPay API response', [
            'rendezvous_id' => $rendezvous->getId(),
            'response' => $response,
            'has_reference' => isset($response['reference'])
        ]);

        if (!isset($response['reference'])) {
            $logger->error('[FeexPay Init] FeexPay API failed', [
                'rendezvous_id' => $rendezvous->getId(),
                'response' => $response,
                'error_message' => $response['message'] ?? 'Erreur FeexPay'
            ]);
            
            return $this->render('feexpay/form.html.twig', [
                'form' => $form->createView(),
                'rendezvous' => $rendezvous,
                'error' => $response['message'] ?? 'Erreur FeexPay',
                'debug' => $response
            ]);
        }

        $payment = new Payment();
        $payment->initializeForFeexPay(
            transactionID: $response['reference'],
            reference: $response['reference'],
            phoneNumber: $data['phone'],
            customer: $user,
            rendezvous: $rendezvous,
            mode: $feexpayService->getMode(),
            provider: 'feexpay'
        );

        $em->persist($payment);
        $em->flush();

        // Rediriger vers la page d'attente au lieu de la page de succès
        return $this->redirectToRoute('rendezvous_payment_pending', [
            'reference' => $response['reference']
        ]);
    }

    /**
     * API endpoint pour vérifier le statut du paiement en temps réel (AJAX polling - BDD seulement)
     */
    #[Route('/api/payment/feexpay/status/{reference}', name: 'api_feexpay_payment_status', methods: ['GET'])]
    public function apiStatus(
        string $reference,
        EntityManagerInterface $em
    ): Response {
        $payment = $em->getRepository(Payment::class)->findOneBy(['reference' => $reference]);

        if (!$payment) {
            return $this->json(['error' => 'Paiement introuvable'], 404);
        }

        return $this->json([
            'reference' => $reference,
            'status' => $payment->getStatus(),
            'updated_at' => $payment->getUpdatedAt()?->format('Y-m-d H:i:s'),
            'is_paid' => $payment->getRendezvous()->isPaid(),
            'source' => 'database'
        ]);
    }

    /**
     * API endpoint hybride - Vérifie directement chez Feexpay ET met à jour notre BDD
     */
    #[Route('/api/payment/feexpay/verify/{reference}', name: 'api_feexpay_verify_direct', methods: ['GET'])]
    public function apiVerifyDirect(
        string $reference,
        EntityManagerInterface $em,
        FeexpayService $feexpayService,
        LoggerInterface $logger,
        MailerInterface $mailer
    ): Response {
        $payment = $em->getRepository(Payment::class)->findOneBy(['reference' => $reference]);

        if (!$payment) {
            return $this->json(['error' => 'Paiement introuvable'], 404);
        }

        try {
            // 1. Vérifier directement chez Feexpay
            $feexpayResponse = $feexpayService->getPaiementStatus($reference);

            $logger->info('[Hybrid Polling] Vérification directe Feexpay', [
                'reference' => $reference,
                'current_db_status' => $payment->getStatus(),
                'feexpay_response' => $feexpayResponse
            ]);

            // 2. Convertir le statut Feexpay
            $feexpayStatus = $feexpayResponse['status'] ?? 'unknown';
            $internalStatus = Payment::convertFeexStatus($feexpayStatus);

            // 3. Comparer avec notre statut actuel
            $currentStatus = $payment->getStatus();
            $statusChanged = ($currentStatus !== $internalStatus);

            // 4. Si le statut a changé, mettre à jour notre BDD
            if ($statusChanged) {
                $oldStatus = $payment->getStatus();
                $payment->setStatus($internalStatus)->setUpdatedAt(new \DateTime());

                $rendezvous = $payment->getRendezvous();

                // Traiter selon le nouveau statut (même logique que le webhook)
                switch ($internalStatus) {
                    case 'successful':
                        if ($oldStatus !== 'successful') {
                            $rendezvous->setPaid(true)->setStatus('Rendez-vous pris');

                            // Envoi des emails comme dans le webhook
                            $this->sendSuccessEmails($rendezvous, $mailer, $logger);

                            $logger->info("[Hybrid Polling] Paiement réussi détecté - Emails envoyés pour RDV #{$rendezvous->getId()}");
                        }
                        break;

                    case 'failed':
                        $rendezvous->setStatus('Échec du paiement');
                        break;

                    case 'canceled':
                        $rendezvous->setStatus('Paiement annulé');
                        break;

                    case 'pending':
                        $rendezvous->setStatus('Paiement en attente');
                        break;
                }

                $em->flush();

                $logger->info('[Hybrid Polling] Statut mis à jour depuis Feexpay', [
                    'reference' => $reference,
                    'old_status' => $oldStatus,
                    'new_status' => $internalStatus,
                    'feexpay_status' => $feexpayStatus
                ]);
            }

            return $this->json([
                'reference' => $reference,
                'status' => $internalStatus,
                'updated_at' => $payment->getUpdatedAt()?->format('Y-m-d H:i:s'),
                'is_paid' => $payment->getRendezvous()->isPaid(),
                'source' => 'feexpay_direct',
                'status_changed' => $statusChanged,
                'feexpay_status' => $feexpayStatus,
                'feexpay_response' => $feexpayResponse
            ]);
        } catch (\Exception $e) {
            $logger->error('[Hybrid Polling] Erreur lors de la vérification directe', [
                'reference' => $reference,
                'error' => $e->getMessage()
            ]);

            // En cas d'erreur, retourner le statut actuel de notre BDD
            return $this->json([
                'reference' => $reference,
                'status' => $payment->getStatus(),
                'updated_at' => $payment->getUpdatedAt()?->format('Y-m-d H:i:s'),
                'is_paid' => $payment->getRendezvous()->isPaid(),
                'source' => 'database_fallback',
                'error' => 'Impossible de vérifier chez Feexpay'
            ]);
        }
    }

    /**
     * Méthode helper pour envoyer les emails de succès
     */
    private function sendSuccessEmails($rendezvous, MailerInterface $mailer, LoggerInterface $logger): void
    {
        try {
            // Email client
            $userEmail = $rendezvous->getUser()->getEmail();
            $clientEmail = (new Email())
                ->from('beellenailscare@beellenails.com')
                ->to($userEmail)
                ->subject('Informations de rendez-vous!')
                ->html($this->renderView('emails/rendezvous_created.html.twig', [
                    'rendezvous' => $rendezvous
                ]));
            $mailer->send($clientEmail);

            // Email admin
            $adminEmail = (new Email())
                ->from('beellenailscare@beellenails.com')
                ->to('murielahodode@gmail.com')
                ->subject('Nouveau Rendez-vous !')
                ->html($this->renderView('emails/rendezvous_created_admin.html.twig', [
                    'rendezvous' => $rendezvous
                ]));
            $mailer->send($adminEmail);

            $logger->info('[Hybrid Polling] Emails de succès envoyés');
        } catch (\Exception $e) {
            $logger->error('[Hybrid Polling] Erreur envoi emails', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Endpoint pour vérifier manuellement le statut (optionnel - pour debug)
     */
    #[Route('/payment/feexpay/status/{reference}', name: 'feexpay_payment_status', methods: ['GET'])]
    public function status(
        string $reference,
        FeexpayService $feexpayService,
        EntityManagerInterface $em,
        LoggerInterface $logger
    ): Response {
        $payment = $em->getRepository(Payment::class)->findOneBy(['reference' => $reference]);

        if (!$payment) {
            throw $this->createNotFoundException('Paiement introuvable');
        }

        // Vérification manuelle du statut (pour debug)
        $result = $feexpayService->getPaiementStatus($reference);

        $logger->info('[FeexPay Status Check] Vérification manuelle', [
            'reference' => $reference,
            'current_status' => $payment->getStatus(),
            'feexpay_response' => $result
        ]);

        return $this->json([
            'reference' => $reference,
            'current_status' => $payment->getStatus(),
            'feexpay_status' => $result['status'] ?? 'UNKNOWN',
            'feexpay_response' => $result
        ]);
    }

    #[Route('/rendezvous/payment/done/{reference}', name: 'rendezvous_payment_done')]
    public function paymentDone(string $reference, EntityManagerInterface $em): Response
    {
        $payment = $em->getRepository(Payment::class)->findOneBy(['reference' => $reference]);

        if (!$payment) {
            throw $this->createNotFoundException('Paiement introuvable');
        }

        return $this->render('rendezvous/payment/done.html.twig', [
            'payment' => $payment,
        ]);
    }

    #[Route('/rendezvous/payment/error/{reference}', name: 'rendezvous_payment_error')]
    public function paymentError(string $reference, EntityManagerInterface $em): Response
    {
        $payment = $em->getRepository(Payment::class)->findOneBy(['reference' => $reference]);

        if (!$payment) {
            throw $this->createNotFoundException('Paiement introuvable');
        }

        return $this->render('rendezvous/payment/error.html.twig', [
            'payment' => $payment,
        ]);
    }

    #[Route('/rendezvous/payment/pending/{reference}', name: 'rendezvous_payment_pending')]
    public function paymentPending(string $reference, EntityManagerInterface $em): Response
    {
        $payment = $em->getRepository(Payment::class)->findOneBy(['reference' => $reference]);

        if (!$payment) {
            throw $this->createNotFoundException('Paiement introuvable');
        }

        return $this->render('feexpay/pending.html.twig', [
            'payment' => $payment,
            'reference' => $reference,
        ]);
    }
}

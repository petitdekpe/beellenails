<?php

namespace App\Service;

use App\Entity\Rendezvous;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

class NotificationService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly Environment $twig,
        private readonly LoggerInterface $logger,
        private readonly string $adminEmail = 'murielahodode@gmail.com',
        private readonly string $fromEmail = 'beellenailscare@beellenails.com'
    ) {}

    /**
     * Envoie les notifications de confirmation de paiement
     */
    public function sendPaymentConfirmation(Rendezvous $rendezvous): void
    {
        try {
            // Email au client
            $this->sendCustomerConfirmation($rendezvous);

            // Email à l'admin
            $this->sendAdminNotification($rendezvous);

            $this->logger->info('Notifications de paiement envoyées', [
                'rendezvous_id' => $rendezvous->getId(),
                'customer_email' => $rendezvous->getUser()->getEmail()
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Erreur envoi notifications de paiement', [
                'rendezvous_id' => $rendezvous->getId(),
                'error' => $e->getMessage()
            ]);

            // On ne relance pas l'exception pour ne pas bloquer le processus de paiement
        }
    }

    /**
     * Envoie l'email de confirmation au client
     */
    private function sendCustomerConfirmation(Rendezvous $rendezvous): void
    {
        try {
            $email = (new Email())
                ->from($this->fromEmail)
                ->to($rendezvous->getUser()->getEmail())
                ->subject('✅ Confirmation de votre rendez-vous - Paiement réussi')
                ->html($this->twig->render('emails/rendezvous_created.html.twig', [
                    'rendezvous' => $rendezvous
                ]));

            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Erreur envoi email client', [
                'rendezvous_id' => $rendezvous->getId(),
                'customer_email' => $rendezvous->getUser()->getEmail(),
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Envoie l'email de notification à l'admin
     */
    private function sendAdminNotification(Rendezvous $rendezvous): void
    {
        try {
            $email = (new Email())
                ->from($this->fromEmail)
                ->to($this->adminEmail)
                ->subject('🎉 Nouveau rendez-vous payé !')
                ->html($this->twig->render('emails/rendezvous_created_admin.html.twig', [
                    'rendezvous' => $rendezvous
                ]));

            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Erreur envoi email admin', [
                'rendezvous_id' => $rendezvous->getId(),
                'admin_email' => $this->adminEmail,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Envoie une notification d'échec de paiement
     */
    public function sendPaymentFailureNotification(Rendezvous $rendezvous, string $reason = ''): void
    {
        try {
            $email = (new Email())
                ->from($this->fromEmail)
                ->to($rendezvous->getUser()->getEmail())
                ->subject('❌ Échec du paiement - Rendez-vous en attente')
                ->html($this->twig->render('emails/payment_failed.html.twig', [
                    'rendezvous' => $rendezvous,
                    'reason' => $reason
                ]));

            $this->mailer->send($email);

            $this->logger->info('Notification d\'échec de paiement envoyée', [
                'rendezvous_id' => $rendezvous->getId(),
                'customer_email' => $rendezvous->getUser()->getEmail()
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Erreur envoi notification échec paiement', [
                'rendezvous_id' => $rendezvous->getId(),
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Envoie un rappel de paiement en attente
     */
    public function sendPendingPaymentReminder(Rendezvous $rendezvous): void
    {
        try {
            $email = (new Email())
                ->from($this->fromEmail)
                ->to($rendezvous->getUser()->getEmail())
                ->subject('⏰ Rappel - Paiement en attente')
                ->html($this->twig->render('emails/payment_pending.html.twig', [
                    'rendezvous' => $rendezvous
                ]));

            $this->mailer->send($email);

            $this->logger->info('Rappel de paiement en attente envoyé', [
                'rendezvous_id' => $rendezvous->getId(),
                'customer_email' => $rendezvous->getUser()->getEmail()
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Erreur envoi rappel paiement en attente', [
                'rendezvous_id' => $rendezvous->getId(),
                'error' => $e->getMessage()
            ]);
        }
    }
}

<?php

namespace App\MessageHandler;

use App\Message\SendReminderEmailsMessage;
use App\Repository\RendezvousRepository;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;
use Twig\Environment;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;

#[AsMessageHandler]
class SendReminderEmailsMessageHandler
{
    private RendezvousRepository $rendezVousRepository;
    private MailerInterface $mailer;
    private Environment $twig;
    private LoggerInterface $logger;
    private LockFactory $lockFactory;

    public function __construct(
        RendezvousRepository $rendezVousRepository,
        MailerInterface $mailer,
        Environment $twig,
        LoggerInterface $logger,
        LockFactory $lockFactory
    ) {
        $this->rendezVousRepository = $rendezVousRepository;
        $this->mailer = $mailer;
        $this->twig = $twig;
        $this->logger = $logger;
        $this->lockFactory = $lockFactory;
    }

    public function __invoke(SendReminderEmailsMessage $message): void
    {
        // Créer un verrou pour empêcher l'exécution simultanée
        $lock = $this->lockFactory->createLock('send_reminder_emails_' . date('Y-m-d-H'));

        if (!$lock->acquire()) {
            $this->logger->info('Envoi de mails de rappel déjà en cours, abandon de cette exécution');
            return;
        }

        try {
            $upcomingAppointments = $this->rendezVousRepository->findUpcomingAppointments();
            $emailCount = 0;

        foreach ($upcomingAppointments as $appointment) {
            try {
                $user = $appointment->getUser();
                $email = (new Email())
                    ->from('BeElle Nails Care <reservation@beellegroup.com>')
                    ->to($user->getEmail())
                    ->replyTo('reservation@beellegroup.com')
                    ->subject('Rappel : Rendez-vous dans 3 jours')
                    ->html($this->twig->render(
                        'emails/rendezvous_reminder.html.twig',
                        ['rendezvous' => $appointment]
                    ));

                $email->getHeaders()
                    ->addTextHeader('X-Mailer', 'BeElle Nails Booking System')
                    ->addTextHeader('X-Auto-Response-Suppress', 'OOF, DR, RN, NRN, AutoReply');

                $this->mailer->send($email);
                $emailCount++;
                
                $this->logger->info('Mail de rappel envoyé', [
                    'recipient' => $user->getEmail(),
                    'appointment_id' => $appointment->getId(),
                    'appointment_date' => $appointment->getDay()->format('Y-m-d'),
                    'appointment_time' => $appointment->getCreneau()->getStartTime()->format('H:i')
                ]);
            } catch (\Exception $e) {
                $this->logger->error('Erreur lors de l\'envoi du mail de rappel', [
                    'appointment_id' => $appointment->getId(),
                    'error' => $e->getMessage()
                ]);
            }
        }

            $this->logger->info('Envoi des mails de rappel terminé', [
                'emails_sent' => $emailCount,
                'total_appointments' => count($upcomingAppointments)
            ]);
        } finally {
            $lock->release();
        }
    }
}
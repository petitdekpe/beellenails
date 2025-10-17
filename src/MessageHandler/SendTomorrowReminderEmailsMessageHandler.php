<?php

namespace App\MessageHandler;

use App\Message\SendTomorrowReminderEmailsMessage;
use App\Repository\RendezvousRepository;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;
use Twig\Environment;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;

#[AsMessageHandler]
class SendTomorrowReminderEmailsMessageHandler
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

    public function __invoke(SendTomorrowReminderEmailsMessage $message): void
    {
        // Créer un verrou pour empêcher l'exécution simultanée
        $lock = $this->lockFactory->createLock('send_tomorrow_reminder_emails_' . date('Y-m-d-H'));

        if (!$lock->acquire()) {
            $this->logger->info('Envoi de mails de rappel J-1 déjà en cours, abandon de cette exécution');
            return;
        }

        try {
            $tomorrowAppointments = $this->rendezVousRepository->findTomorrowAppointments();
            $emailCount = 0;

        foreach ($tomorrowAppointments as $appointment) {
            try {
                $user = $appointment->getUser();
                $email = (new Email())
                    ->from('beellenailscare@beellenails.com')
                    ->to($user->getEmail())
                    ->subject('Rappel : Rendez-vous demain chez BeElle Nails')
                    ->html($this->twig->render(
                        'emails/rendezvous_tomorrow_reminder.html.twig',
                        ['rendezvous' => $appointment]
                    ));

                $this->mailer->send($email);
                $emailCount++;
                
                $this->logger->info('Mail de rappel J-1 envoyé', [
                    'recipient' => $user->getEmail(),
                    'appointment_id' => $appointment->getId(),
                    'appointment_date' => $appointment->getDay()->format('Y-m-d'),
                    'appointment_time' => $appointment->getCreneau()->getStartTime()->format('H:i')
                ]);
            } catch (\Exception $e) {
                $this->logger->error('Erreur lors de l\'envoi du mail de rappel J-1', [
                    'appointment_id' => $appointment->getId(),
                    'error' => $e->getMessage()
                ]);
            }
        }

            $this->logger->info('Envoi des mails de rappel J-1 terminé', [
                'emails_sent' => $emailCount,
                'total_appointments' => count($tomorrowAppointments)
            ]);
        } finally {
            $lock->release();
        }
    }
}
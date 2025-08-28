<?php

namespace App\MessageHandler;

use App\Message\SendReminderEmailsMessage;
use App\Repository\RendezvousRepository;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;
use Twig\Environment;
use Psr\Log\LoggerInterface;

#[AsMessageHandler]
class SendReminderEmailsMessageHandler
{
    private RendezvousRepository $rendezVousRepository;
    private MailerInterface $mailer;
    private Environment $twig;
    private LoggerInterface $logger;

    public function __construct(
        RendezvousRepository $rendezVousRepository,
        MailerInterface $mailer,
        Environment $twig,
        LoggerInterface $logger
    ) {
        $this->rendezVousRepository = $rendezVousRepository;
        $this->mailer = $mailer;
        $this->twig = $twig;
        $this->logger = $logger;
    }

    public function __invoke(SendReminderEmailsMessage $message): void
    {
        $upcomingAppointments = $this->rendezVousRepository->findUpcomingAppointments();
        $emailCount = 0;

        foreach ($upcomingAppointments as $appointment) {
            try {
                $user = $appointment->getUser();
                $email = (new Email())
                    ->from('beellenailscare@beellenails.com')
                    ->to($user->getEmail())
                    ->subject('Rappel : Rendez-vous dans 3 jours')
                    ->html($this->twig->render(
                        'emails/rendezvous_reminder.html.twig',
                        ['rendezvous' => $appointment]
                    ));

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
    }
}
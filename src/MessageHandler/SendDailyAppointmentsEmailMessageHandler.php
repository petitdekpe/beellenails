<?php

namespace App\MessageHandler;

use App\Message\SendDailyAppointmentsEmailMessage;
use App\Repository\RendezvousRepository;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;
use Twig\Environment;
use Psr\Log\LoggerInterface;

#[AsMessageHandler]
class SendDailyAppointmentsEmailMessageHandler
{
    private RendezvousRepository $rendezvousRepository;
    private MailerInterface $mailer;
    private Environment $twig;
    private LoggerInterface $logger;

    public function __construct(
        RendezvousRepository $rendezvousRepository,
        MailerInterface $mailer,
        Environment $twig,
        LoggerInterface $logger
    ) {
        $this->rendezvousRepository = $rendezvousRepository;
        $this->mailer = $mailer;
        $this->twig = $twig;
        $this->logger = $logger;
    }

    public function __invoke(SendDailyAppointmentsEmailMessage $message): void
    {
        try {
            $appointments = $this->rendezvousRepository->findTomorrowAppointments();

            $email = (new Email())
                ->from('beellenailscare@beellenails.com')
                ->to('murielahodode@gmail.com')
                ->subject('Rendez-vous pour demain')
                ->html($this->twig->render('emails/rendezvous_daily_appointments.html.twig', [
                    'appointments' => $appointments,
                ]));

            $this->mailer->send($email);

            $this->logger->info('Email quotidien avec liste des rendez-vous envoyé à l\'admin', [
                'appointments_count' => count($appointments),
                'recipient' => 'murielahodode@gmail.com'
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de l\'envoi de l\'email quotidien admin', [
                'error' => $e->getMessage()
            ]);
        }
    }
}
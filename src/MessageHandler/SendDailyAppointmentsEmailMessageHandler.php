<?php

namespace App\MessageHandler;

use App\Message\SendDailyAppointmentsEmailMessage;
use App\Repository\RendezvousRepository;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;
use Twig\Environment;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;

#[AsMessageHandler]
class SendDailyAppointmentsEmailMessageHandler
{
    private RendezvousRepository $rendezvousRepository;
    private MailerInterface $mailer;
    private Environment $twig;
    private LoggerInterface $logger;
    private LockFactory $lockFactory;

    public function __construct(
        RendezvousRepository $rendezvousRepository,
        MailerInterface $mailer,
        Environment $twig,
        LoggerInterface $logger,
        LockFactory $lockFactory
    ) {
        $this->rendezvousRepository = $rendezvousRepository;
        $this->mailer = $mailer;
        $this->twig = $twig;
        $this->logger = $logger;
        $this->lockFactory = $lockFactory;
    }

    public function __invoke(SendDailyAppointmentsEmailMessage $message): void
    {
        // Créer un verrou pour empêcher l'exécution simultanée
        $lock = $this->lockFactory->createLock('send_daily_appointments_email_' . date('Y-m-d-H'));

        if (!$lock->acquire()) {
            $this->logger->info('Envoi de l\'email quotidien admin déjà en cours, abandon de cette exécution');
            return;
        }

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
        } finally {
            $lock->release();
        }
    }
}
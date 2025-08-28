<?php

namespace App\Command;

use App\Repository\RendezvousRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

class SendTomorrowReminderEmailsCommand extends Command
{
    protected static $defaultName = 'app:send-tomorrow-reminder-emails';

    private $rendezVousRepository;
    private $mailer;
    private $twig;

    public function __construct(RendezvousRepository $rendezVousRepository, MailerInterface $mailer, Environment $twig)
    {
        $this->rendezVousRepository = $rendezVousRepository;
        $this->mailer = $mailer;
        $this->twig = $twig;

        parent::__construct();
    }

    protected function configure()
    {
        $this->setDescription('Send reminder emails for tomorrow appointments (day before reminder)');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $tomorrowAppointments = $this->rendezVousRepository->findTomorrowAppointments();
        $emailCount = 0;
        $recipients = [];

        foreach ($tomorrowAppointments as $appointment) {
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
            $recipients[] = $user->getNom().' '.$user->getPrenom();
        }

        $output->writeln(sprintf('Les rappels J-1 par e-mail ont été envoyés avec succès. Nombre de mails envoyés : %d', $emailCount));
        $output->writeln('Liste des destinataires :');
        foreach ($recipients as $recipient) {
            $output->writeln($recipient);
        }

        return Command::SUCCESS;
    }
}
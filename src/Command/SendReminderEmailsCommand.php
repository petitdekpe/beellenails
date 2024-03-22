<?php

namespace App\Command;

use App\Repository\RendezvousRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

class SendReminderEmailsCommand extends Command
{
    protected static $defaultName = 'app:send-reminder-emails';

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
        $this->setDescription('Send reminder emails for upcoming appointments');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $upcomingAppointments = $this->rendezVousRepository->findUpcomingAppointments();

        foreach ($upcomingAppointments as $appointment) {
            $user = $appointment->getUser();
            $email = (new Email())
                ->from('beellenailscare@beellenails.com')
                ->to($user->getEmail())
                ->subject('Rappel : Rendez-vous à venir')
                ->html($this->twig->render(
                    'emails/rendezvous_reminder.html.twig',
                    ['rendezvou' => $appointment]
                ));

            $this->mailer->send($email);
        }

        $output->writeln('Les rappels par e-mail ont été envoyés avec succès.');

        return Command::SUCCESS;
    }
}

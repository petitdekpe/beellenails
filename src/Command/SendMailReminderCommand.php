<?php

namespace App\Command;

use App\Command\SendMailReminderCommand;
use Symfony\Component\Mime\Email;
use App\Repository\RendezvousRepository;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


class EmailReminderCommand extends Command
{
    protected static $defaultName = 'app:send-email-reminder';

    private $rendezVousRepository;
    private $mailer;

    public function __construct(RendezvousRepository $rendezVousRepository, MailerInterface $mailer)
    {
        $this->rendezVousRepository = $rendezVousRepository;
        $this->mailer = $mailer;

        parent::__construct();
    }

    protected function configure()
    {
        $this->setDescription('Sends email reminders for upcoming appointments.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Récupérer les rendez-vous avec les statuts appropriés et la date dans deux jours
        $upcomingAppointments = $this->rendezVousRepository->findUpcomingAppointments();

        foreach ($upcomingAppointments as $appointment) {
            // Envoyer un e-mail à chaque utilisateur
            $user = $appointment->getUser();
            $email = (new Email())
                ->from('your_email@example.com')
                ->to($user->getEmail())
                ->subject('Rendez-vous à venir')
                ->html('<p>Votre rendez-vous est prévu dans deux jours. Merci de confirmer.</p>');

            $this->mailer->send($email);
        }

        $output->writeln('Email reminders sent successfully.');

        return Command::SUCCESS;
    }
}
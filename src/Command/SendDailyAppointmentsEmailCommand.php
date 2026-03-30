<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use App\Repository\RendezvousRepository;
use Twig\Environment;

class SendDailyAppointmentsEmailCommand extends Command
{
    protected static $defaultName = 'app:send-daily-appointments-email';
    private $mailer;
    private $rendezvousRepository;
    private $twig;

    public function __construct(MailerInterface $mailer, RendezvousRepository $rendezvousRepository, Environment $twig)
    {
        $this->mailer = $mailer;
        $this->rendezvousRepository = $rendezvousRepository;
        $this->twig = $twig;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription('Send daily email with list of upcoming appointments.')
            ->setHelp('This command sends a daily email with the list of appointments for the next day.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Email : rendez-vous de demain
        $appointmentsTomorrow = $this->rendezvousRepository->findTomorrowAppointments();

        $emailTomorrow = (new Email())
            ->from('beellenailscare@beellenails.com')
            ->to('murielahodode@gmail.com', 'resabeelle@gmail.com')
            ->bcc('petitdekpe@gmail.com')
            ->subject('Rendez-vous pour demain')
            ->html($this->twig->render('emails/rendezvous_daily_appointments.html.twig', [
                'appointments' => $appointmentsTomorrow,
                'label'        => 'demain',
            ]));

        $this->mailer->send($emailTomorrow);
        $output->writeln('Email "demain" envoyé (' . count($appointmentsTomorrow) . ' RDV).');

        // Email : rendez-vous dans 3 jours
        $appointmentsIn3Days = $this->rendezvousRepository->findAppointmentsInDays(3);

        $emailIn3Days = (new Email())
            ->from('beellenailscare@beellenails.com')
            ->to('murielahodode@gmail.com', 'resabeelle@gmail.com')
            ->bcc('petitdekpe@gmail.com')
            ->subject('Rendez-vous dans 3 jours')
            ->html($this->twig->render('emails/rendezvous_daily_appointments.html.twig', [
                'appointments' => $appointmentsIn3Days,
                'label'        => 'dans 3 jours',
            ]));

        $this->mailer->send($emailIn3Days);
        $output->writeln('Email "dans 3 jours" envoyé (' . count($appointmentsIn3Days) . ' RDV).');

        return Command::SUCCESS;
    }

}

<?php

namespace App\Command;

use App\Repository\RendezvousRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Console\Input\InputOption;
use Twig\Environment;
use Symfony\Component\Console\Style\SymfonyStyle;

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
        $this->addOption(
            'schedule',
            's',
            InputOption::VALUE_OPTIONAL,
            'Schedule for sending reminder emails',
            '20 11 * * *' // 6h50 tous les jours
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Récupérer l'option de planification
        $schedule = $input->getOption('schedule');

        // Récupérer les rendez-vous à venir
        $upcomingAppointments = $this->rendezVousRepository->findUpcomingAppointments();
        $emailCount = 0;
        $recipients = [];

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
            $emailCount++;
            $recipients[] = $user->getNom().' '.$user->getPrenom();
        }

        // Afficher les informations de l'envoi des e-mails
        $output->writeln(sprintf('Les rappels par e-mail ont été envoyés avec succès. Nombre de mails envoyés : %d', $emailCount));
        $output->writeln('Liste des destinataires :');
        foreach ($recipients as $recipient) {
            $output->writeln($recipient);
        }

        return Command::SUCCESS;
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        // Vérifier si l'option de planification est fournie en ligne de commande
        if (!$input->getOption('schedule')) {
            // Demander à l'utilisateur de saisir la planification s'il n'est pas fourni
            $schedule = $io->ask('Entrez la planification pour l\'envoi des rappels par e-mail (format cron, par exemple: "50 6 * * *")', '50 6 * * *');
            $input->setOption('schedule', $schedule);
        }
    }
}

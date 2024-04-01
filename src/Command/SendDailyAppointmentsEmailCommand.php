<?php

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
        $tomorrow = new \DateTime('+1 day');
        $appointments = $this->rendezvousRepository->findByDay($tomorrow);

        $email = (new Email())
            ->from('beellenailscare@beellenails.com')
            ->to('murielahodode@gmail.com')
            ->subject('Rendez-vous pour demain')
            ->html($this->twig->render('emails/rendezvous_daily_appointments.html.twig', [
                'appointments' => $appointments,
            ]));

        $this->mailer->send($email);

        $output->writeln('Daily appointment email sent successfully.');

        return Command::SUCCESS;
    }
}

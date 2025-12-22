<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;
use App\Message\SendReminderEmailsMessage;
use App\Message\SendTomorrowReminderEmailsMessage;
use App\Message\SendDailyAppointmentsEmailMessage;

class SendScheduledEmailsCommand extends Command
{
    protected static $defaultName = 'app:send-scheduled-emails';
    protected static $defaultDescription = 'Envoie tous les emails programmés (rappels J-3, J-1 et email quotidien admin)';

    public function __construct(
        private readonly MessageBusInterface $messageBus
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setHelp('Cette commande déclenche l\'envoi de tous les emails programmés :
- Rappels de rendez-vous J-3 (3 jours avant)
- Rappels de rendez-vous J-1 (veille du RDV)
- Email quotidien à l\'admin avec la liste des rendez-vous de demain');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Envoi des emails programmés');

        try {
            // 1. Rappels J-3 (3 jours avant le RDV)
            $io->section('📧 Envoi des rappels J-3...');
            $this->messageBus->dispatch(new SendReminderEmailsMessage());
            $io->success('Message dispatché pour les rappels J-3');

            // 2. Rappels J-1 (veille du RDV)
            $io->section('📧 Envoi des rappels J-1...');
            $this->messageBus->dispatch(new SendTomorrowReminderEmailsMessage());
            $io->success('Message dispatché pour les rappels J-1');

            // 3. Email quotidien admin
            $io->section('📧 Envoi de l\'email quotidien admin...');
            $this->messageBus->dispatch(new SendDailyAppointmentsEmailMessage());
            $io->success('Message dispatché pour l\'email quotidien admin');

            $io->newLine();
            $io->success('Tous les messages ont été dispatchés avec succès !');
            $io->note('Les emails seront traités par Messenger de manière asynchrone.');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Erreur lors du dispatch des messages : ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}

<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>

// src/Command/SendEmailCommand.php
namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class TestCommand extends Command
{
    protected static $defaultName = 'app:test';

    private $mailer;

    public function __construct(MailerInterface $mailer)
    {
        parent::__construct();
        $this->mailer = $mailer;
    }

    protected function configure()
    {
        $this
            ->setDescription('Send an email')
            ->setHelp('This command allows you to send an email to petitdekpe@gmail.com with the message "Bonjour"');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $email = (new Email())
            ->from('beellenailscare@beellenails.com')
            ->to('petitdekpe@gmail.com')
            ->subject('Bonjour')
            ->text('Bonjour');

        $this->mailer->send($email);

        $output->writeln('E-mail sent successfully.');

        return Command::SUCCESS;
    }
}

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

class SendTestEmailCommand extends Command
{
    protected static $defaultName = 'app:send-test-email';
    private $mailer;

    public function __construct(MailerInterface $mailer)
    {
        $this->mailer = $mailer;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription('Send a test email with content "test effectué".')
            ->setHelp('This command sends a test email with the content "test effectué".');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $email = (new Email())
            ->from('beellenailscare@beellenails.com')
            ->to('murielahodode@gmail.com')
            ->subject('Test Email')
            ->text('test effectué');

        $this->mailer->send($email);

        $output->writeln('Test email sent successfully.');

        return Command::SUCCESS;
    }
}

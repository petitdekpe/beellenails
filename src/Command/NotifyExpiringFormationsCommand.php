<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>

namespace App\Command;

use App\Service\ExpirationNotificationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:notify-expiring-formations',
    description: 'Send email notifications for formations expiring soon',
)]
class NotifyExpiringFormationsCommand extends Command
{
    public function __construct(
        private ExpirationNotificationService $notificationService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Checking for expiring formations...');

        // Send expiration reminders
        $remindersSent = $this->notificationService->sendExpirationReminders();
        $io->success(sprintf('Sent %d expiration reminder(s)', $remindersSent));

        // Mark expired enrollments
        $expiredCount = $this->notificationService->markExpiredEnrollments();
        $io->success(sprintf('Marked %d enrollment(s) as expired', $expiredCount));

        $io->success('Formation expiration check completed successfully!');

        return Command::SUCCESS;
    }
}
<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>

namespace App\Command;

use App\Service\BookingHoldService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Lock\LockFactory;

#[AsCommand(
    name: 'app:expire-stale-booking-holds',
    description: 'Expire les réservations temporaires (Tentative / Paiement en attente) dont le délai de 15 minutes est dépassé',
)]
class ExpireStaleBookingHoldsCommand extends Command
{
    public function __construct(
        private readonly BookingHoldService $bookingHoldService,
        private readonly LockFactory $lockFactory
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $lock = $this->lockFactory->createLock('expire_stale_booking_holds', 120);

        if (!$lock->acquire()) {
            $io->warning('Une autre exécution est déjà en cours, abandon.');
            return Command::SUCCESS;
        }

        try {
            $count = $this->bookingHoldService->expireStaleHolds();
            $io->success(sprintf('%d réservation(s) temporaire(s) expirée(s).', $count));
        } finally {
            $lock->release();
        }

        return Command::SUCCESS;
    }
}

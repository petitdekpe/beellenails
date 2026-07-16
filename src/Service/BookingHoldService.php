<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>

namespace App\Service;

use App\Repository\RendezvousRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Libère les créneaux réservés temporairement (Tentative / Paiement en attente)
 * dont le délai de 15 minutes est dépassé sans que le paiement ait abouti.
 */
class BookingHoldService
{
    public function __construct(
        private readonly RendezvousRepository $rendezvousRepository,
        private readonly PromoCodeService $promoCodeService,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger
    ) {}

    public function expireStaleHolds(): int
    {
        $expiredHolds = $this->rendezvousRepository->findExpiredHolds(new \DateTime());
        $count = 0;

        foreach ($expiredHolds as $rendezvous) {
            $rendezvous->setStatus('Expiré');

            if ($rendezvous->getPromoCode()) {
                $this->promoCodeService->revokePromoCodeUsage($rendezvous, 'Créneau expiré - paiement non finalisé à temps');
            }

            $this->logger->info('[BookingHoldService] Réservation temporaire expirée', [
                'rendezvous_id' => $rendezvous->getId(),
                'day' => $rendezvous->getDay()?->format('Y-m-d'),
                'creneau_id' => $rendezvous->getCreneau()?->getId(),
            ]);

            $count++;
        }

        $this->entityManager->flush();

        return $count;
    }
}

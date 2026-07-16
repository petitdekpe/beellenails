<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>

namespace App\Service;

use App\Entity\Payment;
use App\Entity\Rendezvous;
use App\Repository\RendezvousRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Confirme un rendez-vous après paiement réussi, en revalidant sous verrou
 * qu'aucun autre rendez-vous n'a déjà confirmé le même jour/créneau entretemps.
 *
 * Transaction-agnostique : l'appelant gère son propre beginTransaction/commit/rollback,
 * pour s'adapter aux deux points d'entrée existants (callback FedaPay sans transaction,
 * webhook FeexPay qui en a déjà une ouverte).
 */
class RendezvousConflictResolver
{
    public function __construct(
        private readonly RendezvousRepository $rendezvousRepository,
        private readonly PromoCodeService $promoCodeService,
        private readonly NotificationService $notificationService,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * @return bool true si le rendez-vous a été confirmé normalement, false s'il a été
     *              marqué en conflit (créneau déjà pris par un autre rendez-vous confirmé).
     */
    public function confirmOrFlagConflict(Rendezvous $rendezvous, Payment $payment, EntityManagerInterface $em): bool
    {
        $em->lock($rendezvous->getCreneau(), LockMode::PESSIMISTIC_WRITE);

        $hasConflict = $this->rendezvousRepository->hasConfirmedConflict(
            $rendezvous->getDay(),
            $rendezvous->getCreneau(),
            $rendezvous->getId()
        );

        if (!$hasConflict) {
            $rendezvous->onPaymentSuccess();
            return true;
        }

        $this->logger->warning('[RendezvousConflictResolver] Conflit de créneau détecté après paiement', [
            'rendezvous_id' => $rendezvous->getId(),
            'payment_id' => $payment->getId(),
            'payment_reference' => $payment->getReference(),
            'day' => $rendezvous->getDay()?->format('Y-m-d'),
            'creneau_id' => $rendezvous->getCreneau()?->getId(),
        ]);

        $rendezvous->setStatus('Paiement en conflit');
        $rendezvous->setExpiresAt(null);
        $payment->setStatus('conflict');

        if ($rendezvous->getPromoCode()) {
            $this->promoCodeService->revokePromoCodeUsage($rendezvous, 'Conflit de créneau après paiement tardif');
        }

        $this->notificationService->sendPaymentConflictNotification($rendezvous, $payment);

        return false;
    }
}

<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>


namespace App\Service;

use App\Entity\Creneau;
use App\Entity\Rendezvous;
use App\Entity\User;
use App\Repository\RendezvousRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Exception\ConflictException;

class RendezvousBookingService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private RendezvousRepository $rendezvousRepository
    ) {}

    /**
     * Réserve un créneau de manière thread-safe
     * Utilise un lock pessimiste pour éviter les race conditions
     */
    public function bookSlot(User $user, Creneau $creneau, \DateTime $date, array $prestations = []): Rendezvous
    {
        $this->entityManager->beginTransaction();
        
        try {
            // 1. Lock pessimiste sur le créneau pour éviter les accès concurrents
            $this->entityManager->lock($creneau, LockMode::PESSIMISTIC_WRITE);
            
            // 2. Vérification de disponibilité avec lock actif
            if ($this->isSlotTaken($creneau, $date)) {
                throw new ConflictException(
                    sprintf('Le créneau du %s à %s est déjà réservé', 
                        $date->format('d/m/Y'), 
                        $creneau->getStartTime()->format('H:i')
                    )
                );
            }
            
            // 3. Créer le rendez-vous (l'écriture est maintenant sécurisée)
            $rendezvous = new Rendezvous();
            $rendezvous->setUser($user)
                      ->setCreneau($creneau)
                      ->setDay($date)
                      ->setStatus('Réservé temporairement'); // Status temporaire
            
            // Ajouter les prestations si fournies
            foreach ($prestations as $prestation) {
                $rendezvous->addPrestation($prestation);
            }
            
            $this->entityManager->persist($rendezvous);
            $this->entityManager->flush();
            
            // 4. Confirmer la transaction
            $this->entityManager->commit();
            
            return $rendezvous;
            
        } catch (\Exception $e) {
            // 5. Rollback en cas d'erreur
            $this->entityManager->rollback();
            throw $e;
        }
    }
    
    /**
     * Vérifie si un créneau est déjà pris (méthode sécurisée)
     */
    private function isSlotTaken(Creneau $creneau, \DateTime $date): bool
    {
        $existingAppointment = $this->rendezvousRepository->createQueryBuilder('r')
            ->where('r.creneau = :creneau')
            ->andWhere('r.day = :date')
            ->andWhere('r.status NOT IN (:excludedStatus)')
            ->setParameter('creneau', $creneau)
            ->setParameter('date', $date->format('Y-m-d'))
            ->setParameter('excludedStatus', ['Annulé', 'Expiré'])
            ->getQuery()
            ->getOneOrNullResult();
        
        // Vérifier spécifiquement les congés
        $existingConge = $this->rendezvousRepository->findOneBy([
            'creneau' => $creneau,
            'day' => $date,
            'status' => 'Congé'
        ]);
            
        return $existingAppointment !== null || $existingConge !== null;
    }
    
    /**
     * Confirme définitivement une réservation (après paiement)
     */
    public function confirmBooking(Rendezvous $rendezvous): void
    {
        $rendezvous->setStatus('Rendez-vous confirmé');
        $this->entityManager->flush();
    }
    
    /**
     * Expire les réservations temporaires (à exécuter via cron)
     */
    public function expireTemporaryBookings(\DateTime $olderThan = null): int
    {
        if ($olderThan === null) {
            $olderThan = new \DateTime('-15 minutes'); // Expire après 15min
        }
        
        $expiredBookings = $this->rendezvousRepository->createQueryBuilder('r')
            ->where('r.status = :tempStatus')
            ->andWhere('r.createdAt < :expireDate')
            ->setParameter('tempStatus', 'Réservé temporairement')
            ->setParameter('expireDate', $expireDate)
            ->getQuery()
            ->getResult();
            
        foreach ($expiredBookings as $booking) {
            $booking->setStatus('Expiré');
        }
        
        $this->entityManager->flush();
        
        return count($expiredBookings);
    }
}
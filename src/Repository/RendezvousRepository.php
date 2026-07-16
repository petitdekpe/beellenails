<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>


namespace App\Repository;

use App\Entity\Creneau;
use App\Entity\Rendezvous;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Rendezvous>
 *
 * @method Rendezvous|null find($id, $lockMode = null, $lockVersion = null)
 * @method Rendezvous|null findOneBy(array $criteria, array $orderBy = null)
 * @method Rendezvous[]    findAll()
 * @method Rendezvous[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class RendezvousRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Rendezvous::class);
    }

    public function findPaidRendezvous()
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.status IN (:statuses)')
            ->setParameter('statuses', ['Rendez-vous pris', 'Rendez-vous confirmé', 'Congé'])
            ->getQuery()
            ->getResult();
    }


    /**
     * Find upcoming appointments with status 'Rendez-vous pris' or 'Rendez-vous confirmé'
     * scheduled in three days from now (for reminder emails).
     *
     * @return RendezVous[] Returns an array of RendezVous objects
     */
    public function findUpcomingAppointments()
    {
        $threeDaysFromNow = new \DateTime('3 days');
        $statusCriteria = ['Rendez-vous pris', 'Rendez-vous confirmé'];

        return $this->createQueryBuilder('r')
            ->andWhere('r.status IN (:statuses)')
            ->andWhere('r.day = :day')
            ->setParameter('statuses', $statusCriteria)
            ->setParameter('day', $threeDaysFromNow->format('Y-m-d'))
            ->getQuery()
            ->getResult();
    }

    public function findTomorrowAppointments()
    {
        $tomorrow = new \DateTime('tomorrow');
        $statusCriteria = ['Rendez-vous pris', 'Rendez-vous confirmé'];

        return $this->createQueryBuilder('r')
            ->andWhere('r.status IN (:statuses)')
            ->andWhere('r.day = :day')
            ->setParameter('statuses', $statusCriteria)
            ->setParameter('day', $tomorrow->format('Y-m-d'))
            ->orderBy('r.creneau', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findAppointmentsInDays(int $days)
    {
        $date = new \DateTime('+' . $days . ' days');
        $statusCriteria = ['Rendez-vous pris', 'Rendez-vous confirmé'];

        return $this->createQueryBuilder('r')
            ->andWhere('r.status IN (:statuses)')
            ->andWhere('r.day = :day')
            ->setParameter('statuses', $statusCriteria)
            ->setParameter('day', $date->format('Y-m-d'))
            ->orderBy('r.creneau', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les rendez-vous reportés dans une période donnée
     * Un rendez-vous est considéré comme reporté s'il a des anciennes informations
     * de date et/ou créneau sauvegardées
     *
     * @param \DateTime $startDate
     * @param \DateTime $endDate
     * @return Rendezvous[]
     */
    public function findRescheduledAppointments(\DateTime $startDate, \DateTime $endDate): array
    {
        return $this->createQueryBuilder('r')
            ->join('r.user', 'u')
            ->join('r.prestation', 'p')
            ->join('r.creneau', 'c')
            ->leftJoin('r.previousCreneau', 'pc')
            ->where('r.updated_at BETWEEN :start AND :end')
            ->andWhere('(r.previousDay IS NOT NULL OR r.previousCreneau IS NOT NULL)')
            ->andWhere('r.status IN (:activeStatuses)')
            ->setParameter('start', $startDate->format('Y-m-d 00:00:00'))
            ->setParameter('end', $endDate->format('Y-m-d 23:59:59'))
            ->setParameter('activeStatuses', ['Rendez-vous confirmé', 'Rendez-vous pris'])
            ->orderBy('r.updated_at', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * True si un AUTRE rendez-vous occupe déjà ce jour/créneau : soit confirmé,
     * soit une réservation temporaire pas encore expirée (Tentative / Paiement en attente).
     * Utilisé comme garde-fou avant de générer un lien de paiement.
     */
    public function hasActiveHoldOrConfirmedConflict(\DateTimeInterface $day, Creneau $creneau, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('r')
            ->andWhere('r.day = :day')
            ->andWhere('r.creneau = :creneau')
            ->andWhere('(
                r.status IN (:confirmedStatuses)
                OR (r.status IN (:holdStatuses) AND r.expiresAt > :now)
            )')
            ->setParameter('day', $day->format('Y-m-d'))
            ->setParameter('creneau', $creneau)
            ->setParameter('confirmedStatuses', ['Rendez-vous pris', 'Rendez-vous confirmé'])
            ->setParameter('holdStatuses', ['Tentative', 'Paiement en attente'])
            ->setParameter('now', new \DateTime())
            ->setMaxResults(1);

        if ($excludeId !== null) {
            $qb->andWhere('r.id != :excludeId')->setParameter('excludeId', $excludeId);
        }

        return $qb->getQuery()->getOneOrNullResult() !== null;
    }

    /**
     * True si un AUTRE rendez-vous est déjà confirmé sur ce jour/créneau.
     * Utilisé au moment de la confirmation du paiement (sous verrou) : seule une
     * réservation qui a déjà gagné la course compte, pas une simple tentative concurrente.
     */
    public function hasConfirmedConflict(\DateTimeInterface $day, Creneau $creneau, int $excludeId): bool
    {
        $result = $this->createQueryBuilder('r')
            ->andWhere('r.day = :day')
            ->andWhere('r.creneau = :creneau')
            ->andWhere('r.status IN (:confirmedStatuses)')
            ->andWhere('r.id != :excludeId')
            ->setParameter('day', $day->format('Y-m-d'))
            ->setParameter('creneau', $creneau)
            ->setParameter('confirmedStatuses', ['Rendez-vous pris', 'Rendez-vous confirmé'])
            ->setParameter('excludeId', $excludeId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $result !== null;
    }

    /**
     * Réservations temporaires (Tentative / Paiement en attente) dont le délai est dépassé.
     *
     * @return Rendezvous[]
     */
    public function findExpiredHolds(\DateTimeInterface $now): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.status IN (:holdStatuses)')
            ->andWhere('r.expiresAt IS NOT NULL')
            ->andWhere('r.expiresAt <= :now')
            ->setParameter('holdStatuses', ['Tentative', 'Paiement en attente'])
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();
    }


//    /**
//     * @return Rendezvous[] Returns an array of Rendezvous objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('r')
//            ->andWhere('r.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('r.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Rendezvous
//    {
//        return $this->createQueryBuilder('r')
//            ->andWhere('r.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}

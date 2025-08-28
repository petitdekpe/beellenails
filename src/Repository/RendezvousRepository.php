<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <jy.ahouanvoedo@gmail.com>


namespace App\Repository;

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
            ->setParameter('day', $tomorrow->format('Y-m-d')) // Assuming 'day' field is stored as date without time
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

<?php

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
     * and date in two days from now.
     *
     * @return RendezVous[] Returns an array of RendezVous objects
     */
    public function findUpcomingAppointments()
    {
        $twoDaysFromNow = new \DateTime('2 days');
        $statusCriteria = ['Rendez-vous pris', 'Rendez-vous confirmé'];

        return $this->createQueryBuilder('r')
            ->andWhere('r.status IN (:statuses)')
            ->andWhere('r.day = :day')
            ->setParameter('statuses', $statusCriteria)
            ->setParameter('day', $twoDaysFromNow->format('Y-m-d')) // Assuming 'day' field is stored as date without time
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
     * Trouve les rendez-vous réellement reportés dans une période donnée
     * Un rendez-vous est considéré comme reporté s'il a été modifié avec un délai significatif
     * après sa création, indiquant probablement un changement de date/créneau
     *
     * @param \DateTime $startDate
     * @param \DateTime $endDate
     * @return Rendezvous[]
     */
    public function findRescheduledAppointments(\DateTime $startDate, \DateTime $endDate): array
    {
        // Récupérer tous les rendez-vous modifiés dans la période
        $results = $this->createQueryBuilder('r')
            ->join('r.user', 'u')
            ->join('r.prestation', 'p')
            ->join('r.creneau', 'c')
            ->where('r.updated_at BETWEEN :start AND :end')
            ->andWhere('r.updated_at != r.created_at')
            ->andWhere('r.status IN (:activeStatuses)')
            ->setParameter('start', $startDate->format('Y-m-d 00:00:00'))
            ->setParameter('end', $endDate->format('Y-m-d 23:59:59'))
            ->setParameter('activeStatuses', ['Rendez-vous confirmé', 'Rendez-vous pris'])
            ->orderBy('r.updated_at', 'DESC')
            ->getQuery()
            ->getResult();

        // Filtrer en PHP les rendez-vous avec un délai significatif (> 10 minutes)
        $rescheduledAppointments = [];
        foreach ($results as $rendezvous) {
            $createdAt = $rendezvous->getCreatedAt();
            $updatedAt = $rendezvous->getUpdatedAt();
            
            // Calculer la différence en minutes
            $diff = $updatedAt->getTimestamp() - $createdAt->getTimestamp();
            $diffMinutes = $diff / 60;
            
            // Garder seulement ceux modifiés plus de 10 minutes après création
            if ($diffMinutes > 10) {
                $rescheduledAppointments[] = $rendezvous;
            }
        }
        
        return $rescheduledAppointments;
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

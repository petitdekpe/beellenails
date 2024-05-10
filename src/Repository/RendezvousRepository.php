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

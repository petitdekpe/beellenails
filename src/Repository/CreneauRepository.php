<?php

namespace App\Repository;

use App\Entity\Creneau;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

/**
 * @extends ServiceEntityRepository<Creneau>
 *
 * @method Creneau|null find($id, $lockMode = null, $lockVersion = null)
 * @method Creneau|null findOneBy(array $criteria, array $orderBy = null)
 * @method Creneau[]    findAll()
 * @method Creneau[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CreneauRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Creneau::class);
    }

    public function findAvailableSlots(\DateTimeInterface $selectedDate): array
    {
        $currentTime = new \DateTime(); // Heure actuelle

        // Définir l'heure actuelle plus deux heures
        $twoHoursLater = clone $currentTime;
        $twoHoursLater->modify('+2 hours');

        return $this->createQueryBuilder('c')
            ->leftJoin('c.rendezvouses', 'r', 'WITH', 'r.day = :selectedDate')
            ->andWhere('r.id IS NULL OR c.id NOT IN (
            SELECT cr.id FROM App\Entity\Creneau cr
            INNER JOIN cr.rendezvouses re
            WHERE re.day = :selectedDate
            AND re.status IN (:statuses)
        )')
            ->andWhere('( :selectedDate != CURRENT_DATE() OR c.startTime > :twoHoursLater )') // Vérifie si la date sélectionnée est différente de la date actuelle, ou si l'heure de début est supérieure à l'heure actuelle plus deux heures
            ->setParameter('selectedDate', $selectedDate->format('Y-m-d'))
            ->setParameter('statuses', ['Rendez-vous pris', 'Rendez-vous confirmé', 'Congé'])
            ->setParameter('twoHoursLater', $twoHoursLater->format('H:i:s'))
            ->getQuery()
            ->getResult();
    }





    



//    /**
//     * @return Creneau[] Returns an array of Creneau objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('c')
//            ->andWhere('c.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('c.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Creneau
//    {
//        return $this->createQueryBuilder('c')
//            ->andWhere('c.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}

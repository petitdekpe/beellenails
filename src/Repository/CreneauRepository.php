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
    // Récupère les créneaux associés aux rendez-vous pour la date sélectionnée
    $takenSlots = $this->createQueryBuilder('c')
        ->select('c.id')
        ->innerJoin('c.rendezvouses', 'r')
        ->andWhere('r.day = :selectedDate')
        ->andWhere('r.Paid IS NOT NULL') // Filtrer les rendez-vous avec Paid non null
        ->setParameter('selectedDate', $selectedDate->format('Y-m-d'))
        ->getQuery()
        ->getResult();

    // Récupère tous les créneaux
    $allSlots = $this->createQueryBuilder('c')
        ->select('c')
        ->getQuery()
        ->getResult();

    // Supprime les créneaux associés aux rendez-vous de la liste complète des créneaux
    foreach ($takenSlots as $takenSlot) {
        foreach ($allSlots as $key => $slot) {
            if ($slot->getId() === $takenSlot['id']) {
                unset($allSlots[$key]);
                break;
            }
        }
    }

    // Retourne les créneaux restants comme créneaux disponibles
    return $allSlots;
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

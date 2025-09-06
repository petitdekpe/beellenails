<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>


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
        // Définir le fuseau horaire du serveur (Europe/Paris)
        $serverTimezone = new \DateTimeZone('Europe/Paris');

        // Définir votre fuseau horaire local (Europe/Paris dans cet exemple, ajustez si nécessaire)
        $localTimezone = new \DateTimeZone('Africa/Porto-Novo');

        // Obtenir l'heure actuelle du serveur en Europe/Paris
        $currentTime = new \DateTime('now');

        // Ajouter deux heures à l'heure actuelle du serveur
        $twoHoursLater = clone $currentTime;
        $twoHoursLater->modify('+1 hours');

        return $this->createQueryBuilder('c')
            ->leftJoin('c.rendezvouses', 'r', 'WITH', 'r.day = :selectedDate')
            ->andWhere('r.id IS NULL OR c.id NOT IN (
            SELECT cr.id FROM App\Entity\Creneau cr
            INNER JOIN cr.rendezvouses re
            WHERE re.day = :selectedDate
            AND re.status IN (:statuses)
        )')
            ->andWhere('( :selectedDate != CURRENT_DATE() OR c.startTime > :twoHoursLater )')
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

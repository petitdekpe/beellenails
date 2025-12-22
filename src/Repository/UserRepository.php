<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>


namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
* @implements PasswordUpgraderInterface<User>
 *
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

//    /**
//     * @return User[] Returns an array of User objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('u')
//            ->andWhere('u.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('u.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?User
//    {
//        return $this->createQueryBuilder('u')
//            ->andWhere('u.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }

    /**
     * Récupère les clients fidèles de l'année en cours (au moins 2 rendez-vous)
     * @return User[]
     */
    public function findLoyalClientsCurrentYear(): array
    {
        $currentYear = (int) date('Y');
        $startOfYear = new \DateTime($currentYear . '-01-01 00:00:00');
        $endOfYear = new \DateTime($currentYear . '-12-31 23:59:59');

        return $this->createQueryBuilder('u')
            ->innerJoin('u.rendezvouses', 'r')
            ->where('u.roles NOT LIKE :admin_role')
            ->andWhere('r.day BETWEEN :start AND :end')
            ->andWhere('r.status IN (:valid_statuses)')
            ->setParameter('admin_role', '%ROLE_ADMIN%')
            ->setParameter('start', $startOfYear)
            ->setParameter('end', $endOfYear)
            ->setParameter('valid_statuses', ['Rendez-vous pris', 'Rendez-vous confirmé'])
            ->groupBy('u.id')
            ->having('COUNT(r.id) >= 2')
            ->orderBy('u.Prenom', 'ASC')
            ->addOrderBy('u.Nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les clients à reconquérir (actifs année précédente, inactifs année en cours)
     * @return User[]
     */
    public function findClientsToReconquer(): array
    {
        $currentYear = (int) date('Y');
        $previousYear = $currentYear - 1;

        $startOfPreviousYear = new \DateTime($previousYear . '-01-01 00:00:00');
        $endOfPreviousYear = new \DateTime($previousYear . '-12-31 23:59:59');
        $startOfCurrentYear = new \DateTime($currentYear . '-01-01 00:00:00');
        $endOfCurrentYear = new \DateTime($currentYear . '-12-31 23:59:59');

        // Sous-requête pour compter les RDV de l'année en cours
        $qb = $this->createQueryBuilder('u');
        $qbCurrentYear = $this->getEntityManager()->createQueryBuilder()
            ->select('COUNT(r2.id)')
            ->from('App\Entity\Rendezvous', 'r2')
            ->where('r2.user = u.id')
            ->andWhere('r2.day BETWEEN :start_current AND :end_current')
            ->andWhere('r2.status IN (:valid_statuses)')
            ->getDQL();

        return $qb
            ->innerJoin('u.rendezvouses', 'r')
            ->where('u.roles NOT LIKE :admin_role')
            ->andWhere('r.day BETWEEN :start_previous AND :end_previous')
            ->andWhere('r.status IN (:valid_statuses)')
            ->andWhere('(' . $qbCurrentYear . ') <= 1')
            ->setParameter('admin_role', '%ROLE_ADMIN%')
            ->setParameter('start_previous', $startOfPreviousYear)
            ->setParameter('end_previous', $endOfPreviousYear)
            ->setParameter('start_current', $startOfCurrentYear)
            ->setParameter('end_current', $endOfCurrentYear)
            ->setParameter('valid_statuses', ['Rendez-vous pris', 'Rendez-vous confirmé'])
            ->groupBy('u.id')
            ->having('COUNT(r.id) >= 2')
            ->orderBy('u.Prenom', 'ASC')
            ->addOrderBy('u.Nom', 'ASC')
            ->getQuery()
            ->getResult();
    }
}

<?php

namespace App\Repository;

use App\Entity\Client;
use App\Entity\Commande;
use App\Entity\Enseigne;
use App\Entity\Orders;
use App\Entity\PgmEnrolement;
use App\Entity\Utilisateur;
use App\Toolbox\StatutEnrolementEnum;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Orders>
 *
 * @method Orders|null find($id, $lockMode = null, $lockVersion = null)
 * @method Orders|null findOneBy(array $criteria, array $orderBy = null)
 * @method Orders[]    findAll()
 * @method Orders[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class OrdersRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Orders::class);
    }

    public function save(Orders $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Orders $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return Commande[] Returns an array of Commande objects
     */
    public function findByCheckStatus(): array
    {
        return $this->createQueryBuilder('c')
            ->orWhere('c.dernierStatut = ' . StatutEnrolementEnum::DemandeEnrolement->value)
            ->orWhere('c.dernierStatut = ' . StatutEnrolementEnum::DemandeAnnulation->value)
            ->orWhere('c.dernierStatut = ' . StatutEnrolementEnum::DemandeRetour->value)
            ->orWhere('c.dernierStatut = ' . StatutEnrolementEnum::CheckStatut->value)
            ->orWhere('c.dernierStatut = ' . StatutEnrolementEnum::DemandeControleOrdre->value)
            ->orderBy('c.id', 'ASC')
//            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
            ;
    }

    /**
     * @return Commande[] Returns an array of Commande objects
     */
    public function getLastOrders(Client $client): array
    {
        $result=$this->createQueryBuilder('c')
            ->andWhere('c.client = :val')
            ->setParameter('val', $client)
            ->orderBy('c.id','DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();
        return $result;

    }

        /**
     * @return Commande[] Returns an array of Commande objects
     */
    public function getLastOrdersByUtilisateur(Utilisateur $utilisateur): array
    {
        $result=$this->createQueryBuilder('c')
            ->andWhere('c.estCreePar = :val')
            ->setParameter('val', $utilisateur)
            ->orderBy('c.id','DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();
        return $result;

    }


    /**
     * @return Commande[] Returns an array of Commande objects
     */
    public function getTopOrders(int $max = 25): array
    {
        $result=$this->createQueryBuilder('c')
            ->orderBy('c.id','DESC')
            ->setMaxResults($max)
            ->getQuery()
            ->getResult();
        return $result;

    }

    /**
     * @return Orders[] Returns an array of Orders objects
     */
    public function findByDates(\DateTimeInterface $dateDebut,\DateTimeInterface $dateFin): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.dateCreation >= :dateDebut')
            ->andWhere('o.dateCreation < :dateFin')
            ->setParameter('dateDebut', $dateDebut)
            ->setParameter('dateFin', $dateFin)
            ->getQuery()
            ->getResult()
        ;
    }


//    public function findOneBySomeField($value): ?Orders
//    {
//        return $this->createQueryBuilder('o')
//            ->andWhere('o.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}

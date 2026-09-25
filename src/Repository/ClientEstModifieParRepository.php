<?php

namespace App\Repository;

use App\Entity\ClientEstModifiePar;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ClientEstModifiePar>
 *
 * @method ClientEstModifiePar|null find($id, $lockMode = null, $lockVersion = null)
 * @method ClientEstModifiePar|null findOneBy(array $criteria, array $orderBy = null)
 * @method ClientEstModifiePar[]    findAll()
 * @method ClientEstModifiePar[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ClientEstModifieParRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClientEstModifiePar::class);
    }

    public function save(ClientEstModifiePar $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ClientEstModifiePar $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return ClientEstModifiePar[] Returns an array of ClientEstModifiePar objects
    */
    public function findLastByUtilisateur(\App\Entity\Utilisateur $utilisateur): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.utilisateur = :val')
            ->setParameter('val', $utilisateur)
            ->orderBy('c.id', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }

//    public function findOneBySomeField($value): ?ClientEstModifiePar
//    {
//        return $this->createQueryBuilder('c')
//            ->andWhere('c.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}

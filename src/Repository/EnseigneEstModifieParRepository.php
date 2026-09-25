<?php

namespace App\Repository;

use App\Entity\EnseigneEstModifiePar;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EnseigneEstModifiePar>
 *
 * @method EnseigneEstModifiePar|null find($id, $lockMode = null, $lockVersion = null)
 * @method EnseigneEstModifiePar|null findOneBy(array $criteria, array $orderBy = null)
 * @method EnseigneEstModifiePar[]    findAll()
 * @method EnseigneEstModifiePar[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EnseigneEstModifieParRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EnseigneEstModifiePar::class);
    }

    public function save(EnseigneEstModifiePar $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(EnseigneEstModifiePar $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

//    /**
//     * @return EnseigneEstModifiePar[] Returns an array of EnseigneEstModifiePar objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('e')
//            ->andWhere('e.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('e.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?EnseigneEstModifiePar
//    {
//        return $this->createQueryBuilder('e')
//            ->andWhere('e.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}

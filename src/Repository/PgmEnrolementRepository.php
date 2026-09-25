<?php

namespace App\Repository;

use App\Entity\PgmEnrolement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PgmEnrolement>
 *
 * @method PgmEnrolement|null find($id, $lockMode = null, $lockVersion = null)
 * @method PgmEnrolement|null findOneBy(array $criteria, array $orderBy = null)
 * @method PgmEnrolement[]    findAll()
 * @method PgmEnrolement[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PgmEnrolementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PgmEnrolement::class);
    }

    public function save(PgmEnrolement $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(PgmEnrolement $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

//    /**
//     * @return PgmEnrolement[] Returns an array of PgmEnrolement objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('p.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?PgmEnrolement
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}

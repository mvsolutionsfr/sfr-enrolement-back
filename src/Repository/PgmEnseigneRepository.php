<?php

namespace App\Repository;

use App\Entity\PgmEnseigne;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PgmEnseigne>
 *
 * @method PgmEnseigne|null find($id, $lockMode = null, $lockVersion = null)
 * @method PgmEnseigne|null findOneBy(array $criteria, array $orderBy = null)
 * @method PgmEnseigne[]    findAll()
 * @method PgmEnseigne[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PgmEnseigneRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PgmEnseigne::class);
    }

    public function save(PgmEnseigne $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(PgmEnseigne $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByPgmByResellerId($resellerId,$pgm): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.resellerId = :resellerId')
            ->setParameter('resellerId', $resellerId)
            ->andWhere('p.pgmEnrolement = :pgm')
            ->setParameter('pgm', $pgm)
            ->orderBy('p.id', 'ASC')
            ->getQuery()
            ->getResult()
            ;
    }

//    /**
//     * @return PgmEnseigne[] Returns an array of PgmEnseigne objects
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

//    public function findOneBySomeField($value): ?PgmEnseigne
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}

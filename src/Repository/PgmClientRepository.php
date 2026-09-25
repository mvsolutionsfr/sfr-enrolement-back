<?php

namespace App\Repository;

use App\Entity\PgmClient;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PgmClient>
 *
 * @method PgmClient|null find($id, $lockMode = null, $lockVersion = null)
 * @method PgmClient|null findOneBy(array $criteria, array $orderBy = null)
 * @method PgmClient[]    findAll()
 * @method PgmClient[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PgmClientRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PgmClient::class);
    }

    public function save(PgmClient $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(PgmClient $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }


    public function findByClientByPgm($client,$pgm): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.client = :client')
            ->setParameter('client', $client)
            ->andWhere('p.pgmEnrolement = :pgm')
            ->setParameter('pgm', $pgm)
            ->orderBy('p.id', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

//    public function findOneBySomeField($value): ?PgmClient
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}

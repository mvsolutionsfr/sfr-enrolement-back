<?php

namespace App\Repository;

use App\Entity\Terminal;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Terminal>
 *
 * @method Terminal|null find($id, $lockMode = null, $lockVersion = null)
 * @method Terminal|null findOneBy(array $criteria, array $orderBy = null)
 * @method Terminal[]    findAll()
 * @method Terminal[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TerminalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Terminal::class);
    }

    public function save(Terminal $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Terminal $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return Terminal[] Returns an array of Terminal objects
     */
    public function findAllByClient($client): array
    {
        return $this->createQueryBuilder('t')
            ->orWhere('t. LIKE :imei')
            ->setParameter('imei', $value."%")
            ->orWhere('t.numeroSerie LIKE :sn')
            ->setParameter('sn', $value."%")
            ->andWhere('t.programme = :pgm')
            ->setParameter('pgm', $pgm)
            ->orderBy('t.numeroIMEI', 'ASC')
//            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
            ;
    }

    /**
     * @return Terminal[] Returns an array of Terminal objects
     */
    public function findAllByIMEIOrSerie($value,$pgm): array
    {
        return $this->createQueryBuilder('t')
            ->orWhere('t.numeroIMEI LIKE :imei')
            ->setParameter('imei', $value."%")
            ->orWhere('t.numeroSerie LIKE :sn')
            ->setParameter('sn', $value."%")
            ->andWhere('t.programme = :pgm')
            ->setParameter('pgm', $pgm)
            ->orderBy('t.numeroIMEI', 'ASC')
//            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }

    public function findByIMEIOrSerie($value): ?Terminal
    {
        $res = $this->createQueryBuilder('t')
            ->orWhere('t.numeroIMEI = :imei')
            ->setParameter('imei', $value)
            ->orWhere('t.numeroSerie = :sn')
            ->setParameter('sn', $value)
            ->getQuery()
            ->getResult()
            ;
        if ($res && count($res)>0)
            return $res[0];
        else
            return null;
    }

//    public function findOneBySomeField($value): ?Terminal
//    {
//        return $this->createQueryBuilder('t')
//            ->andWhere('t.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}

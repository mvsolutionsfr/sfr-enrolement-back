<?php

namespace App\Repository;

use App\Entity\Enrolement;
use App\Entity\Enseigne;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\Query\AST\Functions\CurrentDateFunction;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Enrolement>
 *
 * @method Enrolement|null find($id, $lockMode = null, $lockVersion = null)
 * @method Enrolement|null findOneBy(array $criteria, array $orderBy = null)
 * @method Enrolement[]    findAll()
 * @method Enrolement[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EnrolementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Enrolement::class);
    }

    public function save(Enrolement $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Enrolement $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findTerminauxByClient($client, $pgmEnrolement): array
    {

        $qb = $this->createQueryBuilder('e');
        $res = $qb
            ->andwhere('e.client =  :client')
            ->andWhere('t.programme = :pgm')
            ->leftJoin('e.terminal', 't')
            ->addSelect('t')
            ->setParameter('client', $client)
            ->setParameter('pgm', $pgmEnrolement)
            ->getQuery()
            ->getResult();
        return $res;
    }

    public function findPaginatorTerminauxByClient($client, $pgmEnrolement, $page, $maxResult): Paginator
    {

        return new Paginator(
            $this->createQueryBuilder('e')
                ->andwhere('e.client =  :client')
                ->andWhere('t.programme = :pgm')
                ->leftJoin('e.terminal', 't')
                ->addSelect('t')
                ->addOrderBy('e.date','DESC')
                ->setParameter('client', $client)
                ->setParameter('pgm', $pgmEnrolement)
                ->setFirstResult(($page - 1) * $maxResult)
                ->setMaxResults($maxResult)
                ->getQuery()
                ->setHydrationMode(Query::HYDRATE_ARRAY)
                ->setHint(Paginator::HINT_ENABLE_DISTINCT, false)
        );
    }

    public function findTodayByClients($clients, $pgmEnrolement): array
    {
        $datetimejour = new \DateTime('now');
        $datetimejour->setTime(0, 0, 0);

        $qb = $this->createQueryBuilder('e');
        return $qb
            ->andwhere('e.client IN ( :clients)')
            ->andwhere('e.date >= :datetimejour')
            ->andWhere('t.programme = :pgm')
            ->leftJoin('e.terminal', 't')
            ->addSelect('t')
            ->setParameter('clients', $clients)
            ->setParameter('datetimejour', $datetimejour)
            ->setParameter('pgm', $pgmEnrolement)
            ->select('COUNT(e)')
            ->getQuery()
            ->getResult();

    }

    public function topClientAnnee($clients, $pgmEnrolement): array
    {
        $datetimejour = new \DateTime('now');
        $datetimejour->setTime(0, 0, 0);
        $datetimejour->sub(new \DateInterval("P1Y"));
        $qb = $this->createQueryBuilder('e');
        /** @var Query $res */
        $res = $qb
            ->andwhere('e.client IN ( :clients)')
            ->andwhere('e.date >= :datetimejour')
            ->andWhere('t.programme = :pgm')
            ->leftJoin('e.terminal', 't')
            ->innerJoin('e.client', 'c')
            ->addSelect('t')
            ->setParameter('clients', $clients)
            ->setParameter('datetimejour', $datetimejour)
            ->setParameter('pgm', $pgmEnrolement)
            ->addGroupBy('c.id')
            ->addOrderBy('COUNT(c)', 'DESC')
            ->select('c.raisonSociale , COUNT(c) as qte')
            ->setMaxResults(10)
            ->getQuery();
        $sql = $res->getSQL();
        //  dd($sql);
        return $res->getResult();
    }


    public function findEnrolementByDates(\DateTimeInterface $dateDebut,\DateTimeInterface $dateFin): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.date >= :dateDebut')
            ->andWhere('o.date < :dateFin')
            ->setParameter('dateDebut', $dateDebut)
            ->setParameter('dateFin', $dateFin)
            ->getQuery()
            ->getResult()
            ;
    }
    public function findDesenrolementByDates(\DateTimeInterface $dateDebut,\DateTimeInterface $dateFin): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.dateFin >= :dateDebut')
            ->andWhere('o.dateFin < :dateFin')
            ->setParameter('dateDebut', $dateDebut)
            ->setParameter('dateFin', $dateFin)
            ->getQuery()
            ->getResult()
            ;
    }

//    /**
//     * @return Enrolement[] Returns an array of Enrolement objects
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

//    public function findOneBySomeField($value): ?Enrolement
//    {
//        return $this->createQueryBuilder('e')
//            ->andWhere('e.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}

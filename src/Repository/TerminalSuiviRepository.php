<?php

namespace App\Repository;

use Doctrine\ORM\Query;
use Doctrine\ORM\Tools\Pagination\Paginator;
use App\Entity\OrderTransaction;
use App\Entity\TerminalSuivi;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TerminalSuivi>
 *
 * @method TerminalSuivi|null find($id, $lockMode = null, $lockVersion = null)
 * @method TerminalSuivi|null findOneBy(array $criteria, array $orderBy = null)
 * @method TerminalSuivi[]    findAll()
 * @method TerminalSuivi[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TerminalSuiviRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TerminalSuivi::class);
    }

    public function save(TerminalSuivi $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(TerminalSuivi $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByTransactionPaginator(OrderTransaction $orderTransaction, int $page, int $maxresult, string $clientId = ""): Paginator
    {
        $paginator = new Paginator($this->createQueryBuilder('c')
            ->andWhere('c.transaction = :orderTransaction')
            ->setParameter('orderTransaction', $orderTransaction)
            ->leftJoin('c.terminal', 't')
            ->select('t.numeroIMEI,t.numeroSerie, c.message, c.statut')
            ->setFirstResult(($page - 1) * $maxresult)
            ->setMaxResults($maxresult)
            ->getQuery()
            ->setHydrationMode(Query::HYDRATE_ARRAY)
            ->setHint(Paginator::HINT_ENABLE_DISTINCT, false));

        $paginator->setUseOutputWalkers(false);
        return $paginator;

    }

}

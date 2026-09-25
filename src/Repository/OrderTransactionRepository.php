<?php

namespace App\Repository;

use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\Query;
use Doctrine\ORM\Tools\Pagination\Paginator;
use App\Entity\Client;
use App\Entity\Enseigne;
use App\Entity\Orders;
use App\Entity\OrderTransaction;
use App\Entity\PgmEnrolement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OrderTransaction>
 *
 * @method OrderTransaction|null find($id, $lockMode = null, $lockVersion = null)
 * @method OrderTransaction|null findOneBy(array $criteria, array $orderBy = null)
 * @method OrderTransaction[]    findAll()
 * @method OrderTransaction[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class OrderTransactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrderTransaction::class);
    }

    public function save(OrderTransaction $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(OrderTransaction $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findLastTransactionByCommande(Orders $commande): ?OrderTransaction
    {
        $result = $this->createQueryBuilder('c')
            ->andWhere('c.orders = :val')
            ->setParameter('val', $commande)
            ->orderBy('c.id', 'DESC')
            ->getQuery()
            ->getResult();
        if ($result) $result = $result[0];
        return $result;
    }

    public function findFirstTransactionWithTransactionIdByCommande(Orders $commande): ?OrderTransaction
    {
        $result = $this->createQueryBuilder('c')
            ->andWhere('c.orders = :val')
            ->andWhere('c.transactionId IS NOT NULL')
            ->setParameter('val', $commande)
            ->orderBy('c.id', 'ASC')
            ->getQuery()
            ->getResult();
        if ($result) $result = $result[0];
        return $result;
    }


    public function findLastTransactionsByClient(Client $client, ?PgmEnrolement $pgmEnrolement): array
    {
        $result = null;
        if (!$pgmEnrolement)
            $result = $this->createQueryBuilder('c')
                ->andWhere('o.client = :client')
                ->andWhere('c.lastTransacOnType = true')
                ->leftJoin('c.orders', 'o')
                ->leftJoin('o.pgmEnrolement', 'p')
                ->leftJoin('c.terminalSuivis', 't')
                ->setParameter('client', $client)
                ->addGroupBy('o.id')
                ->addGroupBy('c.id')
                ->addGroupBy('p.id')
                ->orderBy('c.dateCreation', 'DESC')
                ->select('o.id , c.dateCreation,o.customerId, o.ref, c.typeTransaction, c.statut, c.statutTransacPgm, p.libelle as pgm, COUNT(t) as nb')
                ->setMaxResults(10)
                ->getQuery()
                ->getResult();
        else
            $result = $this->createQueryBuilder('c')
                ->andWhere('o.client = :client')
                ->andWhere('c.lastTransacOnType = true')
                ->andWhere('o.pgmEnrolement = :pgm')
                ->leftJoin('c.orders', 'o')
                ->leftJoin('c.terminalSuivis', 't')
                ->setParameter('client', $client)
                ->setParameter('pgm', $pgmEnrolement)
                ->addGroupBy('o.id')
                ->addGroupBy('c.id')
                ->orderBy('c.dateCreation', 'DESC')
                ->select('c.id , c.dateCreation,o.customerId, o.ref,c.typeTransaction, c.statut, c.statutTransacPgm, COUNT(t) as nb')
                ->setMaxResults(10)
                ->getQuery()
                ->getResult();

        return $result;
    }

    public function findLastTransactionsByClientPaginator(Client $client, $page, $maxResult): Paginator
    {
        $paginator = new Paginator($this->createQueryBuilder('c')
            ->andWhere('o.client = :client')
            ->andWhere('c.lastTransacOnType = true')
            ->leftJoin('c.orders', 'o')
            ->leftJoin('o.pgmEnrolement', 'p')
            ->innerJoin('c.terminalSuivis', 't')
            ->setParameter('client', $client)
            ->addGroupBy('o.id')
            ->addGroupBy('c.id')
            ->addGroupBy('p.id')
            ->orderBy('c.dateCreation', 'DESC')
            ->select('o.id , c.dateCreation,o.customerId, c.typeTransaction, c.statut, c.statutTransacPgm, p.libelle as pgm, COUNT(t) as nb')
            ->setFirstResult(($page - 1) * $maxResult)
            ->setMaxResults($maxResult)
            ->getQuery()
            ->setHydrationMode(AbstractQuery::HYDRATE_ARRAY)
            ->setHint(Paginator::HINT_ENABLE_DISTINCT, false));

        $paginator->setUseOutputWalkers(false);
        return $paginator;
    }

    public function findLastTransactionsByEnseigne2(Enseigne $enseigne, PgmEnrolement $pgmEnrolement, int $page, int $maxresult, string $clientId = ""): array
    {
//la raison sociale du client
//le customerId
//la date de la transaction
//la transaction
//le type de transaction
//l'utilisateur
//le statut de la transaction
//l'id order (commande)
//le nombre d'appareils

        if ($clientId == "") {
            $result = $this->createQueryBuilder('c')
                ->andWhere('o.pgmEnrolement = :pgm')
                ->andWhere('o.enseigne = :enseigne')
                ->andWhere('c.lastTransacOnType = True')
                ->leftJoin('c.orders', 'o')
                ->leftJoin('o.client', 't')
                ->leftJoin('c.estCreePar', 'u')
                ->leftJoin('c.terminalSuivis', 's')
                ->setParameter('enseigne', $enseigne)
                ->setParameter('pgm', $pgmEnrolement)
                ->addGroupBy('t.id')
                ->addGroupBy('u.id')
                ->addGroupBy('o.id')
                ->addGroupBy('c.id')
                ->orderBy('c.dateCreation', 'DESC')
                ->select('t.raisonSociale as raisonSociale, u.nom as nom, u.prenom as prenom, c.id as transacId, o.id as orderid, c.dateCreation as  dateCreation ,o.customerId as customerId, c.typeTransaction, c.statut, COUNT(s) as nbTerminaux')
                ->setFirstResult(($page - 1) * $maxresult)
                ->setMaxResults($maxresult)
                ->getQuery()
                ->getResult();
        } else {
            $result = $this->createQueryBuilder('c')
                ->andWhere('t.id = :clientId')
                ->andWhere('o.pgmEnrolement = :pgm')
                ->andWhere('o.enseigne = :enseigne')
                ->andWhere('c.lastTransacOnType = True')
                ->leftJoin('c.orders', 'o')
                ->leftJoin('o.client', 't')
                ->leftJoin('c.estCreePar', 'u')
                ->leftJoin('c.terminalSuivis', 's')
                ->setParameter('clientId', $clientId)
                ->setParameter('enseigne', $enseigne)
                ->setParameter('pgm', $pgmEnrolement)
                ->addGroupBy('t.id')
                ->addGroupBy('u.id')
                ->addGroupBy('o.id')
                ->addGroupBy('c.id')
                ->orderBy('c.dateCreation', 'DESC')
                ->select('t.raisonSociale as raisonSociale, u.nom as nom, u.prenom as prenom, c.id as transacId, o.id as orderId, c.dateCreation as dateCreation,o.customerId as customerId, c.typeTransaction as typeTransaction, c.statut as statut, COUNT(s) as nbTerminaux')
                ->setFirstResult(($page - 1) * $maxresult)
                ->setMaxResults($maxresult)
                ->getQuery()
                ->getResult();
        }
        return $result;
    }

    public function findLastTransactionsByEnseigne(Enseigne $enseigne, PgmEnrolement $pgmEnrolement, int $page, int $maxresult, string $clientId = ""): Paginator
    {
//la raison sociale du client
//le customerId
//la date de la transaction
//la transaction
//le type de transaction
//l'utilisateur
//le statut de la transaction
//l'id order (commande)
//le nombre d'appareils

        if ($clientId == "") {
            $paginator = new Paginator($this->createQueryBuilder('c')
                ->andWhere('o.pgmEnrolement = :pgm')
                ->andWhere('o.enseigne = :enseigne')
                ->andWhere('c.lastTransacOnType = True')
                ->leftJoin('c.orders', 'o')
                ->leftJoin('o.client', 't')
                ->leftJoin('c.estCreePar', 'u')
                ->leftJoin('c.terminalSuivis', 's')
                ->setParameter('enseigne', $enseigne)
                ->setParameter('pgm', $pgmEnrolement)
                ->addGroupBy('t.id')
                ->addGroupBy('u.id')
                ->addGroupBy('o.id')
                ->addGroupBy('c.id')
                ->orderBy('c.dateCreation', 'DESC')
                ->select('t.raisonSociale as raisonSociale, o.ref as ref, u.nom as nom, u.prenom as prenom, c.id as transacId, o.id as orderid, c.dateCreation as  dateCreation ,o.customerId as customerId, c.typeTransaction, c.statut, COUNT(s) as nbTerminaux')
                ->setFirstResult(($page - 1) * $maxresult)
                ->setMaxResults($maxresult)
                ->getQuery()
                ->setHydrationMode(AbstractQuery::HYDRATE_ARRAY)
                ->setHint(Paginator::HINT_ENABLE_DISTINCT, false));
        } else {
            $paginator = new Paginator($this->createQueryBuilder('c')
                ->andWhere('t.id = :clientId')
                ->andWhere('o.pgmEnrolement = :pgm')
                ->andWhere('o.enseigne = :enseigne')
                ->andWhere('c.lastTransacOnType = True')
                ->leftJoin('c.orders', 'o')
                ->leftJoin('o.client', 't')
                ->leftJoin('c.estCreePar', 'u')
                ->leftJoin('c.terminalSuivis', 's')
                ->setParameter('clientId', $clientId)
                ->setParameter('enseigne', $enseigne)
                ->setParameter('pgm', $pgmEnrolement)
                ->addGroupBy('t.id')
                ->addGroupBy('u.id')
                ->addGroupBy('o.id')
                ->addGroupBy('c.id')
                ->orderBy('c.dateCreation', 'DESC')
                ->select('t.raisonSociale as raisonSociale, o.ref as ref,u.nom as nom, u.prenom as prenom, c.id as transacId, o.id as orderId, c.dateCreation as dateCreation,o.customerId as customerId, c.typeTransaction as typeTransaction, c.statut as statut, COUNT(s) as nbTerminaux')
                ->setFirstResult(($page - 1) * $maxresult)
                ->setMaxResults($maxresult)
                ->getQuery()
               ->setHydrationMode(AbstractQuery::HYDRATE_ARRAY)
                ->setHint(Paginator::HINT_ENABLE_DISTINCT, false));
        }
        $paginator->setUseOutputWalkers(false);
        return $paginator;
    }

    public function findLastTransactionsEnErreurByEnseigne(Enseigne $enseigne, PgmEnrolement $pgmEnrolement, int $page, int $maxresult, string $clientId): Paginator
    {
//la raison sociale du client
//le customerId
//la date de la transaction
//la transaction
//le type de transaction
//l'utilisateur
//le statut de la transaction
//l'id order (commande)
//le nombre d'appareils

        $paginator = new Paginator($this->createQueryBuilder('c')
            ->andWhere('t.id = :clientId')
            ->andWhere('o.pgmEnrolement = :pgm')
            ->andWhere('o.enseigne = :enseigne')
            ->andWhere('c.lastTransacOnType = True')
            ->andWhere('c.statut = 5 or c.statut = 3')
            ->leftJoin('c.orders', 'o')
            ->leftJoin('o.client', 't')
            ->leftJoin('c.estCreePar', 'u')
            ->innerJoin('c.terminalSuivis', 's')
            ->setParameter('clientId', $clientId)
            ->setParameter('enseigne', $enseigne)
            ->setParameter('pgm', $pgmEnrolement)
            ->addGroupBy('t.id')
            ->addGroupBy('u.id')
            ->addGroupBy('o.id')
            ->addGroupBy('c.id')
            ->orderBy('c.dateCreation', 'DESC')
            ->select('t.raisonSociale as raisonSociale, u.nom as nom, u.prenom as prenom, c.id as transacId, o.id as orderId, c.dateCreation as dateCreation,o.customerId as customerId, c.typeTransaction as typeTransaction, c.statut as statut, COUNT(s) as nbTerminaux')
            ->setFirstResult(($page - 1) * $maxresult)
            ->setMaxResults($maxresult)
            ->getQuery()
            ->setHydrationMode(Query::HYDRATE_ARRAY)
            ->setHint(Paginator::HINT_ENABLE_DISTINCT, false));
        $paginator->setUseOutputWalkers(false);
        return $paginator;
    }

    public function __findLastTransactionsByEnseigne(Enseigne $enseigne, PgmEnrolement $pgmEnrolement, string $clientId = ""): array
    {
//la raison sociale du client
//le customerId
//la date de la transaction
//la transaction
//le type de transaction
//l'utilisateur
//le statut de la transaction
//l'id order (commande)
//le nombre d'appareils

        if ($clientId == "") {
            $result = $this->createQueryBuilder('c')
                ->andWhere('o.pgmEnrolement = :pgm')
                ->andWhere('o.enseigne = :enseigne')
                ->andWhere('c.lastTransacOnType = True')
                ->leftJoin('c.orders', 'o')
                ->leftJoin('o.client', 't')
                ->leftJoin('c.estCreePar', 'u')
                ->innerJoin('c.terminalSuivis', 's')
                ->setParameter('enseigne', $enseigne)
                ->setParameter('pgm', $pgmEnrolement)
                ->addGroupBy('t.id')
                ->addGroupBy('u.id')
                ->addGroupBy('o.id')
                ->addGroupBy('c.id')
                ->orderBy('c.dateCreation', 'DESC')
                ->select('t.raisonSociale, u.nom, u.prenom, c.id as transacId, o.id as orderid, c.dateCreation,o.customerId, c.typeTransaction, c.statut, COUNT(s) as nbTerminaux')
                ->setMaxResults(100)
                ->getQuery()
                ->getResult();
        } else {
            $result = $this->createQueryBuilder('c')
                ->andWhere('t.id = :clientId')
                ->andWhere('o.pgmEnrolement = :pgm')
                ->andWhere('o.enseigne = :enseigne')
                ->andWhere('c.lastTransacOnType = True')
                ->leftJoin('c.orders', 'o')
                ->leftJoin('o.client', 't')
                ->leftJoin('c.estCreePar', 'u')
                ->innerJoin('c.terminalSuivis', 's')
                ->setParameter('clientId', $clientId)
                ->setParameter('enseigne', $enseigne)
                ->setParameter('pgm', $pgmEnrolement)
                ->addGroupBy('t.id')
                ->addGroupBy('u.id')
                ->addGroupBy('o.id')
                ->addGroupBy('c.id')
                ->orderBy('c.dateCreation', 'DESC')
                ->select('t.raisonSociale, u.nom, u.prenom, c.id as transacId, o.id as orderId, c.dateCreation,o.customerId, c.typeTransaction, c.statut, COUNT(s) as nbTerminaux')
                ->setMaxResults(100)
                ->getQuery()
                ->getResult();
        }
        return $result;
    }

    public function getTransaction(string $transactionId): array
    {
//la raison sociale du client
//le customerId
//la date de la transaction
//la transaction
//le type de transaction
//l'utilisateur
//le statut de la transaction
//l'id order (commande)
//le nombre d'appareils

        $result = $this->createQueryBuilder('c')
            ->andWhere('c.id = :transactionId')
            ->leftJoin('c.orders', 'o')
            ->leftJoin('o.client', 't')
            ->leftJoin('c.estCreePar', 'u')
            ->innerJoin('c.terminalSuivis', 's')
            ->setParameter('transactionId', $transactionId)
            ->addGroupBy('t.id')
            ->addGroupBy('u.id')
            ->addGroupBy('o.id')
            ->addGroupBy('c.id')
            ->orderBy('c.dateCreation', 'DESC')
            ->select('t.raisonSociale, u.nom, u.prenom, c.id as transacId, o.id as orderid, c.dateCreation,o.customerId, c.typeTransaction, c.statut, COUNT(s) as nbTerminaux')
            ->setMaxResults(1)
            ->getQuery()
            ->getResult();
        return $result;
    }

    public function findByOrderSortByDate(Orders $order): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.orders = :order')
            ->setParameter('order', $order)
            ->orderBy('c.dateCreation', 'ASC')
            ->getQuery()
            ->getResult();
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


}

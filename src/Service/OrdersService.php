<?php

namespace App\Service;

use App\Entity\Orders;
use App\Entity\OrderTransaction;
use App\Repository\OrderTransactionRepository;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;

class OrdersService extends AbstractService
{
    private $utc;

    public function __construct(ManagerRegistry $doctrine, LoggerESService $logger, SessionService $session)
    {
        parent::__construct($doctrine,$logger, $session);
    }

    public function getOrder(int $orderId): ?Orders
    {
        $repository = $this->doctrine->getRepository(Orders::class);
        return $repository->find($orderId);
    }

    public function getLastOrderTransaction(Orders $order) : ?OrderTransaction
    {
        /** @var OrderTransactionRepository $repository */
        $repository = $this->doctrine->getRepository(OrderTransaction::class);
        return $repository->findLastTransactionByCommande($order);
    }
}
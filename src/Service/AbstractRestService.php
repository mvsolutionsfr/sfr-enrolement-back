<?php

namespace App\Service;

use App\Entity\Orders;
use App\Toolbox\TypeEnrolementEnum;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use App\Toolbox\StatutRequeteEnum;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

//use function Sodium\add;

abstract class AbstractRestService extends AbstractService
{
    protected MetricsService $metricsService;

    public function __construct(?ManagerRegistry $doctrine,LoggerESService $logger,SessionService $session, MetricsService $metricsService)
    {
        parent::__construct($doctrine,$logger,$session);
        $this->metricsService = $metricsService;
    }

    public function enrolement(string $depResellerId, string $customerId, Orders $order, array $terminaux, string $transactionId, ?\DateTimeInterface $dateCreation = null ): ?array
    {
        return $this->processAppel(TypeEnrolementEnum::Inscription,$depResellerId,$customerId, $order,"", $terminaux, $transactionId, $dateCreation );
    }

    public function retour(string $depResellerId, string $customerId, Orders $order, string $oldOrderNumber, array $terminaux, string $transactionId, ?\DateTimeInterface $dateCreation = null ): ?array
    {
        return $this->processAppel(TypeEnrolementEnum::Desinscription,$depResellerId,$customerId, $order, $oldOrderNumber, $terminaux,$transactionId, $dateCreation );
    }

    public function annulation(string $depResellerId, string $customerId, Orders $order, string $removeTransactionId, array $terminaux, ?\DateTimeInterface $dateCreation = null  ): ?array
    {
        return $this->processAppel(TypeEnrolementEnum::Annulation,$depResellerId,$customerId, $order, $removeTransactionId, $terminaux,"", $dateCreation );
    }

    public function checkorder(string $depResellerId, string $customerId, Orders $order, string $removeTransactionId, array $terminaux ): ?array
    {
        return $this->processAppel(TypeEnrolementEnum::Synchro,$depResellerId,$customerId, $order, $removeTransactionId, $terminaux,"" );
    }

    abstract protected function processAppel(TypeEnrolementEnum $orderType, string $vendorId, string $customerId, Orders $order, string $removeTransactionId , array $terminaux, string $transactionId, ?\DateTimeInterface $dateCreation = null): ?array ;
    abstract function checkTransactionStatus(string $vendorId, string $deviceEnrollmentTransactionId, ?Orders $order = null ): ?array;

}
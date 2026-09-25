<?php

namespace App\Service;

use App\Entity\Metric;
use App\Toolbox\MetricTypeEnum;
use App\Toolbox\MetricIndicatorEnum;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Class MetricsService
 */
class MetricsService  extends AbstractService
{

    public function __construct(ManagerRegistry $doctrine, LoggerESService $logger, SessionService $session)
    {
        parent::__construct($doctrine, $logger, $session);
    }

    public function createMetric(MetricIndicatorEnum $indicateur, ?string $programme, ?string $typeError = null, ?string $order = null) : Metric
    {

        $metric = new Metric();
        $metric->setType(MetricTypeEnum::Counter->name);
        $metric->setDate(date_create('now'));
        if ($order != null) $metric->setCommande($order);
        $metric->setNom($indicateur->name);
        $metric->setProgramme($programme);
        if ($typeError) $typeError = substr($typeError,0,255);
        if ($typeError != null) $metric->setLibelle($typeError);

        $entityManager = $this->doctrine->getManager();
        $entityManager->persist($metric);
        $entityManager->flush();
        return $metric;
    }
}
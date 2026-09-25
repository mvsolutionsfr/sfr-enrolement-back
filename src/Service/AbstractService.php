<?php

namespace App\Service;

use App\Entity\Orders;
use App\Toolbox\LogLevelEnum;
use App\Toolbox\SourceEnum;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

abstract class AbstractService
{
    protected ?ManagerRegistry $doctrine;
    protected LoggerESService $logger;
    protected ?SessionService $sessionService;

    protected function __construct(?ManagerRegistry $doctrine, LoggerESService $logger, ?SessionService $sessionService = null)
    {
        $this->doctrine = $doctrine;
        $this->logger = $logger;
        $this->sessionService = $sessionService;
    }

    public function writeLog(LogLevelEnum $level, string $functionName, string $message, SourceEnum $source = SourceEnum::BackService, ?Orders $numcmd = null )
    {
        $this->logger->writeLog($level,$functionName, $message, $source , $numcmd );
    }
}

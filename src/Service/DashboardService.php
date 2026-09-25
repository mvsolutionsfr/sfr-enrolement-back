<?php

namespace App\Service;

use App\Entity\Client;
use App\Entity\Enrolement;
use App\Entity\Enseigne;
use App\Entity\PgmClient;
use App\Entity\PgmEnrolement;
use App\Toolbox\CodeErreurEnum;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class DashboardService extends AbstractService
{
    private ?Enseigne $enseigne;
    private ?PgmEnrolement $pgmEnrolement;

    public function __construct(ManagerRegistry $doctrine, LoggerESService $logger, SessionService $sessionService)
    {
        parent::__construct($doctrine,$logger,$sessionService);
    }

    public function getTotalClients(): int
    {
        $retour = CodeErreurEnum::ok;
        $message = "";

        $this->pgmEnrolement = $this->sessionService->getProgramme();   // Pgm d'enrolement en cours
        $this->enseigne = $this->sessionService->getEnseigne();
        $nbClients = 0;

        if (!$this->pgmEnrolement || !$this->enseigne) {
            throw new \Exception("Contexte incohérent, programme ou enseigne non présent", CodeErreurEnum::ex->value);
        } else {
            $enseigne = $this->doctrine->getRepository(Enseigne::class)->find($this->enseigne->getId());
            $clients = $enseigne->getClients();
            $clientsPgm = $clients->filter(function ($value) {
                $pgmc = $value->getPgmEnrolements();
                $pgmc2 = $pgmc->filter(function ($value2) {
                    return $value2->getPgmEnrolement()->getLibelle() == $this->pgmEnrolement->getLibelle();
                });
                return $pgmc2->count() > 0;
            });
            if ($clientsPgm) $nbClients = $clientsPgm->count();
        }
        return $nbClients;
    }

    public function getNbEnrolementDuJour(): int
    {
        $nbEnrolementDuJour = 0;
        $this->pgmEnrolement = $this->sessionService->getProgramme();   // Pgm d'enrolement en cours
        $this->enseigne = $this->sessionService->getEnseigne();
        $this->enseigne = $this->doctrine->getRepository(Enseigne::class)->find($this->enseigne->getId());
        $clients = $this->enseigne->getClients();
        if ($clients) {
            $enrolementRepository = $this->doctrine->getRepository(Enrolement::class);
            $datetimejour = new \DateTime('now');
            $enrolementDuJour = $enrolementRepository->findTodayByClients($clients, $this->pgmEnrolement, $datetimejour);
            $nbEnrolementDuJour = $enrolementDuJour[0][1];
        }
        return $nbEnrolementDuJour;
    }

    public function topClientsAnnee() : array
    {
        $topClientsAnnee = array();
        $this->pgmEnrolement = $this->sessionService->getProgramme();   // Pgm d'enrolement en cours
        $this->enseigne = $this->sessionService->getEnseigne();
//        $this->enseigne = $this->doctrine->getRepository(Enseigne::class)->find($this->enseigne->getId());
        if ($this->pgmEnrolement == null || $this->enseigne == null)
            throw new \Exception("Enseigne ou programme enrolement non renseigné");
        $clients = $this->enseigne->getClients();
        if ($clients) {
            $enrolementRepository = $this->doctrine->getRepository(Enrolement::class);
            $topClientsAnnee = $enrolementRepository->topClientAnnee($clients, $this->pgmEnrolement);
        }
        return $topClientsAnnee;
    }


}
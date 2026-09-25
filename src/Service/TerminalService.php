<?php

namespace App\Service;

use App\Toolbox\StatutImeiEnum;
use Doctrine\Orm\Tools\Pagination\Paginator;
use App\Entity\Client;
use App\Entity\Enrolement;
use App\Entity\Enseigne;
use App\Entity\Fabricant;
use App\Entity\PgmClient;
use App\Entity\PgmEnrolement;
use App\Entity\Terminal;
use App\Toolbox\CodeErreurEnum;
use App\Toolbox\LogLevelEnum;
use App\Toolbox\SourceEnum;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class TerminalService extends AbstractService
{
    private Enseigne $enseigne;
    private PgmEnrolement $pgmEnrolement;

    public function __construct(ManagerRegistry $doctrine, LoggerESService $logger, SessionService $sessionService)
    {
        parent::__construct($doctrine, $logger, $sessionService);
    }

    public function getTerminaux(string $critere): ?array
    {
        $retour = CodeErreurEnum::ok;
        $message = "";

        $this->pgmEnrolement = $this->sessionService->getProgramme();   // Pgm d'enrolement en cours
        $this->enseigne = $this->sessionService->getEnseigne();
        $terminaux = null;

        if (!$this->pgmEnrolement || !$this->enseigne) {
            throw new \Exception("Contexte incohérent, programme ou enseigne non présent", CodeErreurEnum::ex->value);
        } else {
            $terminaux = $this->doctrine->getRepository(Terminal::class)->findAllByIMEIOrSerie($critere, $this->pgmEnrolement);
            $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "Retour Rechercher terminaux trouvés [" . count($terminaux) . "]", SourceEnum::BackService);
        }
        return $terminaux;
    }

    public function getTerminal(string $id): ?Terminal
    {
        return $this->doctrine->getRepository(Terminal::class)->find($id);
    }

    public function libereTerminal(Terminal $terminal): void
    {
        $terminal?->setStatut(StatutImeiEnum::Libre->value);
        $this->doctrine->getManager()->persist($terminal);
        $this->doctrine->getManager()->flush();
    }

    public function getFabricantByCode(string $code): ?Fabricant
    {
        $this->logger->writeLog(LogLevelEnum::Debug, __METHOD__ . "[" . __LINE__ . "]", "Recherche fabricant id " . $code, SourceEnum::BackService);
        $repository = $this->doctrine->getRepository(Fabricant::class);
        $resultat = $repository->findBy(["code" => $code]);
        $fabricant = null;
        if ($resultat) $fabricant = $resultat[0];
        return $fabricant;
    }

    public function getEnrolementsByClient(mixed $clientId, PgmEnrolement $pgmEnrolement, int $page, int $maxResult): Paginator
    {
        $retour = CodeErreurEnum::ok;
        $message = "";

        $this->enseigne = $this->sessionService->getEnseigne();
        $terminaux = null;

        $client = $this->doctrine->getRepository(Client::class)->find($clientId);
        $enrolements = $this->doctrine->getRepository(Enrolement::class)->findPaginatorTerminauxByClient($client, $pgmEnrolement, $page, $maxResult);
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "Retour Rechercher terminaux trouvés [" . $enrolements->count() . "]", SourceEnum::BackService);

        return $enrolements;
    }
}
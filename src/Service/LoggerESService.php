<?php

namespace App\Service;

use App\Entity\Log;
use App\Entity\Orders;
use App\Entity\PgmEnrolement;
use App\Toolbox\LogLevelEnum;
use App\Toolbox\ProgrammeEnrolementEnum;
use App\Toolbox\SourceEnum;
use DateTime;
use Doctrine\Persistence\ManagerRegistry;
use Elasticsearch\Client;
use Elasticsearch\ClientBuilder;
use Psr\Log\LoggerInterface;

class LoggerESService
{

    protected LoggerInterface $logger;
    protected SessionService $sessionService;
    protected static ?Client $elasticInstance = null;
    protected ManagerRegistry $doctrine;
    const ENROLEMENT_INDEX = "logs_enrolement";
    private $pgmApple;
    private $pgmKnox;
    private $pgmZT;

    public function __construct(LoggerInterface $logger, SessionService $sessionService, ManagerRegistry $doctrine )
    {
        $this->doctrine = $doctrine;
        $this->pgmApple = $this->doctrine->getRepository(PgmEnrolement::class)->find(ProgrammeEnrolementEnum::Apple->value);
        $this->pgmKnox = $this->doctrine->getRepository(PgmEnrolement::class)->find(ProgrammeEnrolementEnum::Knox->value);
        $this->pgmZT = $this->doctrine->getRepository(PgmEnrolement::class)->find(ProgrammeEnrolementEnum::Zerotouch->value);

        $this->logger = $logger;
        $this->sessionService = $sessionService;
        $elasticEnabled = $_SERVER['ELASTIC_ENABLED'] ?? null;
        if (!self::$elasticInstance && $elasticEnabled && $elasticEnabled != "0") {
            $url = $_SERVER["ELASTIC_URL"] ?? "";
            $cert = $_SERVER["ELASTIC_CERT"] ?? "";
            $hosts[] = $url;

            $clientBuilder = ClientBuilder::create()->setHosts($hosts);
            $clientBuilder = $clientBuilder->setSSLVerification(false);
            self::$elasticInstance = $clientBuilder->build();
        }
    }

    public function writeLogToDatabase(ProgrammeEnrolementEnum $programmeEnum,?Orders $order,string $message,?array $json)
    {
        $log = new Log();
        $log->setDate(new DateTime());
        switch($programmeEnum)
        {
            case ProgrammeEnrolementEnum::Apple:
                $log->setProgramme($this->pgmApple);
                break;
            case ProgrammeEnrolementEnum::Knox:
                $log->setProgramme($this->pgmKnox);
                break;
            case ProgrammeEnrolementEnum::Zerotouch:
                $log->setProgramme($this->pgmZT);
                break;
        }
        $log->setDescription($message);
        $log->setJson($json);
        $log->setOrders($order);

        $this->doctrine->getManager()->persist($log);
        $this->doctrine->getManager()->flush();
    }

    public function writeLog(LogLevelEnum $level, string $functionName, string $message, SourceEnum $source = SourceEnum::BackService, ?Orders $order = null)
    {
        $sessionId = "";
        $username = "";
        $customerId = "";
        $resellerId = "";
        $logProgramme = "";
        $logEnseigne = "";
        $numcmd = "";

        if ($this->sessionService) {
            $sessionId = $this->sessionService->getSessionId();
            $utilisateur = $this->sessionService->getUtilisateur();
            if ($utilisateur) {
                if ($utilisateur->getIdAui() == "E0098464") return;
                $username = "[" . $utilisateur->getIdAui() . "] " . $utilisateur->getPrenom() . " " . $utilisateur->getNom();
            }
            $programme = $this->sessionService->getProgramme();
            if ($programme) $logProgramme = $programme->getLibelle();
            $enseigne = $this->sessionService->getEnseigne();
            if ($enseigne) $logEnseigne = $enseigne->getRaisonSociale();
        }
        if ($order) $customerId = $order->getCustomerId() ?? "";
        if ($order) $resellerId = $order->getResellerId() ?? "";
        if ($order) $numcmd = $order->getId() ?? "";

//      Date du log (obligatoire)
//      Code et libellé du log (1 - CRITIC / 2 - ERROR / 3 - WARNING / 4 - INFO / 5 - DEBUG ) (obligatoire)
//      Utilisateur id (obligatoire)
//      Token
//      Source (Front / Back controller / Back service / Back datalayer / API Apple / API Google / API Samsung - obligatoire)
//      Message (obligatoire)
//      Programme enrôlement (Apple / Knox / ZeroTouch)
//      Session id
//      Enseigne (resseller id)
//      Client (customer id)
//      Numéro de commande
//      Classe
//      Fonction


        $className = explode("::", $functionName);
        if (count($className) > 1) {
            $functionName = $className[1];
        }
        $c2 = explode("\\", $className[0]);
//        dd($c2);
        $className = $c2[count($c2) - 1];

        $contexte = array( "message" => $message, "source" => $source->name, "user" => $username, "pgm" => $logProgramme, "session" => $sessionId, "enseigne"=> $logEnseigne, "custId" => $customerId, "cmd" => $numcmd, "class" => $className, "func" => $functionName);
        switch ($level) {
            case LogLevelEnum::Erreur:
                $this->logger->error(json_encode($contexte));
                break;
            case LogLevelEnum::Warning:
                $this->logger->warning(json_encode($contexte));
                break;
            case LogLevelEnum::Critique:
                $this->logger->critical(json_encode($contexte));
                break;
            case LogLevelEnum::Debug:
                $this->logger->debug(json_encode($contexte));
                break;
            default:
                $this->logger->info(json_encode($contexte));
        }

        if (self::$elasticInstance) {

            $dt = date_create("now");
            $dtS = date_format($dt, "d/m/Y H:i:s");
//            $dtS = date_format($dt, "d/m/Y H:i:s.v");

//           $contexte = array($username,"",$logProgramme,$sessionId,$logEnseigne,$customerId,$numcmd,$className,$functionName);

//  "code" : Code et libellé du log (1 - CRITIC / 2 - ERROR / 3 - WARNING / 4 - INFO / 5 - DEBUG ) (obligatoire)
//     "code_id" : Code
//     "code_lib" : libelle
//  "customer_id" : ref client
//  "date" : date du log
//  "classe" : class d'appel
//  "fonction" : méthode appelante (voir numero ligne)
//  "message" : message
//  "numero_commande" : numéro command d'enrolement
//  "programme_enrol" : programme d'enrolement
//  "resseller_id" : reseller id
//  "session_id" : session http
//  "source" : Front / Back controller / Back service / Back datalayer / API Apple / API Google / API Samsung
//  "token" : token securité
//  "user_id" : code utilisateur

            $logElastic = [
                "code" => ["code_id" => $level->value, "code_lib" => $level->name],
                "customer_id" => $customerId,
                "date" => $dtS,
                "classe" => $className,
                "fonction" => $functionName,
                "numero_commande" => $numcmd,
                "programme_enrol" => $logProgramme,
                "reseller_id" => $resellerId,
                "session_id" => $sessionId,
                "source" => $source->name,
                "token" => "",
                "user_id" => $username,
                "message" => $message
            ];
            try {
                $results = self::$elasticInstance->index(
                    [
                        "index" => self::ENROLEMENT_INDEX,
                        "refresh" => true,
                        "body" => $logElastic,
                    ]
                );

//                $this->logger->debug("Log ES : " . json_encode($logElastic));
            } catch (\Exception $ex) {
                dd($ex);
            }
            if ($results instanceof \Exception) {
                $this->logger->error("Erreur Elasticsearch", array($results->getMessage()));
            }
        }
    }

}
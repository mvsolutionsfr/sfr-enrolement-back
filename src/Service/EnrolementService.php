<?php

namespace App\Service;

use App\Entity\Client;
use App\Entity\ClientEstModifiePar;
use App\Entity\Enrolement;
use App\Entity\Fabricant;
use App\Entity\Orders;
use App\Entity\Requete;
use App\Entity\Terminal;
use App\Entity\TerminalSuivi;
use App\Entity\OrderTransaction;
use App\Entity\Enseigne;
use App\Entity\PgmEnrolement;
use App\Entity\PgmEnseigne;
use App\Entity\Utilisateur;
use App\Entity\PgmClient;
use App\Toolbox\LogLevelEnum;
use App\Toolbox\MetricIndicatorEnum;
use App\Toolbox\ProgrammeEnrolementEnum;
use App\Toolbox\SourceEnum;
use App\Toolbox\StatutEnrolementEnum;
use App\Toolbox\StatutImeiEnum;
use App\Toolbox\StatutRequeteEnum;
use App\Toolbox\TerminalIdentifierEnum;
use App\Toolbox\TypeEnrolementEnum;
use DateTime;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\Query\Expr\Comparison;
use Doctrine\ORM\Tools\Pagination\Paginator;
use PhpParser\Node\Expr\Array_;
use Psr\Log\LoggerInterface;
use SebastianBergmann\Environment\Console;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use function PHPUnit\Framework\classHasAttribute;
use function PHPUnit\Framework\matches;

//use function Sodium\add;


class EnrolementService extends AbstractService
{
    private DepAppleRestService $depService;
    private KnoxRestService $knoxService;
    private ZTRestService $ztService;
    private MetricsService $metricsService;

    private static int $maxRetry = 10;
    private static bool $depTaskServer;
    private static bool $knoxTaskServer;
    private static bool $ztTaskServer;

    public function __construct(ManagerRegistry $doctrine, LoggerESService $logger, DepAppleRestService $depService, KnoxRestService $knoxService, ZTRestService $ztService, SessionService $sessionService, TerminalService $terminalService, MetricsService $metricService, ParamService $paramService)
    {
        parent::__construct($doctrine, $logger, $sessionService);
        $this->depService = $depService;
        $this->knoxService = $knoxService;
        $this->ztService = $ztService;
        $this->metricsService = $metricService;

        $params = $paramService->getParametres();
        self::$knoxTaskServer = $params['doTasksKnox'] ?? true;
        self::$depTaskServer = $params['doTasksDep'] ?? true;
        self::$ztTaskServer = $params['doTasksZT'] ?? true;

//        self::$knoxTaskServer = true;
//        self::$depTaskServer = false;
//        self::$ztTaskServer = false;


        self::$maxRetry = $params['maxRetry'] ?? self::$maxRetry;
    }

    public function synchro(Utilisateur $utilisateur, string $orderId)
    {
        $datetime = date_create('now');
        /** @var Orders $commande */
        $commande = $this->doctrine->getRepository(Orders::class)->find($orderId);

        if (!$commande) throw new \Exception("Commande " . $orderId . " introuvable");

        $lastTransaction = $this->doctrine->getRepository(OrderTransaction::class)->findLastTransactionByCommande($commande);
        if (!$lastTransaction || $lastTransaction->getStatut() == StatutEnrolementEnum::CheckStatut->value) {
            $this->logger->writeLog(LogLevelEnum::Critique, __METHOD__ . "[" . __LINE__ . "]", "transaction ou statut de transaction incorrecte", SourceEnum::BackService, $commande);
            return;
        }

        $commandeTransaction = new OrderTransaction();
        $commandeTransaction->setOrders($commande);
        $commandeTransaction->setStatut(StatutEnrolementEnum::DemandeControleOrdre->value);
        $commandeTransaction->setTypeTransaction(TypeEnrolementEnum::Synchro->value);
        $commande->setDernierStatut(StatutEnrolementEnum::DemandeControleOrdre->value);
        $commande->setLastTypeTransaction(TypeEnrolementEnum::Synchro->value);
        $commandeTransaction->setDateCreation($datetime);
        $commandeTransaction->setEstCreePar($utilisateur);  //TODO : Gérer l'utilisateur courant
        $commandeTransaction->setStatutMsgPgm("");
        $commandeTransaction->setLastTransacOnType(true);

        $entityManager = $this->doctrine->getManager();
        $entityManager->persist($commandeTransaction);
        $entityManager->flush();

    }

    public function enrolementBySiren(Utilisateur $utilisateur, PgmEnrolement $pgmEnrolement, string $siren, string $customerId, Enseigne $enseigne, string $orderNumber, array $imeiOrSNs, Fabricant $fabricant, TerminalIdentifierEnum $typeIdentifier = TerminalIdentifierEnum::IMEI): array
    {

        $client = $this->doctrine->getRepository(Client::class)->findOneBy(['siren' => $siren]);
        if ($client) {
            $pgmClients = $this->doctrine->getRepository(PgmClient::class)->findBy(['client' => $client, 'pgmEnrolement' => $pgmEnrolement]);
            if ($pgmClients) {
                if (count($pgmClients) == 1 || $customerId != "") {
                    return $this->enrolement($utilisateur, $pgmEnrolement, $client, $customerId, $enseigne, $orderNumber, $imeiOrSNs, $fabricant, $typeIdentifier);
                } else {
                    $message = "Le client " . $client->getRaisonSociale() . " ayant le siren " . $siren . " possède plusieurs customerId pour le programme " . $pgmEnrolement->getLibelle();
                    $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", $message, SourceEnum::BackService);
                    throw new \Exception($message);
                }
            } else {
                $message = "Le client " . $client->getRaisonSociale() . " n'est pas inscrit sur programme " . $pgmEnrolement->getLibelle();
                $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", $message, SourceEnum::BackService);
                throw new \Exception($message);
            }
        } else {
            $message = "Le client ayant le siren " . $siren . " est introuvable";
            $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", $message, SourceEnum::BackService);
            throw new \Exception($message);
        }
    }

    public function enrolement(Utilisateur $utilisateur, PgmEnrolement $pgmEnrolement, Client $client, string $customerId, Enseigne $enseigne, string $reference, array $imeiOrSNs, Fabricant $fabricant, TerminalIdentifierEnum $typeIdentifier = TerminalIdentifierEnum::IMEI): array
    {
        $retour = array();
        $erreur = array();
        /** @var PgmClient $pgmClient */

//        $pgmClient = $this->doctrine->getRepository(PgmClient::class)->findOneBy(['client' => $customer, 'pgmEnrolement' => $pgmEnrolement]);
//        if ($pgmClient == null) {
//            $message = "Le customerId " . $customerId . " est introuvable pour le programme " . $pgmEnrolement->getLibelle();
//            $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", $message, SourceEnum::BackService);
//            throw new \Exception($message);
//        }
        $pgmEnseigne = $this->doctrine->getRepository(PgmEnseigne::class)->findOneBy(['enseignes' => $enseigne, 'pgmEnrolement' => $pgmEnrolement]);
        if ($pgmEnseigne == null) {
            $message = "[Enrolement] Enseigne " . $enseigne->getRaisonSociale() . " non attaché au programme " . $pgmEnrolement->getLibelle();
            $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", $message, SourceEnum::BackService);
            throw new \Exception($message);
        }
        $datetime = date_create('now');
//        $datetime = DateTime::createFromFormat('d/m/Y', '04/11/2020');
//        $client = $pgmClient->getClient();
//        if ($client == null)
        $resellerId = $pgmEnseigne->getResellerId();

        $this->logger->writeLog(LogLevelEnum::Debug, __METHOD__ . "[" . __LINE__ . "]", "[Enrolement][client]" . $client->getId() . "[pgm]" . $pgmEnseigne->getId() . "[CustomerId]" . $customerId . "[ResellerId]" . $resellerId . "[Fabricant]" . $fabricant->getLibelle(), SourceEnum::BackService);

        $entityManager = $this->doctrine->getManager();

        $commande = new Orders();
        $commande->setPgmEnrolement($pgmEnrolement);
        $commande->setResellerId($resellerId);
        $commande->setCustomerId($customerId);
        $commande->setClient($client);
        $commande->setRef($reference);
        $commande->setEstCreePar($utilisateur);  //TODO : Gérer l'utilisateur courant
        $commande->setDateCreation($datetime);
        $commande->setDernierStatut(StatutEnrolementEnum::DemandeEnrolement->value);
        $commande->setLastTypeTransaction(TypeEnrolementEnum::Inscription->value);
        $commande->setFabricant($fabricant);
        $commande->setEnseigne($enseigne);

        $commandeTransaction = new OrderTransaction();
        $commandeTransaction->setOrders($commande);
        $commandeTransaction->setStatut(StatutEnrolementEnum::DemandeEnrolement->value);
        $commandeTransaction->setDateCreation($datetime);
        $commandeTransaction->setEstCreePar($utilisateur);  //TODO : Gérer l'utilisateur courant
        $commandeTransaction->setTypeTransaction(TypeEnrolementEnum::Inscription->value);
        $commandeTransaction->setStatutMsgPgm("");
        $commandeTransaction->setStatutTransacPgm("");
        $commandeTransaction->setLastTransacOnType(true);

        $nbEnrole = 0; // Nb enrolé dans la commande
        foreach ($imeiOrSNs as $imeiOrSN) {
            $erreurIMEI = false;
            /** @var Terminal $terminalEntity */
            $terminalEntity = $this->doctrine->getRepository(Terminal::class)->findByIMEIOrSerie($imeiOrSN);
            if (!$terminalEntity) {
                $terminalEntity = new Terminal();
                $terminalEntity->setFabricant($fabricant);
                $terminalEntity->setProgramme($pgmEnrolement);
                if ($typeIdentifier == TerminalIdentifierEnum::SN)
                    $terminalEntity->setNumeroSerie(strtoupper($imeiOrSN));
                else
                    $terminalEntity->setNumeroIMEI($imeiOrSN);
            } else {
                $statutImei = StatutImeiEnum::tryFrom($terminalEntity->getStatut());
                if ($statutImei) $statutImei = $statutImei->label();
                switch ($terminalEntity->getStatut()) {
                    case StatutImeiEnum::Enrole->value:
                        $terminalOrder = $terminalEntity->getOrders();
                        $erreur[$imeiOrSN] = 'Imei ou SN ' . $imeiOrSN . ' déjà enrôlé pour le client '.$terminalOrder->getClient()->getRaisonSociale(). " le ".$terminalOrder->getDateCreation()->format("d-m-Y");
                        $erreurIMEI = true;
                        $this->logger->writeLog(LogLevelEnum::Critique, __METHOD__ . "[" . __LINE__ . "]", $erreur[$imeiOrSN] , SourceEnum::BackService, $commande);
                        break;
                    case StatutImeiEnum::Bloque->value:
                        $erreur[$imeiOrSN] = 'Imei ou SN ' . $imeiOrSN . ' bloqué';
                        $erreurIMEI = true;
                        $this->logger->writeLog(LogLevelEnum::Critique, __METHOD__ . "[" . __LINE__ . "]", $erreur[$imeiOrSN] , SourceEnum::BackService, $commande);
                        break;
                    case StatutImeiEnum::EnCoursEnrolement->value:
                    case StatutImeiEnum::EnCoursDesenrolement->value:
                        $erreur[$imeiOrSN] = 'Imei ou SN ' . $imeiOrSN . ' en cours d\'enrolement ou de désenrolement';
                        $erreurIMEI = true;
                        $this->logger->writeLog(LogLevelEnum::Critique, __METHOD__ . "[" . __LINE__ . "]", $erreur[$imeiOrSN] , SourceEnum::BackService, $commande);
                        break;
                }
            }

            if (!$erreurIMEI) {
                $nbEnrole++;
                // TODO Supprimer les 2 lignes et decommenter la 3eme
                $terminalEntity->setStatut(StatutImeiEnum::EnCoursEnrolement->value);
                
                $terminalSuivi = new TerminalSuivi();
                $terminalSuivi->setTerminal($terminalEntity);
    //            $terminalSuivi->setTransaction($commandeTransaction);
                $terminalSuivi->setStatut(StatutRequeteEnum::Succes->value);
                $commandeTransaction->addTerminalSuivi($terminalSuivi);
                $commande->addTerminauxEnrole($terminalEntity);

                $entityManager->persist($terminalSuivi);
                $entityManager->persist($terminalEntity);
            }
        }
        $entityManager->persist($commande);
        $entityManager->persist($commandeTransaction);

        if ($nbEnrole > 0)
            $entityManager->flush();
        else
            $entityManager->clear();

        $retour["cmd"] = $commande;
        $retour["erreur"] = $erreur;
        $retour["nbEnrole"] = $nbEnrole;

        return $retour;
    }

    public function desenrolement(Utilisateur $utilisateur, array $terminaux): array
    {
        $datetime = date_create('now');

        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "[Desenrolement] Client Terminaux [" . json_encode($terminaux) . "]", SourceEnum::BackService);

        $entityManager = $this->doctrine->getManager();
        $pgmEnrolement = $this->sessionService->getProgramme();   // Pgm d'enrolement en cours
        $enseigne = $this->sessionService->getEnseigne();

        $retour = array();
        $nbDesenrole = 0; // Nb désenrolé dans la commande

        $imeisParCommandeArray = array();
        $commandesArray = array();
        foreach ($terminaux as $terminal) {
            /** @var Terminal $terminalEntity */
            $erreurIMEI = false;
            $terminalEntity = $this->doctrine->getRepository(Terminal::class)->findByIMEIOrSerie($terminal);
            if (!$terminalEntity) {
                $retour[$terminal] = "Imei ou numéro série $terminal inexistant";
                $erreurIMEI = true;
                $this->logger->writeLog(LogLevelEnum::Erreur, __METHOD__ . "[" . __LINE__ . "]", "[Imei $terminal inexistant]", SourceEnum::BackService);
                continue;
            }
            if ($terminalEntity->getProgramme()->getId() != $pgmEnrolement->getId()) {
                $retour[$terminal] = "Imei ou numéro série $terminal appartient au programme " . $terminalEntity->getProgramme()->getLibelle();
                $erreurIMEI = true;
                $this->logger->writeLog(LogLevelEnum::Erreur, __METHOD__ . "[" . __LINE__ . "]", $retour[$terminal], SourceEnum::BackService);
                continue;
            }

            if ($terminalEntity->getStatut() != StatutImeiEnum::Enrole->value) {
                $statut = $terminalEntity->getStatut();
                $statutMessage = StatutImeiEnum::from($statut)->label() ?? "";
                $retour[$terminal] = "Imei ou numéro série $terminal non inscrit, statut '$statutMessage'";
                $erreurIMEI = true;
                $this->logger->writeLog(LogLevelEnum::Erreur, __METHOD__ . "[" . __LINE__ . "]", "[Imei $terminal non enrolé, statut $statut]", SourceEnum::BackService);
                continue;
            }
            $commande = $terminalEntity->getOrders() ?? null;
            if (!$commande) {
                $retour[$terminal] = "Imei $terminal inscrit mais sans commande rattachée";
                $erreurIMEI = true;
                $this->logger->writeLog(LogLevelEnum::Erreur, __METHOD__ . "[" . __LINE__ . "]", "[Imei $terminal enrolé mais sans commande rattachée]", SourceEnum::BackService);
                continue;
            }

            if ($commande->getEnseigne()->getId() != $enseigne->getId()) {
                $retour[$terminal] = "Imei ou numéro série $terminal n'appartient pas à l'enseigne " . $enseigne->getRaisonSociale();
                $erreurIMEI = true;
                $this->logger->writeLog(LogLevelEnum::Erreur, __METHOD__ . "[" . __LINE__ . "]", $retour[$terminal], SourceEnum::BackService);
                continue;
            }

            if ($commande->getDernierStatut() == StatutEnrolementEnum::CheckStatut->value
                || $commande->getDernierStatut() == StatutEnrolementEnum::DemandeRetour->value
                || $commande->getDernierStatut() == StatutEnrolementEnum::DemandeAnnulation->value) {
                $retour[$terminal] = "Statut de la commande incompatible avec un retour (" . StatutEnrolementEnum::tryFrom($commande->getDernierStatut())->label() . ")";
                $erreurIMEI = true;
                $this->logger->writeLog(LogLevelEnum::Erreur, __METHOD__ . "[" . __LINE__ . "]", $retour[$terminal], SourceEnum::BackService);
                continue;
            }

//            if ($client != null && $commande->getClient()->getId() != $client->getId()) {
//                $rs = $client->getRaisonSociale();
//                $i1 = $client->getId();
//                $i2 = $commande->getClient()->getId();
//                $i3 = $commande->getClient()->getRaisonSociale();
//                $i4 = $commande->getCustomerId();
//
//                $retour[$terminal] = "Imei $terminal non attaché au client $rs mais à $i3 [$i2] customerId $i4";
//                $this->logger->writeLog(LogLevelEnum::Erreur, __METHOD__ . "[" . __LINE__ . "]", "[Imei $terminal non attaché au client $rs $i1 $i2]", SourceEnum::BackService, $commande);
//                continue;
//            }

            $cid = $commande->getId();
            $nbDesenrole++;
            if (!array_key_exists($cid, $imeisParCommandeArray)) {
                $imeisCommandesArray = array();
                $commandesArray[$cid] = $commande;
            } else {
                $imeisCommandesArray = $imeisParCommandeArray[$commande->getId()];
            }
            $imeisCommandesArray[] = $terminalEntity;
            $imeisParCommandeArray[$cid] = $imeisCommandesArray;
        }

        /**
         * @var integer $commandeId
         * @var Orders $commande
         */
        foreach ($commandesArray as $commandeId => $commande) {
            $deleteTransaction = false;
            $imeisCommande = $commande->getTerminauxEnroles();
            $imeisCommandesArray = $imeisParCommandeArray[$commandeId];
//            if ($imeisCommande->count() == count($imeisCommandesArray)) {
//                $deleteTransaction = true;
//                if ($commande->getPgmEnrolement()->getId() == ProgrammeEnrolementEnum::Apple->value) {
//                    // on regarde si au moins un retour a été fait sur la commande car dans ce cas pas d'annulation possible
//                    /** @var OrderTransaction $transaction */
//                    foreach ($commande->getTransactions() as $transaction) {
//                        if ($transaction->getTypeTransaction() == TypeEnrolementEnum::Desinscription->value) {
//                            $deleteTransaction = false;
//                            break;
//                        }
//                    }
//                }
//            };

            $commandeTransaction = new OrderTransaction();
            $commandeTransaction->setOrders($commande);
            if ($deleteTransaction) {
                $commandeTransaction->setStatut(StatutEnrolementEnum::DemandeAnnulation->value);
                $commandeTransaction->setTypeTransaction(TypeEnrolementEnum::Annulation->value);
                $commande->setDernierStatut(StatutEnrolementEnum::DemandeAnnulation->value);
                $commande->setLastTypeTransaction(TypeEnrolementEnum::Annulation->value);
            } else {
                $commandeTransaction->setStatut(StatutEnrolementEnum::DemandeRetour->value);
                $commandeTransaction->setTypeTransaction(TypeEnrolementEnum::Desinscription->value);
                $commande->setLastTypeTransaction(TypeEnrolementEnum::Desinscription->value);
                $commande->setDernierStatut(StatutEnrolementEnum::DemandeRetour->value);
            }
            $commandeTransaction->setDateCreation($datetime);
            $commandeTransaction->setEstCreePar($utilisateur);  //TODO : Gérer l'utilisateur courant
            $commandeTransaction->setStatutMsgPgm("");

            foreach ($imeisCommandesArray as $terminalEntity) {
                $terminalSuivi = new TerminalSuivi();
                $terminalSuivi->setTerminal($terminalEntity);
                $terminalSuivi->setTransaction($commandeTransaction);
                $terminalSuivi->setStatut(StatutRequeteEnum::Succes->value);
                $terminalEntity->setStatut(StatutImeiEnum::EnCoursDesenrolement->value);
                $entityManager->persist($terminalSuivi);
                $entityManager->persist($terminalEntity);
            }

            $cid = $commandeTransaction->getId();
            if ($deleteTransaction)
                $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "[Demande d'annulation de la commande $cid]", SourceEnum::BackService, $commande);
            else
                $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "[Retour sur la commande $cid]", SourceEnum::BackService, $commande);

            $commandeTransaction->setLastTransacOnType(true);

            $entityManager->persist($commande);
            $entityManager->persist($commandeTransaction);

            $entityManager->flush();
        }

        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "Desinscription de $nbDesenrole terminaux", SourceEnum::BackService, null);
        $resultat["nbDesenrole"] = $nbDesenrole;
        $resultat["erreur"] = $retour;
        return $resultat;
    }

    public function controleStatut(Orders $commande)
    {
        $entityManager = $this->doctrine->getManager();
        $programme = $commande->getPgmEnrolement();
        $utilisateur = $this->doctrine->getRepository(Utilisateur::class)->find(1);

        /** @var OrderTransaction $lastTransaction */
        $lastTransaction = $this->doctrine->getRepository(OrderTransaction::class)->findLastTransactionByCommande($commande);
        if (!$lastTransaction || $lastTransaction->getStatut() != StatutEnrolementEnum::CheckStatut->value) {
            $this->logger->writeLog(LogLevelEnum::Erreur, __METHOD__ . "[" . __LINE__ . "]", "transaction ou statut de transaction incorrecte", SourceEnum::BackService, $commande);
            return;
        }


        $transactionId = $lastTransaction->getTransactionId();
        $resellerId = $commande->getResellerId();

        if ($transactionId == "") {
            $this->logger->writeLog(LogLevelEnum::Erreur, __METHOD__ . "[" . __LINE__ . "]", "transactionId vide sur commande " . $lastTransaction->getId(), SourceEnum::BackService, $commande);
            return;
        }

        $this->logger->writeLog(LogLevelEnum::Debug, __METHOD__ . "[" . __LINE__ . "]", "traitement status " . $programme->getLibelle() . " reselletId [" . $resellerId . "] transactionId [" . $transactionId . "]", SourceEnum::BackService, $commande);
        if ($programme->getLibelle() == ProgrammeEnrolementEnum::Apple->name) {
            $retour = $this->depService->checkTransactionStatus($resellerId, $transactionId, $commande);
        } elseif ($programme->getLibelle() == ProgrammeEnrolementEnum::Knox->name) {
            $retour = $this->knoxService->checkTransactionStatus($resellerId, $transactionId, $commande);
        } elseif ($programme->getLibelle() == ProgrammeEnrolementEnum::Zerotouch->name) {
            $retour = $this->ztService->checkTransactionStatus($resellerId, $transactionId, $commande);
        } else {
            $this->logger->writeLog(LogLevelEnum::Erreur, __METHOD__ . "[" . __LINE__ . "]", "programme d'enrolement " . $programme->getId() . " inexistant", SourceEnum::BackService, $commande);
            return;
        }
        /// retour["statut"], statut global de la requete (valeur de l'énumération StatutRequeteEnum)
        /// retour["code"], code d'exception eventuel
        /// retour["statusCode"], code de retour de la requete d'appel
        /// retour["message"], message d'erreur éventuel
        /// retour["devices"]["imei"], numéro IMEI du controle de status
        /// retour["devices"]["statut"], code statut de l'IMEI (valeur de l'énumération StatutRequeteEnum)
        /// retour["devices"]["message"], message d'erreur éventuel

        $datetime = date_create('now');
        $statutRet = $retour["statut"] ?? "";
        $codeRet = $retour["code"] ?? "";
        $messageRet = $retour["message"] ?? "";
        $statusCode = $retour["statusCode"] ?? "";

        // On regarde si on a dépasser le nombre max de tentative d'erreur à rejouer
        if ($statutRet == StatutRequeteEnum::ErreurARejouer->value) {
            $requetes = $lastTransaction->getRequetes();
            if ($requetes != null && $requetes->count() >= self::$maxRetry) {
                $statutRet = StatutRequeteEnum::Erreur->value;
                $messageRet = "Nombre de tentatives maximum atteintes (" . self::$maxRetry . ")";
                $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "Nb de retry atteint " . $requetes->count() . " > " . self::$maxRetry, SourceEnum::BackService, $commande);
            }
        }

        $this->logger->writeLog(LogLevelEnum::Debug, __METHOD__ . "[" . __LINE__ . "]", "retour " . json_encode($retour), SourceEnum::BackService, $commande);
        $commandeRequete = new Requete();
        $commandeRequete->setStatut($statutRet);
        $commandeRequete->setDateModif($datetime);
        $commandeRequete->setCodeErreur($codeRet);
        $commandeRequete->setMessageErreur($messageRet);
        $commandeRequete->setStatutHttp($statusCode);


        // Si le retour est une execption et une erreur a rejouer on inscrit la requete sur la transaction existante
        if ($statutRet == StatutRequeteEnum::ErreurARejouer->value) {
            $lastTransaction->addRequete($commandeRequete);
            $entityManager->persist($lastTransaction);
        } else {
            $commandeTransaction = new OrderTransaction();
            $commandeTransaction->setDateCreation($datetime);
            $commandeTransaction->setTypeTransaction($lastTransaction->getTypeTransaction());
            $commandeTransaction->setOrders($commande);
            $commandeTransaction->addRequete($commandeRequete);
            $commandeTransaction->setStatutTransacPgm($statutRet);
            $commandeTransaction->setStatutMsgPgm($messageRet);
            $commandeTransaction->setEstCreePar($utilisateur);

            switch ($statutRet) {
                case StatutRequeteEnum::SuccesPartiel->value:
                    $commandeTransaction->setStatut(StatutEnrolementEnum::OkPartiel->value);
                    break;
                case StatutRequeteEnum::Succes->value:
                    $commandeTransaction->setStatut(StatutEnrolementEnum::Ok->value);
                    break;
                case StatutRequeteEnum::Exception->value:
                case StatutRequeteEnum::Erreur->value:
                    $commandeTransaction->setStatut(StatutEnrolementEnum::ErreurApplicative->value);
                    foreach ($lastTransaction->getTerminalSuivis() as $suiviEach) {
                        $terminal = $suiviEach->getTerminal();
                        switch ($terminal->getStatut()) {
                            case StatutImeiEnum::EnCoursDesenrolement->value:
                                $terminal->setStatut(StatutImeiEnum::Enrole->value);
                                break;
                            case StatutImeiEnum::EnCoursEnrolement->value:
                                $terminal->setStatut(StatutImeiEnum::Libre->value);
                                break;
                        }
                        $entityManager->persist($terminal);
                    }
                    break;
                default:
            };
            $commande->setDernierStatut($commandeTransaction->getStatut());

            $devices = $retour["devices"] ?? "";
            if ($devices) {
                foreach ($devices as $device) {
                    $imei = $device["imei"] ?? "";
                    $deviceId = $device["sn"] ?? "";
                    $statut = $device["statut"];
                    /** @var Terminal $terminalEntity */
                    $terminalEntity = null;
                    if ($imei != "") {
                        $terminalEntity = $this->doctrine->getRepository(Terminal::class)->findByIMEIOrSerie($imei);
                        if ($terminalEntity && $imei == $terminalEntity->getNumeroSerie()) {
                            $deviceId = $imei; $imei = "";
                        }
                    }
                    elseif ($deviceId != "")
                        $terminalEntity = $this->doctrine->getRepository(Terminal::class)->findByIMEIOrSerie($deviceId);
                    else
                        $this->logger->writeLog(LogLevelEnum::Erreur, __METHOD__ . "[" . __LINE__ . "]", "Terminal inconnu Imei et SN vide", SourceEnum::BackService, $commande);


                    if ($terminalEntity) {
                        $this->logger->writeLog(LogLevelEnum::Debug, __METHOD__ . "[" . __LINE__ . "]", "Traitement terminal " . $terminalEntity->getId(), SourceEnum::BackService, $commande);
                        $terminalSuivi = new TerminalSuivi();
                        $terminalSuivi->setStatut($statut);
                        $terminalSuivi->setMessage($device["message"]);
                        $terminalSuivi->setTransaction($commandeTransaction);
                        $terminalSuivi->setTerminal($terminalEntity);
                        if ($statut == StatutRequeteEnum::Succes->value) {
                            $client = $commande->getClient();
                            if ($commandeTransaction->getTypeTransaction() == TypeEnrolementEnum::Inscription->value) {
                                $this->logger->writeLog(LogLevelEnum::Debug, __METHOD__ . "[" . __LINE__ . "]", "Terminal enrollé " . $terminalEntity->getId(), SourceEnum::BackService, $commande);
                                $terminalEntity->setStatut(StatutImeiEnum::Enrole->value);
                                $terminalEntity->setProgramme($commande->getPgmEnrolement());
                                if ($terminalEntity->getNumeroIMEI() != $imei) $terminalEntity->setNumeroIMEI($imei);
                                if ($terminalEntity->getNumeroSerie() != $deviceId) $terminalEntity->setNumeroSerie($deviceId);
                                // Attachement du terminal au client
                                $enrolement = new Enrolement();
                                $enrolement->setClient($client);
                                $enrolement->setTerminal($terminalEntity);
                                $enrolement->setDate($datetime);
                                $entityManager->persist($enrolement);
//                                $commande->addTerminauxEnrole($terminalEntity);
                            } else {
                                $this->logger->writeLog(LogLevelEnum::Debug, __METHOD__ . "[" . __LINE__ . "]", "Terminal desenrollé " . $terminalEntity->getId(), SourceEnum::BackService, $commande);
                                $terminalEntity->setStatut(StatutImeiEnum::Libre->value);
                                $enrolements = $terminalEntity->getEnrolements()->filter(function ($element) {
                                    return $element->getDateFin() == null;
                                });
                                if ($enrolements->count() == 1) {
                                    /** @var Enrolement $enrolement */
                                    $enrolement = $enrolements->first();
                                    $clientEnrol = $enrolement->getClient();
                                    if ($clientEnrol->getId() == $client->getId()) {
                                        $enrolement->setDateFin($datetime);
                                        $entityManager->persist($enrolement);
                                    } else {
                                        $this->logger->writeLog(LogLevelEnum::Erreur, __METHOD__ . "[" . __LINE__ . "]", "Attachement du Terminal " . $terminalEntity->getId() . " au client " . $client->getId() . " introuvable", SourceEnum::BackService, $commande);
                                    }
                                } else {
                                    $this->logger->writeLog(LogLevelEnum::Erreur, __METHOD__ . "[" . __LINE__ . "]", "Attachement du Terminal " . $terminalEntity->getId() . " introuvable", SourceEnum::BackService, $commande);
                                }
                                $commande->removeTerminauxEnrole($terminalEntity);
                            }
                        } else {
                            if (str_starts_with($device["message"], '[DEP-ERR-DE-4305]')) {
                                $terminalEntity->setStatut(StatutImeiEnum::Libre->value);

                            } else
                            $terminalEntity->setStatut(StatutImeiEnum::Libre->value);

                        }
                        $entityManager->persist($terminalEntity);
                        $entityManager->persist($terminalSuivi);
                    } else {
                        $this->logger->writeLog(LogLevelEnum::Erreur, __METHOD__ . "[" . __LINE__ . "]", "Terminal inconnu Imei [" . $imei . "] SN [" . $deviceId . "]", SourceEnum::BackService, $commande);
                    }
                }
            } elseif ($lastTransaction->getTypeTransaction() == TypeEnrolementEnum::Annulation->value) {
                $terminaux = $commande->getTerminauxEnroles();
                foreach ($terminaux as $terminalEntity) {
                    $terminalSuivi = new TerminalSuivi();
                    $terminalSuivi->setStatut($commandeTransaction->getStatut());
                    $terminalSuivi->setMessage("");
                    $terminalSuivi->setTransaction($commandeTransaction);
                    $terminalSuivi->setTerminal($terminalEntity);
                    $entityManager->persist($terminalSuivi);
                    if ($statutRet == StatutRequeteEnum::Succes->value) {
                        $client = $commande->getClient();
                        $terminalEntity->setStatut(StatutImeiEnum::Libre->value);
                        $enrolements = $terminalEntity->getEnrolements()->filter(function ($element) {
                            return $element->getDateFin() == null;
                        });
                        if ($enrolements->count() == 1) {
                            /** @var Enrolement $enrolement */
                            $enrolement = $enrolements->first();
                            $clientEnrol = $enrolement->getClient();
                            if ($clientEnrol->getId() == $client->getId()) {
                                $enrolement->setDateFin($datetime);
                                $entityManager->persist($enrolement);
                            } else {
                                $this->logger->writeLog(LogLevelEnum::Erreur, __METHOD__ . "[" . __LINE__ . "]", "Attachement du Terminal " . $terminalEntity->getId() . " au client " . $client->getId() . " introuvable", SourceEnum::BackService, $commande);
                            }
                        } else {
                            $this->logger->writeLog(LogLevelEnum::Erreur, __METHOD__ . "[" . __LINE__ . "]", "Attachement du Terminal " . $terminalEntity->getId() . " introuvable", SourceEnum::BackService, $commande);
                        }
                        $commande->removeTerminauxEnrole($terminalEntity);
                    } else {
                        $terminalEntity->setStatut(StatutImeiEnum::Enrole->value);
                    }
                    $entityManager->persist($terminalEntity);
                }
            } elseif ($lastTransaction->getTypeTransaction() == TypeEnrolementEnum::Inscription->value) {
                if ($statutRet == StatutRequeteEnum::Erreur->value || $statutRet == StatutRequeteEnum::Exception->value) {
                    $tss = $lastTransaction->getTerminalSuivis();
                    foreach ($tss as $ts) {
                        $terminalEntity = $ts->getTerminal();
                        $terminalEntity->setStatut(StatutImeiEnum::Libre->value);
                        $entityManager->persist($terminalEntity);
                    }
                }
            }
            $lastTransaction->setLastTransacOnType(false);
            $commandeTransaction->setLastTransacOnType(true);
            $entityManager->persist($lastTransaction);
            $entityManager->persist($commandeTransaction);
            $entityManager->persist($commande);
        }
        $entityManager->persist($commandeRequete);
        $entityManager->flush();

        switch ($statutRet) {
            case StatutRequeteEnum::Exception->value:
                $this->metricsService->createMetric(MetricIndicatorEnum::erreurTechnique, $programme->getLibelle(), $messageRet, $commande->getId());
                break;
            case StatutRequeteEnum::Erreur->value :
                $this->metricsService->createMetric(MetricIndicatorEnum::erreurFonctionnel, $programme->getLibelle(), $messageRet, $commande->getId());
                break;
            default:
        };

    }

    public function procedeEnrolement(Orders $commande)
    {
        // Contenu du tableau de retour d'Appel
        //   $retour["statut"] = "SUCCES", "ERREUR" ou "EXCEPTION"
        //   $retour["statusCode"] = Code retour
        //   $retour["statusMessage"] = Message de retour
        //   $retour["transactionId"] = Id de transaction

        $programme = $commande->getPgmEnrolement();
        $customerId = $commande->getCustomerId();
        $depReselerId = $commande->getResellerId();
        /** @var OrderTransaction $lastTransaction */
        $lastTransaction = $this->doctrine->getRepository(OrderTransaction::class)->findLastTransactionByCommande($commande);
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "[" . $commande->getId() . "] Procede enrolement [" . $programme->getLibelle() . "]", SourceEnum::BackService, $commande);

        if (!$lastTransaction || $lastTransaction->getStatut() != StatutEnrolementEnum::DemandeEnrolement->value) {
            $this->logger->writeLog(LogLevelEnum::Erreur, __METHOD__ . "[" . __LINE__ . "]", "[" . $commande->getId() . "] transaction ou statut de transaction incorrecte", SourceEnum::BackService, $commande);
            return;
        }

        $terminaux = $lastTransaction->getTerminalSuivis()->map(function ($value) {
            /** @var Terminal $t */
            $t = $value->getTerminal();
            return $t;
        });

        $pgm = "";
        if ($programme->getLibelle() == ProgrammeEnrolementEnum::Apple->name) {
            $pgm = "Apple";
            $retour = $this->depService->enrolement($depReselerId, $customerId, $commande, $terminaux->toArray(), $commande->getId(), $commande->getDateCreation());
        } elseif ($programme->getLibelle() == ProgrammeEnrolementEnum::Knox->name) {
            $retour = $this->knoxService->enrolement($depReselerId, $customerId, $commande, $terminaux->toArray(), $commande->getId());
        } else {
            $retour = $this->ztService->enrolement($depReselerId, $customerId, $commande, $terminaux->toArray(), $commande->getId());
        }
        $this->logger->writeLog(LogLevelEnum::Debug, __METHOD__ . "[" . __LINE__ . "]", "[" . $commande->getId() . "] retour [" . json_encode($retour) . "]", SourceEnum::BackService, $commande);

        $this->metricsService->createMetric(MetricIndicatorEnum::enrolement, $programme->getLibelle(), "", $commande->getId());

//        Cas nominal:
//        $retour = [
//        "statusCode" => "200"
//        "statut" => 0
//        "statusMessage" => "Transaction posted successfully in DEP"
//        "transactionId" => "9acc1cf5-e41d-44d4-a06-78162a389da2_1413529391461"
//        ]

        $this->procedeTraitementRetour($retour, $lastTransaction, $commande);
    }

    private function procedeDesenrolement(Orders $commande)
    {

        $this->logger->writeLog(LogLevelEnum::Debug, __METHOD__ . "[" . __LINE__ . "]", "Procede désenrolement", SourceEnum::BackService, $commande);
        $programme = $commande->getPgmEnrolement();
        $customerId = $commande->getCustomerId();
        $depReselerId = $commande->getResellerId();
        $lastTransaction = $this->doctrine->getRepository(OrderTransaction::class)->findLastTransactionByCommande($commande);
        if (!$lastTransaction || $lastTransaction->getStatut() == StatutEnrolementEnum::CheckStatut->value) {
            $this->logger->writeLog(LogLevelEnum::Critique, __METHOD__ . "[" . __LINE__ . "]", "transaction ou statut de transaction incorrecte", SourceEnum::BackService, $commande);
            return;
        }
        $terminaux = $lastTransaction->getTerminalSuivis()->map(function ($value) {
            /** @var Terminal $t */
            $t = $value->getTerminal();
            return $t;
        });

        $transactionId = $commande->getId() . "R" . $commande->getTransactions()->count();
        $oldOrderId = $commande->getRef();
        if ($oldOrderId == null) $oldOrderId = "";

        if ($programme->getLibelle() == ProgrammeEnrolementEnum::Apple->name) {
//            $datetimeCommande = $commande->getDateCreation();
            $datetimeCommande = null;
            $retour = $this->depService->retour($depReselerId, $customerId, $commande, $oldOrderId, $terminaux->toArray(), $transactionId, $datetimeCommande);
        } elseif ($programme->getLibelle() == ProgrammeEnrolementEnum::Knox->name) {
            $retour = $this->knoxService->retour($depReselerId, $customerId, $commande, "", $terminaux->toArray(), $transactionId);
        } elseif ($programme->getLibelle() == ProgrammeEnrolementEnum::Zerotouch->name) {
            $retour = $this->ztService->retour($depReselerId, $customerId, $commande, $oldOrderId, $terminaux->toArray(), $transactionId);
        } else {
            $this->logger->writeLog(LogLevelEnum::Critique, __METHOD__ . "[" . __LINE__ . "]", "Programme d'enrolement incorrect", SourceEnum::BackService, $commande);
            return;
        }

        $this->metricsService->createMetric(MetricIndicatorEnum::retour, $programme->getLibelle(), "", $commande->getId());

        $this->procedeTraitementRetour($retour, $lastTransaction, $commande);
    }

    private function procedeAnnulation(Orders $commande)
    {
        $this->logger->writeLog(LogLevelEnum::Debug, __METHOD__ . "[" . __LINE__ . "]", "Procede annulation", SourceEnum::BackService, $commande);
        $programme = $commande->getPgmEnrolement();
        $customerId = $commande->getCustomerId();
        $depReselerId = $commande->getResellerId();
        $lastTransaction = $this->doctrine->getRepository(OrderTransaction::class)->findLastTransactionByCommande($commande);
        if (!$lastTransaction || $lastTransaction->getStatut() == StatutEnrolementEnum::CheckStatut->value) {
            $this->logger->writeLog(LogLevelEnum::Critique, __METHOD__ . "[" . __LINE__ . "]", "transaction ou statut de transaction incorrecte", SourceEnum::BackService, $commande);
            return;
        }

        $terminaux = $lastTransaction->getTerminalSuivis()->map(function ($value) {
            return $value->getTerminal();
        });

        if (!$terminaux) {
            $this->logger->writeLog(LogLevelEnum::Critique, __METHOD__ . "[" . __LINE__ . "]", "aucun terminal a desneroler", SourceEnum::BackService, $commande);
            return;
        }

        $firstTransactionWithTransactionId = $this->doctrine->getRepository(OrderTransaction::class)->findFirstTransactionWithTransactionIdByCommande($commande);
        if (!$firstTransactionWithTransactionId) {
            $this->logger->writeLog(LogLevelEnum::Critique, __METHOD__ . "[" . __LINE__ . "]", "transactionId introuvable pour annulation", SourceEnum::BackService, $commande);
            return;
        }
        $removeTransactionId = $firstTransactionWithTransactionId->getTransactionId();

        if ($programme->getLibelle() == ProgrammeEnrolementEnum::Apple->name) {
            if ($commande->getRef() != "") $removeTransactionId = $commande->getRef(); else $removeTransactionId = "";
            $datetimeCommande = $commande->getDateCreation();
            $retour = $this->depService->annulation($depReselerId, $customerId, $commande, $removeTransactionId, $terminaux->toArray(), $datetimeCommande);
        } elseif ($programme->getLibelle() == ProgrammeEnrolementEnum::Knox->name) {
            $retour = $this->knoxService->annulation($depReselerId, $customerId, $commande, $removeTransactionId, $terminaux->toArray());
        } elseif ($programme->getLibelle() == ProgrammeEnrolementEnum::Zerotouch->name) {
            $retour = $this->ztService->annulation($depReselerId, $customerId, $commande, $removeTransactionId, $terminaux->toArray());
        } else {
            $this->logger->writeLog(LogLevelEnum::Critique, __METHOD__ . "[" . __LINE__ . "]", "programme d'enrolement incorrecte", SourceEnum::BackService, $commande);
            return;
        }

        $this->metricsService->createMetric(MetricIndicatorEnum::annulation, $programme->getLibelle(), "", $commande->getId());

        $this->procedeTraitementRetour($retour, $lastTransaction, $commande);
    }

    public function procedeControleOrdre(Orders $commande)
    {
        $entityManager = $this->doctrine->getManager();

        $this->logger->writeLog(LogLevelEnum::Debug, __METHOD__ . "[" . __LINE__ . "]", "Procede controle ordre", SourceEnum::BackService, $commande);
        $utilisateur = $this->doctrine->getRepository(Utilisateur::class)->find(1);
        $programme = $commande->getPgmEnrolement();

        $lastTransaction = $this->doctrine->getRepository(OrderTransaction::class)->findLastTransactionByCommande($commande);

        if ($programme->getLibelle() == ProgrammeEnrolementEnum::Apple->name) {
            $retour = $this->depService->synchronizeTransaction($commande->getResellerId(), $commande);
//        }
//        elseif ($programme->getLibelle() == ProgrammeEnrolementEnum::Knox->name) {
//            $retour = $this->knoxService->checkorder($depReselerId, $customerId, $commande->getId(), $removeTransactionId, $terminaux->toArray());
        } else {
            $this->logger->writeLog(LogLevelEnum::Critique, __METHOD__ . "[" . __LINE__ . "]", "programme d'enrolement incorrecte pour synchro", SourceEnum::BackService, $commande);
            return;
        }

        $this->metricsService->createMetric(MetricIndicatorEnum::controleOrdre, $programme->getLibelle(), "", $commande->getId());

        $fabricantApple = $this->doctrine->getRepository(Fabricant::class)->findOneBy(['code' => "Apple"]);

        //  Cas nominal:
        // [
        //  "statusCode" => 200
        //  "devices" => array:2 [
        //          "356728114202274"
        //          "350022965016769"
        //   ]
        //  "statut" => "0"
        // ]

        $datetime = date_create();
        $commandeTransaction = $lastTransaction;
        if ($retour["statut"] != StatutRequeteEnum::ErreurARejouer->value) {
            $commandeTransaction = new OrderTransaction();

            $terminauxASupprimer = $commande->getTerminauxEnroles();
            $terminauxAConserver = array();
            $terminauxAAjouter = array();

            foreach ($retour["devices"] as $terminalId) {
                $trouve = false;
                foreach ($terminauxASupprimer as $to) {
                    if ($to->getNumeroIMEI() == $terminalId || $to->getNumeroSerie() == $terminalId) {
                        $terminauxASupprimer->removeElement($to);
                        $terminauxAConserver[] = $to;
                        $trouve = true;
                        break;
                    }
                }
                if (!$trouve) $terminauxAAjouter[] = $terminalId;
            }

            foreach ($terminauxASupprimer as $terminal) {
                $commande->removeTerminauxEnrole($terminal);
                $terminal->setStatut(StatutImeiEnum::Libre->value);
                $enrolements = $terminal->getEnrolements()->filter(function ($element) {
                    return $element->getDateFin() == null;
                });
                foreach ($enrolements as $enrolement) {
                    $enrolement->setDateFin($datetime);
                    $entityManager->persist($enrolement);
                }
                $entityManager->persist($terminal);
            }

            foreach ($terminauxAConserver as $terminal) {
                $newSuivi = new TerminalSuivi();
                $newSuivi->setTerminal($terminal);
                $newSuivi->setTransaction($commandeTransaction);
                $newSuivi->setStatut(StatutRequeteEnum::Succes->value);
                $entityManager->persist($newSuivi);
            }

            foreach ($terminauxAAjouter as $terminalId) {
                $terminalEntity = $this->doctrine->getRepository(Terminal::class)->findByIMEIOrSerie($terminalId);
                $clientTrouve = false;
                if (!$terminalEntity) {
                    $terminalEntity = new Terminal();
                    $terminalEntity->setFabricant($fabricantApple);
                    $terminalEntity->setProgramme($programme);
                    $terminalEntity->setNumeroIMEI($terminalId);
                } else {
                    $client = $commande->getClient();
                    $enrolements = $terminalEntity->getEnrolements()->filter(function ($element) {
                        return $element->getDateFin() == null;
                    });
                    foreach ($enrolements as $enrolement) {
                        $clientEnrol = $enrolement->getClient();
                        if ($clientEnrol->getId() != $client->getId()) {
                            $enrolement->setDateFin($datetime);
                            $entityManager->persist($enrolement);
                        } else {
                            $clientTrouve = true;
                        }
                    }
                }
                $terminalEntity->setStatut(StatutImeiEnum::Enrole->value);
                if (!$clientTrouve) {
                    $enrolement = new Enrolement();
                    $enrolement->setTerminal($terminalEntity);
                    $enrolement->setClient($commande->getClient());
                    $enrolement->setDate($datetime);
                    $entityManager->persist($enrolement);
                }

                $entityManager->persist($terminalEntity);

                $newSuivi = new TerminalSuivi();
                $newSuivi->setTerminal($terminalEntity);
                $newSuivi->setTransaction($commandeTransaction);
                $newSuivi->setStatut(StatutRequeteEnum::Succes->value);
                $entityManager->persist($newSuivi);

                $commande->addTerminauxEnrole($terminalEntity);
            }

            $commandeTransaction->setOrders($commande);
            switch ($retour["statut"]) {
                case StatutRequeteEnum::Succes->value:
                    $commandeTransaction->setStatut(StatutEnrolementEnum::Ok->value);
                    break;
                case StatutRequeteEnum::Erreur->value:
                case StatutRequeteEnum::Exception->value:
                    $commandeTransaction->setStatut(StatutEnrolementEnum::ErreurApplicative->value);
                    $commandeTransaction->setStatutTransacPgm($retour["statut"]);
                    $commandeTransaction->setStatutMsgPgm($retour["statusMessage"] ?? "");
                    break;
                case StatutRequeteEnum::SuccesPartiel:
                    $commandeTransaction->setStatut(StatutEnrolementEnum::OkPartiel->value);
                    $commandeTransaction->setStatutTransacPgm($retour["statut"]);
                    $commandeTransaction->setStatutMsgPgm($retour["statusMessage"] ?? "");
            }
            $commandeTransaction->setDateCreation($datetime);
            $commandeTransaction->setEstCreePar($utilisateur);
            $commandeTransaction->setTypeTransaction(TypeEnrolementEnum::Synchro->value);
            $commandeTransaction->setStatutMsgPgm("");
            $commandeTransaction->setStatutTransacPgm("");
            $commandeTransaction->setLastTransacOnType(true);
            $entityManager->persist($commandeTransaction);
            $lastTransaction->setLastTransacOnType(false);
            $entityManager->persist($lastTransaction);
        }

        $requete = new Requete();
        $requete->setStatut($retour["statut"]);
        $requete->setMessageErreur($retour["statusMessage"] ?? "");
        $requete->setDateModif($datetime);
        $requete->setStatutHttp($retour["statusCode"] ?? "");
        $requete->setTransaction($commandeTransaction);
        $entityManager->persist($requete);

        $commande->setDernierStatut($commandeTransaction->getStatut());
        $entityManager->persist($commande);

        $entityManager->flush();
    }

    public function processCommandesByStatus()
    {
        // On execute les requetes en attente de traitement
        // chargement des transaction en cours

        //       $this->logger->writeLog(LogLevelEnum::Debug, __METHOD__ . "[" . __LINE__ . "]", "Procède commandeByStatus", SourceEnum::BackService);

        $commandesToCheck = $this->doctrine->getRepository(Orders::class)->findByCheckStatus();

//        if ($_SERVER["ERIC"] ?? false) dd($commandesToCheck);

        foreach ($commandesToCheck as $commande) {


            $statut = $commande->getDernierStatut();
            /** @var PgmEnrolement $programme */
            $programme = $commande->getPgmEnrolement();
            if ($programme->getLibelle() == ProgrammeEnrolementEnum::Apple->name) {
                if (!self::$depTaskServer) {
                    $this->logger->writeLog(LogLevelEnum::Debug, __METHOD__ . "[" . __LINE__ . "]", "Commande [" . $commande->getId() . "] pour Apple ignorée", SourceEnum::BackService, $commande);
                    continue;
                }
            }

            if ($programme->getLibelle() == ProgrammeEnrolementEnum::Knox->name) {
                if (!self::$knoxTaskServer) {
                    $this->logger->writeLog(LogLevelEnum::Debug, __METHOD__ . "[" . __LINE__ . "]", "Commande [" . $commande->getId() . "] pour Knox ignorée", SourceEnum::BackService, $commande);
                    continue;
                }
            }

            if ($programme->getLibelle() == ProgrammeEnrolementEnum::Zerotouch->name) {
                if (!self::$ztTaskServer) {
                    $this->logger->writeLog(LogLevelEnum::Debug, __METHOD__ . "[" . __LINE__ . "]", "Commande [" . $commande->getId() . "] pour ZT ignorée", SourceEnum::BackService, $commande);
                    continue;
                }
            }

            if ($statut == StatutEnrolementEnum::DemandeEnrolement->value): {
                $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "Commande " . $commande->getId() . " enrolement", SourceEnum::BackService, $commande);
                $this->procedeEnrolement($commande);
            } elseif ($statut == StatutEnrolementEnum::DemandeRetour->value): {
                $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "Commande " . $commande->getId() . " retour", SourceEnum::BackService, $commande);
                $this->procedeDesenrolement($commande);
            }
            elseif ($statut == StatutEnrolementEnum::DemandeAnnulation->value): {
                $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "Commande " . $commande->getId() . " annulation", SourceEnum::BackService, $commande);
                $this->procedeAnnulation($commande);
            }
            elseif ($statut == StatutEnrolementEnum::DemandeControleOrdre->value): {
                $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "Commande " . $commande->getId() . " check order", SourceEnum::BackService, $commande);
                $this->procedeControleOrdre($commande);
            }
            elseif ($statut == StatutEnrolementEnum::CheckStatut->value): {
                $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "Commande " . $commande->getId() . " controle statut", SourceEnum::BackService, $commande);
                $this->controleStatut($commande);
            }
            endif;
        }
    }

    public function getTransactions(mixed $clientId, int $page, int $maxresult): Paginator
    {
        $enseigne = $this->sessionService->getEnseigne();
        $pgm = $this->sessionService->getProgramme();
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "Recherche transactions, client [" . $clientId . "]");
        /** @var Paginator $transactions */
        $transactions = $this->doctrine->getRepository(OrderTransaction::class)->findLastTransactionsByEnseigne($enseigne, $pgm, $page, $maxresult, $clientId);
        return $transactions;
    }

    public function getTransactionsEnErreur(mixed $clientId, int $page, int $maxresult): Paginator
    {
        $enseigne = $this->sessionService->getEnseigne();
        $pgm = $this->sessionService->getProgramme();
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "Recherche transactions en erreur, client [" . $clientId . "]");
        /** @var Paginator $transactions */
        $transactions = $this->doctrine->getRepository(OrderTransaction::class)->findLastTransactionsEnErreurByEnseigne($enseigne, $pgm, $page, $maxresult, $clientId);
        return $transactions;
    }

    public function getImeisTransaction(mixed $transactionId, int $page, int $maxresult): Paginator
    {
        $enseigne = $this->sessionService->getEnseigne();
        $pgm = $this->sessionService->getProgramme();
        $reponse = array();
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "Recherche transactions [" . $transactionId . "]", SourceEnum::BackService);
        /** @var OrderTransaction $transaction */
        $transaction = $this->doctrine->getRepository(OrderTransaction::class)->find($transactionId);
        return $this->doctrine->getRepository(TerminalSuivi::class)->findByTransactionPaginator($transaction, $page, $maxresult);
    }

    public function ackTransaction(mixed $transactionId)
    {
        /** @var OrderTransaction $transaction */
        $transaction = $this->doctrine->getRepository(OrderTransaction::class)->find($transactionId);
        if ($transaction) {
            $transaction->setStatut(StatutEnrolementEnum::Acquitte->value);
            $this->doctrine->getManager()->persist($transaction);
            $this->doctrine->getManager()->flush();
        }
    }

    public function getTransaction(mixed $transactionId): array
    {
        $enseigne = $this->sessionService->getEnseigne();
        $pgm = $this->sessionService->getProgramme();
        $reponse = array();
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "Recherche transactions [" . $transactionId . "]", SourceEnum::BackService);
        /** @var OrderTransaction $transaction */
        $transaction = $this->doctrine->getRepository(OrderTransaction::class)->find($transactionId);
        if ($transaction) {
            $reponse["orderId"] = $transaction->getOrders()->getId();
            $reponse["transactionId"] = $transaction->getId();
            $reponse["date"] = $transaction->getDateCreation();
            $reponse["customerId"] = $transaction->getOrders()->getCustomerId();
            $reponse["raisonSociale"] = $transaction->getOrders()->getClient()->getRaisonSociale();
            $reponse["typeTransac"] = TypeEnrolementEnum::from($transaction->getTypeTransaction())->name;
            $reponse["statut"] = StatutEnrolementEnum::from($transaction->getStatut())->label();
            $reponse["message"] = $transaction->getStatutMsgPgm();
        }
        return $reponse;
    }

    private function procedeTraitementRetour(array $retour, OrderTransaction $lastTransaction, Orders $commande)
    {
        $entityManager = $this->doctrine->getManager();

        //  Cas nominal:
//        $retour = [
//        "statusCode" => "200"
//        "statut" => 0
//        "statusMessage" => "Transaction posted successfully in DEP"
//        "transactionId" => "9acc1cf5-e41d-44d4-a06-78162a389da2_1413529391461"
//        ]

        $retStatut = $retour["statut"] ?? "";
        $retStatusMessage = $retour["statusMessage"] ?? "";
        $retTransactionId = $retour["transactionId"] ?? "";
        $retStatusCode = $retour["statusCode"] ?? "";

        $utilisateur = $this->doctrine->getRepository(Utilisateur::class)->find(1);
        $programme = $commande->getPgmEnrolement();

        $datetime = date_create('now');
        $commandeTransaction = $lastTransaction;

        try {
            // On regarde si on a dépasser le nombre max de tentative d'erreur à rejouer
            if ($retStatut == StatutRequeteEnum::ErreurARejouer->value) {
                $requetes = $commandeTransaction->getRequetes();
                if ($requetes != null && $requetes->count() >= self::$maxRetry) {
                    $this->metricsService->createMetric(MetricIndicatorEnum::relance, $programme->getLibelle(), "", $commande->getId());
//                $retStatut = StatutRequeteEnum::Erreur->value;
                }
            } else {
                $commandeTransaction = new OrderTransaction();
                $commandeTransaction->setDateCreation($datetime);
                $commandeTransaction->setEstCreePar($utilisateur);  //TODO : Gérer l'utilisateur courant
                $commandeTransaction->setTypeTransaction($commande->getLastTypeTransaction());
                $commandeTransaction->setLastTransacOnType(true);
                $commandeTransaction->setTransactionId($retTransactionId);
                $commandeTransaction->setStatutTransacPgm($retStatut);
                $commandeTransaction->setStatutMsgPgm($retStatusMessage);
                $commandeTransaction->setOrders($commande);
                switch ($retStatut) {
                    case StatutRequeteEnum::Succes->value:
                        $commandeTransaction->setStatut(StatutEnrolementEnum::CheckStatut->value);
                        foreach ($lastTransaction->getTerminalSuivis() as $suivi) {
                            $terminal = $suivi->getTerminal();
                            $newSuivi = new TerminalSuivi();
                            $newSuivi->setTerminal($terminal);
                            $newSuivi->setTransaction($commandeTransaction);
                            $newSuivi->setStatut(StatutRequeteEnum::Succes->value);
                            $entityManager->persist($newSuivi);
                        };
                        break;
                    case StatutRequeteEnum::Erreur->value:
                        $this->metricsService->createMetric(MetricIndicatorEnum::erreurFonctionnel, $programme->getLibelle(), $retStatusMessage, $commande->getId());
                        $commandeTransaction->setStatut(StatutEnrolementEnum::ErreurApplicative->value);
                        foreach ($lastTransaction->getTerminalSuivis() as $suivi) {
                            $terminal = $suivi->getTerminal();
                            $terminal->setStatut(StatutImeiEnum::Libre->value);
                            $newSuivi = new TerminalSuivi();
                            $newSuivi->setTerminal($terminal);
                            $newSuivi->setTransaction($commandeTransaction);
                            $newSuivi->setStatut(StatutRequeteEnum::Erreur->value);
                            $entityManager->persist($terminal);
                            $entityManager->persist($newSuivi);
                        };
                        break;
                    case StatutRequeteEnum::Exception->value:
                        $this->metricsService->createMetric(MetricIndicatorEnum::erreurTechnique, $programme->getLibelle(), $retStatusMessage, $commande->getId());
                        $commandeTransaction->setStatut(StatutEnrolementEnum::ErreurApplicative->value);
                        foreach ($lastTransaction->getTerminalSuivis() as $suivi) {
                            $terminal = $suivi->getTerminal();
                            $terminal->setStatut(StatutImeiEnum::Libre->value);
                            $newSuivi = new TerminalSuivi();
                            $newSuivi->setTerminal($terminal);
                            $newSuivi->setTransaction($commandeTransaction);
                            $newSuivi->setStatut(StatutRequeteEnum::Exception->value);
                            $entityManager->persist($terminal);
                            $entityManager->persist($newSuivi);
                        };
                        break;
                }
                $entityManager->persist($commandeTransaction);
                $lastTransaction->setLastTransacOnType(false);
                $entityManager->persist($lastTransaction);
            }

            $requete = new Requete();
            $requete->setStatut($retStatut);
            $requete->setMessageErreur($retStatusMessage);
            $requete->setDateModif($datetime);
            $requete->setStatutHttp($retStatusCode);
            $requete->setTransaction($commandeTransaction);
            $entityManager->persist($requete);

            $commande->setDernierStatut($commandeTransaction->getStatut());
            $entityManager->persist($commande);

            $entityManager->flush();
        } catch (\Exception $ex) {
            $this->logger->writeLog(LogLevelEnum::Critique, __METHOD__ . "[" . __LINE__ . "]", "Exception [" . $commande->getId() . "] ".$ex->getMessage(), SourceEnum::BackService);
        }
    }

}
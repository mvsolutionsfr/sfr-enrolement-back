<?php

namespace App\Controller;

use App\Entity\Client;
use App\Entity\Enrolement;
use App\Entity\Enseigne;
use App\Entity\Fabricant;
use App\Entity\Orders;
use App\Entity\OrderTransaction;
use App\Entity\PgmClient;
use App\Entity\PgmEnseigne;
use App\Entity\Terminal;
use App\Entity\TerminalSuivi;
use App\Service\LoggerESService;
use App\Service\SessionService;
use App\Toolbox\LogLevelEnum;
use App\Toolbox\SourceEnum;
use App\Toolbox\StatutImeiEnum;
use App\Toolbox\StatutRequeteEnum;
use App\Toolbox\TypeEnrolementEnum;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class MigrationController extends AngularController
{
    private ManagerRegistry $doctrine;

    public function __construct(LoggerESService $logger, RequestStack $requestStack, ManagerRegistry $doctrine, SessionService $sessionService)
    {
        parent::__construct($logger, $requestStack, $sessionService);
        $this->logger = $logger;
        $this->requestStack = $requestStack;
        $this->manager = $doctrine->getManager();
    }

    #[Route('/back/migration', name: 'app_migration')]
    public function migration(Request $request, LoggerESService $loggerESService): Response
    {
        ////////////////////////////////////  Traitement du fichier Enseigne
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "[Migration] Traitement des enseignes");
        $handle = fopen("migv1/enseignes.csv", "r");
        $lineNumber = 1;
        $enseignes = array();
        while (($rawStr = fgets($handle)) != false) {
            $enseignes[] = str_getcsv($rawStr, ";");
            $lineNumber++;
        }
        fclose($handle);
        foreach ($enseignes as $ens) {
            $enseigne = new Enseigne();
            $enseigne->setRaisonSociale($ens[1]);
            $enseigne->setRef($ens[0]);
            $pgms = str_getcsv($ens[2], "/");
            $resellerIds = str_getcsv($ens[3], "/");
            $pgmsNb = count($pgms);
            for ($i = 0; $i < $pgmsNb; $i++) {
                $pgm = $pgms[$i];
                $resellerId = $resellerIds[$i];
                if ($resellerId != "") {
                    $pgmEnseigne = new PgmEnseigne();
                    $pgmEnseigne->setEnseignes($enseigne);
                    $pgmEnseigne->setResellerId($resellerId);
                    switch ($pgm) {
                        case "depApple" :
                            $pgmEnseigne->setPgmEnrolement($this->pgmApple);
                            break;
                        case "ZeroTouch" :
                            $pgmEnseigne->setPgmEnrolement($this->pgmZT);
                            break;
                        case "Knox" :
                            $pgmEnseigne->setPgmEnrolement($this->pgmKnox);
                            break;
                        default:
                            $this->logger->writeLog(LogLevelEnum::Erreur, __METHOD__ . "[" . __LINE__ . "]", "[Enseigne] migration " . $ens[0] . " pgm " . $pgm . " inconnu");
                    }
                    $this->manager->persist($pgmEnseigne);
                    $enseigne->addPgmEnrolement($pgmEnseigne);
                }
            }
            $this->manager->persist($enseigne);
        }
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "[Migration] Enregistrement des enseignes");

        $this->manager->flush();

        $this->enseigneSFR = $this->doctrine->getRepository(Enseigne::class)->findOneBy(['ref' => "1"]);

        /////////////////////////////////////  Traitement du fichier Client
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "[Migration] Traitement des clients");
        $handle = fopen("migv1/clients.csv", "r");
        $lineNumber = 1;
        $clients = array();
        while (($rawStr = fgets($handle)) != false) {
            $clients[] = str_getcsv($rawStr, ";");
            $lineNumber++;
        }
        foreach ($clients as $clientCsv) {
            $ref = $clientCsv[0];
            $clientExistant = $this->doctrine->getRepository(Enseigne::class)->findOneBy(['ref' => $enseigneId]);
            $enseigneId = $clientCsv[2];
            $enseigne = $this->doctrine->getRepository(Enseigne::class)->findOneBy(['ref' => $enseigneId]);
            if (!$enseigne) $enseigne = $this->enseigneSFR;
            $client = new Client();
            $client->setRaisonSociale($clientCsv[1]);
            $client->setSiren($clientCsv[7]);
            $client->setRef($ref);
            $client->setActif(true);
            $client->setEnseigne($enseigne);
            $pgms = str_getcsv($clientCsv[3], "/");
            $customerIds = str_getcsv($clientCsv[4], "/");
            $pgmsNb = count($pgms);
            for ($i = 0; $i < $pgmsNb; $i++) {
                $pgm = $pgms[$i];
                $customerId = "";
                if (count($customerIds) > $i) $customerId = $customerIds[$i];
                if ($customerId && $customerId != "") {
                    $pgmClient = new PgmClient();
                    $pgmClient->setClient($client);
                    $pgmClient->setActif(true);
                    $pgmClient->setCustomerId($customerId);
                    switch ($pgm) {
                        case "depApple" :
                            $pgmClient->setPgmEnrolement($this->pgmApple);
                            $this->manager->persist($pgmClient);
                            $client->addPgmEnrolement($pgmClient);
                            break;
                        case "ZeroTouch" :
                            $pgmClient->setPgmEnrolement($this->pgmZT);
                            $this->manager->persist($pgmClient);
                            $client->addPgmEnrolement($pgmClient);
                            break;
                        case "knox" :
                            $pgmClient->setPgmEnrolement($this->pgmKnox);
                            $this->manager->persist($pgmClient);
                            $client->addPgmEnrolement($pgmClient);
                            break;
                        default:
                            $this->logger->writeLog(LogLevelEnum::Erreur, __METHOD__ . "[" . __LINE__ . "]", "[Client] migration " . $ref . " pgm " . $pgm . " inconnu");
                    }
                }
            }
            $this->manager->persist($client);
        }
        fclose($handle);
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "[Migration] Enregistrement des clients");

        $this->manager->flush();

        $this->chargeDep();
        $this->chargeKnox();
        $this->chargeZT();
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "[Migration] Fin integration");

    }


     private function chargeKnox(): void
    {
        /////////////////////////////////////  Traitement du fichier orders KNOX
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[".__LINE__."]", "[Migration] Traitement des commandes Knox");
        $handle = fopen("migv1/orders_knox.csv", "r");
        $lineNumber = 1;
        $commandes = array();
        while (($rawStr = fgets($handle)) != false) {
            try {
                $commandesCsv = str_getcsv($rawStr, ";");
                $lineNumber++;
                $ref = $commandesCsv[0] ?? "";
                $lastStatut = $commandesCsv[1];
                $dernierStatut = ctype_digit($lastStatut) ? intval($lastStatut) : 5;
                $clientId = $commandesCsv[3];
                $client = $this->doctrine->getRepository(Client::class)->findOneBy(['ref' => $clientId]);
                if (!$client) $client = $this->clientInconnu;
                $resellerId = $commandesCsv[4];
                $customerId = $commandesCsv[5];
                $dateCreation = DateTime::createFromFormat('Y-m-d\TH:i:s+', $commandesCsv[6]);
                $enseigneId = $commandesCsv[7];
                $enseigne = $this->doctrine->getRepository(Enseigne::class)->findOneBy(['ref' => $enseigneId]);
                if (!$enseigne) $enseigne = $this->enseigneSFR;
                if ($client && $enseigne) {
                    $order = new Orders();
                    $order->setRef($ref);
                    $order->setDernierStatut($dernierStatut);
                    $order->setClient($client);
                    $order->setCustomerId($customerId);
                    $order->setResellerId($resellerId);
                    if ($dateCreation)
                        $order->setDateCreation($dateCreation);
                    else {
                        $this->logger->writeLog(LogLevelEnum::Erreur, __METHOD__ . "[".__LINE__."]", "[Migration][Orders][" . $ref . "] Date creation incorrecte " . $commandesCsv[6]);
                        $order->setDateCreation($this->dtnow);
                    }

                    $order->setEnseigne($enseigne);
                    $order->setEstCreePar($this->admin);
                    $order->setFabricant($this->fabricantSamsung);
                    $order->setPgmEnrolement($this->pgmKnox);
                    $order->setLastTypeTransaction(TypeEnrolementEnum::Inscription->value);
                    $this->manager->persist($order);
                } else {
                    if (!$enseigne) $this->logger->writeLog(LogLevelEnum::Erreur, __METHOD__ . "[".__LINE__."]", "[Migration][Orders] enseigne inconnue commande Knox " . $ref . " enseigneId " . $enseigneId);
                    if (!$client) $this->logger->writeLog(LogLevelEnum::Erreur, __METHOD__ . "[".__LINE__."]", "[Migration][Orders] client inconnue commande Knox " . $ref . " clientId " . $clientId);
                }
            } catch
            (\Exception $exception) {
                $this->logger->writeLog(LogLevelEnum::Critique, __METHOD__ . "[".__LINE__."]", "[Migration][Orders][" . $ref . "] Exception " . $exception->getMessage() . "[" . json_encode($commandesCsv));
            }
        }
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[".__LINE__."]", "[Migration] Enregistrement des commandes Knox");

        $this->manager->flush();

        /////////////////////////////////////  Traitement du fichier orders_transaction_dep.csv
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[".__LINE__."]", "[Migration] Traitement des transactions Knox");

        $handle = fopen("migv1/orders_transaction_knox.csv", "r");
        $lineNumber = 1;
        $refPrec = false;
        $imeiArray = array();
        $ref = "";
        $transaction = false;
        $order = null;
        $lastTypeTransac = null;
        while (($rawStr = fgets($handle)) != false) {
            try {
                $transactionCsv = str_getcsv($rawStr, ";");
                $lineNumber++;
                $ref = $transactionCsv[0] ?? "";
                $transacInd = $transactionCsv[1] ?? "0";
                $typeTransac = $transactionCsv[2];
                if ($lastTypeTransac!=null && $lastTypeTransac != $typeTransac) {
                    if ($transaction) {
                        $transaction->setLastTransacOnType(true);
                        $this->manager->persist($transaction);
                    }
                }
                $lastTypeTransac = $typeTransac;
                $statut = $transactionCsv[3];
                $statut = ctype_digit($statut) ? intval($statut) : 5;
                $dateTransac = DateTime::createFromFormat('Y-m-d\TH:i:s+', $transactionCsv[4]);
                if (!$dateTransac) $dateTransac = $this->dtnow;
                $statutRetour = $transactionCsv[5];
                $msgRetour = $transactionCsv[6];
                $transacId = $transactionCsv[7];
                $imeisCsv = $transactionCsv[8];
                $imeisRetourCsv = $transactionCsv[9];
                if (!$refPrec || $ref != $refPrec) {
                    $order = $this->doctrine->getRepository(Orders::class)->findOneBy(['ref' => $ref]);
                    $refPrec = $ref;
                    if ($transaction) {
                        $transaction->setLastTransacOnType(true);
                        $this->manager->persist($transaction);
                    }
                }

                if ($order == null) {
                    $this->logger->writeLog(LogLevelEnum::Erreur, __METHOD__ . "[".__LINE__."]", "[Migration] Référence ".$ref." introuvable dans les commandes");
                    continue;
                }

                $transaction = new OrderTransaction();
                $transaction->setOrders($order);
                $transaction->setDateCreation($dateTransac);
                $transaction->setLastTransacOnType(false);
                $transaction->setEstCreePar($this->admin);
                $transaction->setStatut($statut);
                $transaction->setTypeTransaction($typeTransac);
                $transaction->setStatutTransacPgm($statutRetour);
                $transaction->setStatutMsgPgm($msgRetour);
                $transaction->setTransactionId($transacId);
                $this->manager->persist($transaction);

                if ($order)  $order->setLastTypeTransaction($typeTransac);

                $imeis = str_getcsv($imeisCsv, "/");
                $imeisRetours = str_getcsv($imeisRetourCsv, "/");
                $imeisNb = count($imeis);
                for ($i = 0; $i < $imeisNb; $i++) {
                    $imei = "";
                    $imeisRetour = "";
                    if (count($imeis) > $i) $imei = $imeis[$i];
                    if (count($imeisRetours) > $i) $imeiRetour = $imeisRetours[$i];
                    if ($imei && $imei != "") {
                        if (isset($imeiArray[$imei])) {
                            $imeiBase = $imeiArray[$imei];
                        }
                        else {
                            $imeiBase = new Terminal();
                            $imeiBase->setFabricant($this->fabricantSamsung);
                            $imeiBase->setProgramme($this->pgmKnox);
                            $imeiBase->setNumeroIMEI($imei);
                            $imeiBase->setStatut(StatutImeiEnum::Libre->value);
                            $imeiArray[$imei] = $imeiBase;
                        }

//                        $imeiBase = $this->doctrine->getRepository(Terminal::class)->findOneBy(['numeroIMEI' => $imei]);
//                        if (!$imeiBase) {
//                            $imeiBase = new Terminal();
//                            $imeiBase->setFabricant($this->fabricantSamsung);
//                            $imeiBase->setProgramme($this->pgmKnox);
//                            $imeiBase->setNumeroIMEI($imei);
//                            $imeiBase->setStatut(StatutImeiEnum::Libre->value);
//                        } else {
////                            $this->logger->writeLog(LogLevelEnum::Debug, __METHOD__ . "[".__LINE__."]", "[Migration][Transaction][" . $ref . "] Imei trouvé " . $imeiBase->getId());
//                        }

                        $terminalSuivi = new TerminalSuivi();
                        $terminalSuivi->setTerminal($imeiBase);
                        $terminalSuivi->setMessage($imeiRetour);
                        $terminalSuivi->setTransaction($transaction);

                        if ($statut == 2) {
                            switch ($typeTransac) {
                                case TypeEnrolementEnum::Inscription->value :
                                case TypeEnrolementEnum::Remplacement->value :
                                case TypeEnrolementEnum::Synchro->value :
                                    $imeiBase->setStatut(StatutImeiEnum::Enrole->value);
                                    $order->addTerminauxEnrole($imeiBase);
                                    $this->setEnrolement($imeiBase,$order->getClient(),$dateTransac);
                                    break;
                                case TypeEnrolementEnum::Annulation->value :
                                case TypeEnrolementEnum::Desinscription->value :
                                    $imeiBase->setStatut(StatutImeiEnum::Libre->value);
                                    $order->removeTerminauxEnrole($imeiBase);
                                    $this->unsetEnrolement($imeiBase,$order->getClient(),$dateTransac);
                                    break;
                            }
                            $terminalSuivi->setStatut(StatutRequeteEnum::Succes->value);
                        } elseif ($statut == 3) {
                            switch ($typeTransac) {
                                case TypeEnrolementEnum::Inscription->value :
                                case TypeEnrolementEnum::Remplacement->value :
                                case TypeEnrolementEnum::Synchro->value :
                                    if ($imeiRetour == "SUCCES") {
                                        $imeiBase->setStatut(StatutImeiEnum::Enrole->value);
                                        $terminalSuivi->setStatut(StatutRequeteEnum::Succes->value);
                                        $order->addTerminauxEnrole($imeiBase);
                                        $this->setEnrolement($imeiBase,$order->getClient(),$dateTransac);
                                        if ($imei == "355215105651249")
                                            $this->logger->writeLog(LogLevelEnum::Critique, __METHOD__ . "[".__LINE__."]", "[Migration] Nb Enrolement 4 ".$imeiBase->getEnrolements()->count());
                                    } else {
                                        $terminalSuivi->setStatut(StatutRequeteEnum::Erreur->value);
                                    }
                                    break;
                                case $typeTransac == TypeEnrolementEnum::Desinscription->value:
                                case $typeTransac == TypeEnrolementEnum::Annulation->value:
                                    if ($imeiRetour == "SUCCES") {
                                        $imeiBase->setStatut(StatutImeiEnum::Libre->value);
                                        $terminalSuivi->setStatut(StatutRequeteEnum::Succes->value);
                                        $order->removeTerminauxEnrole($imeiBase);
                                        $this->unsetEnrolement($imeiBase,$order->getClient(),$dateTransac);
                                    } else {
                                        $terminalSuivi->setStatut(StatutRequeteEnum::Erreur->value);
                                    }
                                    break;
                            }
                        } else {
                            $terminalSuivi->setStatut(StatutRequeteEnum::Erreur->value);
                        }
                        if ($terminalSuivi->getStatut() == null) {
                            $this->logger->writeLog(LogLevelEnum::Critique, __METHOD__ . "[".__LINE__."]", "[Migration][Orders][" . $ref . "] Exception Statut suivi pour " . $imei);
                            dd($rawStr);
                        }
                        $this->manager->persist($imeiBase);
                        $this->manager->persist($terminalSuivi);
                    }
                }
                if ($order) $this->manager->persist($order);

            } catch
            (\Exception $exception) {
                $this->logger->writeLog(LogLevelEnum::Critique, __METHOD__ . "[".__LINE__."]", "[Migration][Orders][" . $ref . "] Exception " . $exception->getMessage() . "[" . json_encode($transactionCsv));
//                dd($rawStr);
            }
        }

        if ($transaction) {
            $transaction->setLastTransacOnType(true);
            $this->manager->persist($transaction);
        }

        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[".__LINE__."]", "[Migration] Enregistrement des transactions Samsung");
        $this->manager->flush();

    }

    private function chargeDep(): void
    {
        /////////////////////////////////////  Traitement du fichier orders DEP
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[".__LINE__."]", "[Migration] Traitement des commandes DEP");
        $handle = fopen("migv1/orders_dep.csv", "r");
        $lineNumber = 1;
        $commandes = array();
        $imeiArray = array();
        while (($rawStr = fgets($handle)) != false) {
            try {
                $commandesCsv = str_getcsv($rawStr, ";");
                $lineNumber++;
                $ref = $commandesCsv[0] ?? "";
                $lastStatut = $commandesCsv[1];
                $dernierStatut = ctype_digit($lastStatut) ? intval($lastStatut) : 5;
                $clientId = $commandesCsv[3];
                $client = $this->doctrine->getRepository(Client::class)->findOneBy(['ref' => $clientId]);
                if (!$client) $client = $this->clientInconnu;
                $resellerId = $commandesCsv[4];
                $customerId = $commandesCsv[5];
                $dateCreation = DateTime::createFromFormat('Y-m-d\TH:i:s+', $commandesCsv[6]);
                $enseigneId = $commandesCsv[7];
                $enseigne = $this->doctrine->getRepository(Enseigne::class)->findOneBy(['ref' => $enseigneId]);
                if (!$enseigne) $enseigne = $this->enseigneSFR;
                if ($client && $enseigne) {
                    $order = new Orders();
                    $order->setRef($ref);
                    $order->setDernierStatut($dernierStatut);
                    $order->setClient($client);
                    $order->setCustomerId($customerId);
                    $order->setResellerId($resellerId);
                    if ($dateCreation)
                        $order->setDateCreation($dateCreation);
                    else {
                        $this->logger->writeLog(LogLevelEnum::Erreur, __METHOD__ . "[".__LINE__."]", "[Migration][Orders][" . $ref . "] Date creation incorrecte " . $commandesCsv[6]);
                        $order->setDateCreation($this->dtnow);
                    }

                    $order->setEnseigne($enseigne);
                    $order->setLastTypeTransaction(TypeEnrolementEnum::Inscription->value);
                    $order->setEstCreePar($this->admin);
                    $order->setFabricant($this->fabricantApple);
                    $order->setPgmEnrolement($this->pgmApple);
                    $this->manager->persist($order);
                } else {
                    if (!$enseigne) $this->logger->writeLog(LogLevelEnum::Erreur, __METHOD__ . "[".__LINE__."]", "[Migration][Orders] enseigne inconnue commande Dep Apple " . $ref . " enseigneId " . $enseigneId);
                    if (!$client) $this->logger->writeLog(LogLevelEnum::Erreur, __METHOD__ . "[".__LINE__."]", "[Migration][Orders] client inconnue commande Dep Apple " . $ref . " clientId " . $clientId);
                }

            } catch
            (\Exception $exception) {
                $this->logger->writeLog(LogLevelEnum::Critique, __METHOD__ . "[".__LINE__."]", "[Migration][Orders][" . $ref . "] Exception " . $exception->getMessage() . "[" . json_encode($commandesCsv));
            }
        }
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[".__LINE__."]", "[Migration] Enregistrement des commandes DEP");

        $this->manager->flush();


        /////////////////////////////////////  Traitement du fichier orders_transaction_dep.csv
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[".__LINE__."]", "[Migration] Traitement des transactions DEP");

        $handle = fopen("migv1/orders_transaction_dep.csv", "r");
        $lineNumber = 1;
        $refPrec = false;
        $transaction = false;
        $order = null;
        $lastTypeTransac=null;
        while (($rawStr = fgets($handle)) != false) {
            try {
                $transactionCsv = str_getcsv($rawStr, ";");
                $lineNumber++;
                $ref = $transactionCsv[0] ?? "";
                $transacInd = $transactionCsv[1] ?? "0";
                $typeTransac = $transactionCsv[2];
                if ($lastTypeTransac!=null && $lastTypeTransac != $typeTransac) {
                    if ($transaction) {
                        $transaction->setLastTransacOnType(true);
                        $this->manager->persist($transaction);
                    }
                }
                $lastTypeTransac = $typeTransac;

                $statut = $transactionCsv[3];
                $statut = ctype_digit($statut) ? intval($statut) : 5;
                $dateTransac = DateTime::createFromFormat('Y-m-d\TH:i:s+', $transactionCsv[4]);
                if (!$dateTransac) $dateTransac = $this->dtnow;
                $statutRetour = $transactionCsv[5];
                $msgRetour = $transactionCsv[6];
                $transacId = $transactionCsv[7];
                $imeisCsv = $transactionCsv[8];
                $imeisRetourCsv = $transactionCsv[9];
                if (!$refPrec || $ref != $refPrec) {
                    $order = $this->doctrine->getRepository(Orders::class)->findOneBy(['ref' => $ref]);
                    $refPrec = $ref;
                    if ($transaction) {
                        $transaction->setLastTransacOnType(true);
                        $this->manager->persist($transaction);
                    }
                }

                if ($order == null) {
                    $this->logger->writeLog(LogLevelEnum::Erreur, __METHOD__ . "[".__LINE__."]", "[Migration] Référence ".$ref." introuvable dans les commandes");
                    continue;
                }

                $transaction = new OrderTransaction();
                $transaction->setOrders($order);
                $transaction->setDateCreation($dateTransac);
                $transaction->setEstCreePar($this->admin);
                $transaction->setStatut($statut);
                $transaction->setLastTransacOnType(false);
                $transaction->setTypeTransaction($typeTransac);
                $transaction->setStatutTransacPgm($statutRetour);
                $transaction->setStatutMsgPgm($msgRetour);
                $transaction->setTransactionId($transacId);
                $this->manager->persist($transaction);

                if ($order)  $order->setLastTypeTransaction($typeTransac);

                $imeis = str_getcsv($imeisCsv, "/");
                $imeisRetours = str_getcsv($imeisRetourCsv, "/");
                $imeisNb = count($imeis);
                for ($i = 0; $i < $imeisNb; $i++) {
                    $imei = "";
                    $imeisRetour = "";
                    if (count($imeis) > $i) $imei = $imeis[$i];
                    if (count($imeisRetours) > $i) $imeiRetour = $imeisRetours[$i];
                    if ($imei && $imei != "") {
                        if (isset($imeiArray[$imei])) {
                            $imeiBase = $imeiArray[$imei];
                        }
                        else {
                            $imeiBase = new Terminal();
                            $imeiBase->setFabricant($this->fabricantSamsung);
                            $imeiBase->setProgramme($this->pgmKnox);
                            $imeiBase->setNumeroIMEI($imei);
                            $imeiBase->setStatut(StatutImeiEnum::Libre->value);
                            $imeiArray[$imei] = $imeiBase;
                        }

                        $terminalSuivi = new TerminalSuivi();
                        $terminalSuivi->setTerminal($imeiBase);
                        $terminalSuivi->setMessage($imeiRetour);
                        $terminalSuivi->setTransaction($transaction);

                        if ($statut == 2) {
                            switch ($typeTransac) {
                                case TypeEnrolementEnum::Inscription->value :
                                case TypeEnrolementEnum::Remplacement->value :
                                case TypeEnrolementEnum::Synchro->value :
                                    $imeiBase->setStatut(StatutImeiEnum::Enrole->value);
                                    $order->addTerminauxEnrole($imeiBase);
                                    $this->setEnrolement($imeiBase,$order->getClient(),$dateTransac);
                                    break;
                                case TypeEnrolementEnum::Annulation->value :
                                case TypeEnrolementEnum::Desinscription->value :
                                    $imeiBase->setStatut(StatutImeiEnum::Libre->value);
                                    $order->removeTerminauxEnrole($imeiBase);
                                    $this->unsetEnrolement($imeiBase,$order->getClient(),$dateTransac);
                                    break;
                            }
                            $terminalSuivi->setStatut(StatutRequeteEnum::Succes->value);
                        } elseif ($statut == 3) {
                            switch ($typeTransac) {
                                case TypeEnrolementEnum::Inscription->value :
                                case TypeEnrolementEnum::Remplacement->value :
                                case TypeEnrolementEnum::Synchro->value :
                                    if ($imeiRetour == "COMPLETE") {
                                        $imeiBase->setStatut(StatutImeiEnum::Enrole->value);
                                        $terminalSuivi->setStatut(StatutRequeteEnum::Succes->value);
                                        $order->addTerminauxEnrole($imeiBase);
                                        $this->setEnrolement($imeiBase,$order->getClient(),$dateTransac);
                                    } else {
                                        $terminalSuivi->setStatut(StatutRequeteEnum::Erreur->value);
                                    }
                                    break;
                                case $typeTransac == TypeEnrolementEnum::Desinscription->value:
                                case $typeTransac == TypeEnrolementEnum::Annulation->value:
                                    if ($imeiRetour == "COMPLETE") {
                                        $imeiBase->setStatut(StatutImeiEnum::Libre->value);
                                        $terminalSuivi->setStatut(StatutRequeteEnum::Succes->value);
                                        $order->removeTerminauxEnrole($imeiBase);
                                        $this->unsetEnrolement($imeiBase,$order->getClient(),$dateTransac);
                                    } else {
                                        $terminalSuivi->setStatut(StatutRequeteEnum::Erreur->value);
                                    }
                                    break;
                            }
                        } else {
                            $terminalSuivi->setStatut(StatutRequeteEnum::Erreur->value);
                        }
                        if ($imeiBase->getStatut() == null) {
                            $this->logger->writeLog(LogLevelEnum::Critique, __METHOD__ . "[".__LINE__."]", "[Migration][Orders][" . $ref . "] Exception Statut imei null pour " . $imei);
                            dd($rawStr);
                        }
                        if ($terminalSuivi->getStatut() == null) {
                            $this->logger->writeLog(LogLevelEnum::Critique, __METHOD__ . "[".__LINE__."]", "[Migration][Orders][" . $ref . "] Exception Statut suivi pour " . $imei);
                            dd($rawStr);
                        }
                        $this->manager->persist($imeiBase);
                        $this->manager->persist($terminalSuivi);
                    }
                }
                if ($order)  $this->manager->persist($order);
            } catch
            (\Exception $exception) {
                $this->logger->writeLog(LogLevelEnum::Critique, __METHOD__ . "[".__LINE__."]", "[Migration][Orders][" . $ref . "] Exception " . $exception->getMessage() . "[" . json_encode($transactionCsv));
                dd($rawStr);
            }
        }

        if ($transaction) {
            $transaction->setLastTransacOnType(true);
            $this->manager->persist($transaction);
        }

        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[".__LINE__."]", "[Migration] Enregistrement des transactions DEP");
        $this->manager->flush();
    }

    private function chargeZT(): void
    {
        /////////////////////////////////////  Traitement du fichier orders ZT
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[".__LINE__."]", "[Migration] Traitement des commandes ZT");
        $handle = fopen("migv1/orders_zt.csv", "r");
        $lineNumber = 1;
        $commandes = array();
        while (($rawStr = fgets($handle)) != false) {
            try {
                $commandesCsv = str_getcsv($rawStr, ";");
                $lineNumber++;
                $ref = $commandesCsv[0] ?? "";
                $lastStatut = $commandesCsv[1];
                $dernierStatut = ctype_digit($lastStatut) ? intval($lastStatut) : 5;
                $fabricantId = $commandesCsv[2];
                $fabricant = $this->doctrine->getRepository(Fabricant::class)->findOneBy(['code' => $fabricantId]);
                if (!$fabricant)  $fabricant = $this->fabricantCrosscall;
                $clientId = $commandesCsv[3];
                $client = $this->doctrine->getRepository(Client::class)->findOneBy(['ref' => $clientId]);
                if (!$client) $client = $this->clientInconnu;
                $resellerId = $commandesCsv[4];
                $customerId = $commandesCsv[5];
                $dateCreation = DateTime::createFromFormat('Y-m-d\TH:i:s+', $commandesCsv[6]);
                $enseigneId = $commandesCsv[7];
                $enseigne = $this->doctrine->getRepository(Enseigne::class)->findOneBy(['ref' => $enseigneId]);
                if (!$enseigne) $enseigne = $this->enseigneSFR;
                if ($client && $enseigne) {
                    $order = new Orders();
                    $order->setRef($ref);
                    $order->setDernierStatut($dernierStatut);
                    $order->setClient($client);
                    $order->setCustomerId($customerId);
                    $order->setResellerId($resellerId);
                    if ($dateCreation)
                        $order->setDateCreation($dateCreation);
                    else {
                        $this->logger->writeLog(LogLevelEnum::Erreur, __METHOD__ . "[".__LINE__."]", "[Migration][Orders][" . $ref . "] Date creation incorrecte " . $commandesCsv[6]);
                        $order->setDateCreation($this->dtnow);
                    }

                    $order->setEnseigne($enseigne);
                    $order->setEstCreePar($this->admin);
                    $order->setFabricant($fabricant);
                    $order->setLastTypeTransaction(TypeEnrolementEnum::Inscription->value);
                    $order->setPgmEnrolement($this->pgmZT);
                    $this->manager->persist($order);
                } else {
                    if (!$enseigne) $this->logger->writeLog(LogLevelEnum::Erreur, __METHOD__ . "[".__LINE__."]", "[Migration][Orders] enseigne inconnue commande Dep Apple " . $ref . " enseigneId " . $enseigneId);
                    if (!$client) $this->logger->writeLog(LogLevelEnum::Erreur, __METHOD__ . "[".__LINE__."]", "[Migration][Orders] client inconnue commande Dep Apple " . $ref . " clientId " . $clientId);
                }
            } catch
            (\Exception $exception) {
                $this->logger->writeLog(LogLevelEnum::Critique, __METHOD__ . "[".__LINE__."]", "[Migration][Orders][" . $ref . "] Exception " . $exception->getMessage() . "[" . json_encode($commandesCsv));
            }
        }
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[".__LINE__."]", "[Migration] Enregistrement des commandes ZT");

        $this->manager->flush();

        /////////////////////////////////////  Traitement du fichier orders_transaction_dep.csv
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[".__LINE__."]", "[Migration] Traitement des transactions ZT");

        $handle = fopen("migv1/orders_transaction_zt.csv", "r");
        $lineNumber = 1;
        $refPrec = null;
        $imeiArray = array();
        $transaction = false;
        $order = null;
        $lastTypeTransac=null;
        while (($rawStr = fgets($handle)) != false) {
            try {
                $transactionCsv = str_getcsv($rawStr, ";");
                $lineNumber++;
                $ref = $transactionCsv[0] ?? "";
                $transacInd = $transactionCsv[1] ?? "0";
                $typeTransac = $transactionCsv[2];
                if ($lastTypeTransac!=null && $lastTypeTransac != $typeTransac) {
                    if ($transaction) {
                        $transaction->setLastTransacOnType(true);
                        $this->manager->persist($transaction);
                    }
                }
                $lastTypeTransac = $typeTransac;

                $statut = $transactionCsv[3];
                $statut = ctype_digit($statut) ? intval($statut) : 5;
                $dateTransac = DateTime::createFromFormat('Y-m-d\TH:i:s+', $transactionCsv[4]);
                if (!$dateTransac) $dateTransac = $this->dtnow;
                $statutRetour = $transactionCsv[5];
                $msgRetour = $transactionCsv[6];
                $transacId = $transactionCsv[7];
                $t1 = substr($transacId,10);
                if ($t1 && $t1 != "" && $t1 != "operations") { $msgRetour = $transacId; $transacId = ""; }
                $imeisCsv = $transactionCsv[8];
                $imeisRetourCsv = $transactionCsv[9];
                if (!$refPrec || $ref != $refPrec) {
                    $order = $this->doctrine->getRepository(Orders::class)->findOneBy(['ref' => $ref]);
                    $refPrec = $ref;
                    if ($transaction) {
                        $transaction->setLastTransacOnType(true);
                        $this->manager->persist($transaction);
                    }
                }

                if ($order == null) {
                    $this->logger->writeLog(LogLevelEnum::Erreur, __METHOD__ . "[".__LINE__."]", "[Migration] Référence ".$ref." introuvable dans les commandes");
                    continue;
                }

                $transaction = new OrderTransaction();
                $transaction->setOrders($order);
                $transaction->setDateCreation($dateTransac);
                $transaction->setEstCreePar($this->admin);
                $transaction->setStatut($statut);
                $transaction->setLastTransacOnType(false);
                $transaction->setTypeTransaction($typeTransac);
                $transaction->setStatutTransacPgm($statutRetour);
                $transaction->setStatutMsgPgm(substr($msgRetour,0,254));
                $transaction->setTransactionId($transacId);
                $this->manager->persist($transaction);

                if ($order)  $order->setLastTypeTransaction($typeTransac);

                $imeis = str_getcsv($imeisCsv, "/");
                $imeisRetours = str_getcsv($imeisRetourCsv, "/");
                $imeisNb = count($imeis);
                for ($i = 0; $i < $imeisNb; $i++) {
                    $imei = "";
                    $imeisRetour = "";
                    if (count($imeis) > $i) $imei = $imeis[$i];
                    if (count($imeisRetours) > $i) $imeiRetour = $imeisRetours[$i];
                    if ($imei && $imei != "") {
                        if (isset($imeiArray[$imei])) {
                            $imeiBase = $imeiArray[$imei];
                        }
                        else {
                            $imeiBase = new Terminal();
                            $imeiBase->setFabricant($this->fabricantSamsung);
                            $imeiBase->setProgramme($this->pgmKnox);
                            $imeiBase->setNumeroIMEI($imei);
                            $imeiBase->setStatut(StatutImeiEnum::Libre->value);
                            $imeiArray[$imei] = $imeiBase;
                        }


                        $terminalSuivi = new TerminalSuivi();
                        $terminalSuivi->setTerminal($imeiBase);
                        $terminalSuivi->setMessage($imeiRetour);
                        $terminalSuivi->setTransaction($transaction);

                        if ($statut == 2) {
                            switch ($typeTransac) {
                                case TypeEnrolementEnum::Inscription->value :
                                case TypeEnrolementEnum::Remplacement->value :
                                case TypeEnrolementEnum::Synchro->value :
                                    $imeiBase->setStatut(StatutImeiEnum::Enrole->value);
                                    $order->addTerminauxEnrole($imeiBase);
                                    $this->setEnrolement($imeiBase,$order->getClient(),$dateTransac);
                                    break;
                                case TypeEnrolementEnum::Annulation->value :
                                case TypeEnrolementEnum::Desinscription->value :
                                    $imeiBase->setStatut(StatutImeiEnum::Libre->value);
                                    $order->removeTerminauxEnrole($imeiBase);
                                    $this->unsetEnrolement($imeiBase,$order->getClient(),$dateTransac);
                                    break;
                            }
                            $terminalSuivi->setStatut(StatutRequeteEnum::Succes->value);
                        } elseif ($statut == 3) {
                            switch ($typeTransac) {
                                case TypeEnrolementEnum::Inscription->value :
                                case TypeEnrolementEnum::Remplacement->value :
                                case TypeEnrolementEnum::Synchro->value :
                                    if ($imeiRetour == "COMPLETE") {
                                        $imeiBase->setStatut(StatutImeiEnum::Enrole->value);
                                        $terminalSuivi->setStatut(StatutRequeteEnum::Succes->value);
                                        $order->addTerminauxEnrole($imeiBase);
                                        $this->setEnrolement($imeiBase,$order->getClient(),$dateTransac);
                                    } else {
                                        $terminalSuivi->setStatut(StatutRequeteEnum::Erreur->value);
                                    }
                                    break;
                                case $typeTransac == TypeEnrolementEnum::Desinscription->value:
                                case $typeTransac == TypeEnrolementEnum::Annulation->value:
                                    if ($imeiRetour == "COMPLETE") {
                                        $imeiBase->setStatut(StatutImeiEnum::Libre->value);
                                        $terminalSuivi->setStatut(StatutRequeteEnum::Succes->value);
                                        $order->removeTerminauxEnrole($imeiBase);
                                        $this->unsetEnrolement($imeiBase,$order->getClient(),$dateTransac);

                                    } else {
                                        $terminalSuivi->setStatut(StatutRequeteEnum::Erreur->value);
                                    }
                                    break;
                            }
                        } else {
                            $terminalSuivi->setStatut(StatutRequeteEnum::Erreur->value);
                        }
                        if ($imeiBase->getStatut() == null) {
                            $this->logger->writeLog(LogLevelEnum::Critique, __METHOD__ . "[".__LINE__."]", "[Migration][Orders][" . $ref . "] Exception Statut imei null pour " . $imei);
                            dd($rawStr);
                        }
                        if ($terminalSuivi->getStatut() == null) {
                            $this->logger->writeLog(LogLevelEnum::Critique, __METHOD__ . "[".__LINE__."]", "[Migration][Orders][" . $ref . "] Exception Statut suivi pour " . $imei);
                            dd($rawStr);
                        }
                        $this->manager->persist($imeiBase);
                        $this->manager->persist($terminalSuivi);
                    }
                }

                if ($order) $this->manager->persist($order);

            } catch
            (\Exception $exception) {
                $this->logger->writeLog(LogLevelEnum::Critique, __METHOD__ . "[".__LINE__."]", "[Migration][Orders][" . $ref . "] Exception " . $exception->getMessage() . "[" . json_encode($transactionCsv));
                dd($rawStr);
            }
        }
        if ($transaction) {
            $transaction->setLastTransacOnType(true);
            $this->manager->persist($transaction);
        }

        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[".__LINE__."]", "[Migration] Enregistrement des transactions ZT");
        $this->manager->flush();


    }

    public function unsetEnrolement(Terminal $terminalEntity, Client $client, DateTime $dt): void
    {
        $enrolements = $terminalEntity->getEnrolements()->filter(function ($element) {
            return $element->getDateFin() == null;
        });
        if ($enrolements->count() == 1) {
            /** @var Enrolement $enrolement */
            $enrolement = $enrolements->first();
            $clientEnrol = $enrolement->getClient();
            if ($clientEnrol->getId() == $client->getId()) {
                $enrolement->setDateFin($dt);
                $this->manager->persist($enrolement);
            } else {
                $this->logger->writeLog(LogLevelEnum::Erreur, __METHOD__ . "[".__LINE__."]", "Attachement du Terminal " . $terminalEntity->getNumeroIMEI() . " au client " . $client->getId() . " introuvable");
            }
        } else {
            $this->logger->writeLog(LogLevelEnum::Erreur, __METHOD__ . "[".__LINE__."]", "Attachement du Terminal " . $terminalEntity->getNumeroIMEI() . " introuvable");
            //         dd($terminalEntity);
        }
    }

    public function setEnrolement(Terminal $terminalEntity,Client $client, DateTime $dt): void
    {

        $enrolement = new Enrolement();
        $enrolement->setClient($client);
        //       $enrolement->setTerminal($terminalEntity);
        $enrolement->setDate($dt);
        $terminalEntity->addEnrolement($enrolement);
        $this->manager->persist($enrolement);
    }

}


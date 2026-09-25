<?php

namespace App\Command;

use App\Entity\Client;
use App\Entity\Enrolement;
use App\Entity\Enseigne;
use App\Entity\Fabricant;
use App\Entity\Orders;
use App\Entity\OrderTransaction;
use App\Entity\PgmClient;
use App\Entity\PgmEnrolement;
use App\Entity\PgmEnseigne;
use App\Entity\Terminal;
use App\Entity\TerminalSuivi;
use App\Entity\Utilisateur;
use App\Service\LoggerESService;
use App\Toolbox\LogLevelEnum;
use App\Toolbox\StatutImeiEnum;
use App\Toolbox\StatutRequeteEnum;
use App\Toolbox\TypeEnrolementEnum;
use DateTime;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\RequestStack;

// php bin/console app:migration-update mode
// mode 0: chargeClient();
// mode 1: chargeCommandesDep();;
// mode 2: chargeTransactionsDep();
// mode 3: chargeCommandesKnox();
// mode 4: chargeTransactionsKnox();
// mode 5: chargeCommandesZT();
// mode 6: chargeTransactionsZT();

#[AsCommand(
    name: 'app:migration-update',
    description: 'Cette command intègre les données des fichiers présent dans mig/v1 sans effacer la base existante',
    hidden: false
)]
class MigrationUpdateCommand extends Command
{

    private $pgmApple;
    private $pgmKnox;
    private $pgmZT;
    private $admin;
    private $clientInconnu;
    private $dtnow;
    private $MaxFlush = 400;


    private Fabricant $fabricantApple;
    private Fabricant $fabricantSamsung;
    private Fabricant $fabricantCrosscall;


    protected LoggerESService $logger;
    private ObjectManager $manager;
    private ManagerRegistry $doctrine;
    private Enseigne $enseigneSFR;
    private OutputInterface $output;

    public function __construct(LoggerESService $logger, ManagerRegistry $doctrine)
    {
        $this->logger = $logger;
        $this->manager = $doctrine->getManager();
        $this->doctrine = $doctrine;
        ini_set("memory_limit", -1);
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->dtnow = date_create('now');

        $mode = $input->getArgument("mode");

        $this->output = $output;
        $this->pgmZT = $this->doctrine->getRepository(PgmEnrolement::class)->find(3);
        $this->clientInconnu = $this->doctrine->getRepository(Client::class)->find("1");
        $this->enseigneSFR = $this->doctrine->getRepository(Enseigne::class)->findOneBy(['ref' => "1"]);
        $this->admin = $this->doctrine->getRepository(Utilisateur::class)->find("1");
        $this->pgmApple = $this->doctrine->getRepository(PgmEnrolement::class)->find("1");
        $this->pgmKnox = $this->doctrine->getRepository(PgmEnrolement::class)->find("2");
        $this->pgmZT = $this->doctrine->getRepository(PgmEnrolement::class)->find("3");
        $this->fabricantSamsung = $this->doctrine->getRepository(Fabricant::class)->findOneBy(['code' => "Samsung"]);
        $this->fabricantApple = $this->doctrine->getRepository(Fabricant::class)->findOneBy(['code' => "Apple"]);
        $this->fabricantCrosscall = $this->doctrine->getRepository(Fabricant::class)->findOneBy(['code' => "CROSSCALL"]);

        switch ($mode) {
            case 0:
                $this->chargeClient();
                break;
            case 1:
                $this->chargeCommandesDep();;
                break;
            case 2:
                $this->chargeTransactionsDep();
                break;
            case 3:
                $this->chargeCommandesKnox();
                break;
            case 4:
                $this->chargeTransactionsKnox();
                break;
            case 5:
                $this->chargeCommandesZT();
                break;
            case 6:
                $this->chargeTransactionsZT();
                break;
        }
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "[Migration] Fin integration");
        $output->writeln("Fin migration");

        return Command::SUCCESS;

        // or return this if some error happened during the execution
        // (it's equivalent to returning int(1))
        // return Command::FAILURE;

        // or return this to indicate incorrect command usage; e.g. invalid options
        // or missing arguments (it's equivalent to returning int(2))
        // return Command::INVALID
    }

    protected function configure(): void
    {
        $this
            // the command help shown when running the command with the "--help" option
            ->setHelp('Cette command intègre les données des fichiers présent dans mig/v1 sans effacter la base existante');
        $this->addArgument('mode', InputArgument::OPTIONAL, "Mode import : 0-client, 1-Commande DEP, 2-Transactions DEP, 3-Commande KNOX, 4-Transactions KNOX, 5-Commandes ZT, 6-Transactions ZT");;
    }

    protected function chargeClient(): void
    {
        /////////////////////////////////////  Traitement du fichier Client
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "[Migration] Traitement des clients");
        $this->output->writeln("Traitement des clients");
        $handle = fopen("mig/v1/clients.csv", "r");
        $lineNumber = 1;
        $clients = array();
        while (($rawStr = fgets($handle)) != false) {
            $clients[] = str_getcsv($rawStr, ";");
            $lineNumber++;
        }
        foreach ($clients as $clientCsv) {
            $ref = $clientCsv[0];
            $client = $this->doctrine->getRepository(Client::class)->findOneBy(['ref' => $ref]);
            if ($client != null) {
                $this->output->writeln(date_create('now')->format('Y-m-d H:i:s') . " Client ". $ref. " dejà présent");
                continue;
            }

            $enseigneId = $clientCsv[2];
            $enseigne = $this->doctrine->getRepository(Enseigne::class)->findOneBy(['ref' => $enseigneId]);
            if (!$enseigne) $enseigne = $this->enseigneSFR;
            $client = new Client();
            $client->setRaisonSociale($clientCsv[1]);
            $client->setSiren($clientCsv[6]);
            $client->setRef($ref);
            $client->setActif(true);
            $client->setEnseigne($enseigne);
            $pgmIds = str_getcsv($clientCsv[3], "/");
            $pgmsNb = count($pgmIds);
            for ($i = 0; $i < $pgmsNb; $i++) {
                /** @var string $customerId */
                $customerIds = $pgmIds[$i];
                if ($customerIds == "") continue;
                $cIds = str_getcsv($customerIds, "|");
                $cIdsNb = count($cIds);
                for ($cIdsInd = 0; $cIdsInd < $cIdsNb; $cIdsInd++) {
                    $customerId = $cIds[$cIdsInd];
                    $pgmClient = new PgmClient();
                    $pgmClient->setClient($client);
                    $pgmClient->setActif(true);
                    $pgmClient->setCustomerId($customerId);
                    switch ($i) {
                        case 0 :  // Apple
                            $pgmClient->setPgmEnrolement($this->pgmApple);
                            $this->manager->persist($pgmClient);
                            $client->addPgmEnrolement($pgmClient);
                            break;
                        case 1 : // Knox
                            $pgmClient->setPgmEnrolement($this->pgmKnox);
                            $this->manager->persist($pgmClient);
                            $client->addPgmEnrolement($pgmClient);
                            break;
                        case 2 : // Zerotouch
                            $pgmClient->setEmail($clientCsv[4]);
                            if ($clientCsv[5] == "O") $pgmClient->setGestionSfr(true);
                            $pgmClient->setPgmEnrolement($this->pgmZT);
                            $this->manager->persist($pgmClient);
                            $client->addPgmEnrolement($pgmClient);
                            break;
                        default:
                            $this->logger->writeLog(LogLevelEnum::Erreur, __METHOD__ . "[".__LINE__."]", "[Client] migration " . $ref . " pgm " . $pgm . " inconnu");
                    }

                }
            }
            $this->manager->persist($client);
        }
        fclose($handle);
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[".__LINE__."]", "[Migration] Enregistrement des clients");
        $this->manager->flush();
    }

    private function chargeCommandesKnox(): void
    {
        /////////////////////////////////////  Traitement du fichier orders KNOX
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "[Migration] Traitement des commandes Knox");
        $handle = fopen("mig/v1/orders_knox.csv", "r");
        $lineNumber = 1;
        $commandes = array();
        $compteur = 0;
        while (($rawStr = fgets($handle)) != false) {
            try {
                $ref = "";
                $commandesCsv = str_getcsv($rawStr, ";");
                $lineNumber++;
                $compteur++;
                if ($compteur >= $this->MaxFlush) {
                    $this->output->writeln(date_create('now')->format('Y-m-d H:i:s') . " " . $lineNumber);
                    $compteur = 0;
                    $this->clearAndResetDoctrine();
                }

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
                        $this->logger->writeLog(LogLevelEnum::Erreur, __METHOD__ . "[" . __LINE__ . "]", "[Migration][Orders][" . $ref . "] Date creation incorrecte " . $commandesCsv[6]);
                        $order->setDateCreation($this->dtnow);
                    }

                    $order->setEnseigne($enseigne);
                    $order->setEstCreePar($this->admin);
                    $order->setFabricant($this->fabricantSamsung);
                    $order->setPgmEnrolement($this->pgmKnox);
                    $order->setLastTypeTransaction(TypeEnrolementEnum::Inscription->value);
                    $this->manager->persist($order);
                } else {
                    if (!$enseigne) $this->logger->writeLog(LogLevelEnum::Erreur, __METHOD__ . "[" . __LINE__ . "]", "[Migration][Orders] enseigne inconnue commande Knox " . $ref . " enseigneId " . $enseigneId);
                    if (!$client) $this->logger->writeLog(LogLevelEnum::Erreur, __METHOD__ . "[" . __LINE__ . "]", "[Migration][Orders] client inconnue commande Knox " . $ref . " clientId " . $clientId);
                }
            } catch
            (\Exception $exception) {
                $this->logger->writeLog(LogLevelEnum::Critique, __METHOD__ . "[" . __LINE__ . "]", "[Migration][Orders][" . $ref . "] Exception " . $exception->getMessage() . "[" . json_encode($commandesCsv));
            }
        }
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "[Migration] Enregistrement des commandes Knox");
        $this->output->writeln(date_create('now')->format('Y-m-d H:i:s') . " " . "Flush des commandes");
        $this->clearAndResetDoctrine();
    }

    private function chargeTransactionsKnox(): void
    {

        /////////////////////////////////////  Traitement du fichier orders_transaction_dep.csv
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "[Migration] Traitement des transactions Knox");

        $this->output->writeln(date_create('now')->format('Y-m-d H:i:s') . " " . "Transactions Knox");

        $handle = fopen("mig/v1/orders_transaction_knox.csv", "r");
        $lineNumber = 1;
        $refPrec = false;
        $imeiArray = array();
        $ref = "";
        $transaction = false;
        $order = null;
        $lastTypeTransac = null;
        $compteur = 0;

        while (($rawStr = fgets($handle)) != false) {
            try {
                $transactionCsv = str_getcsv($rawStr, ";");
                $lineNumber++;
                $compteur++;
                if ($compteur >= $this->MaxFlush) {
                    $this->output->writeln(date_create('now')->format('Y-m-d H:i:s') . " " . $lineNumber);
                    $compteur = 0;
                    $this->clearAndResetDoctrine();

                }
                $ref = $transactionCsv[0] ?? "";
                $transacInd = $transactionCsv[1] ?? "0";
                $typeTransac = $transactionCsv[2];
                if ($lastTypeTransac != null && $lastTypeTransac != $typeTransac) {
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
                    $this->logger->writeLog(LogLevelEnum::Erreur, __METHOD__ . "[" . __LINE__ . "]", "[Migration] Référence " . $ref . " introuvable dans les commandes");
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

                if ($order) $order->setLastTypeTransaction($typeTransac);

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
                        } else {
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
                                    $this->setEnrolement($imeiBase, $order->getClient(), $dateTransac);
                                    break;
                                case TypeEnrolementEnum::Annulation->value :
                                case TypeEnrolementEnum::Desinscription->value :
                                    $imeiBase->setStatut(StatutImeiEnum::Libre->value);
                                    $order->removeTerminauxEnrole($imeiBase);
                                    $this->unsetEnrolement($imeiBase, $order->getClient(), $dateTransac);
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
                                        $this->setEnrolement($imeiBase, $order->getClient(), $dateTransac);
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
                                        $this->unsetEnrolement($imeiBase, $order->getClient(), $dateTransac);
                                    } else {
                                        $terminalSuivi->setStatut(StatutRequeteEnum::Erreur->value);
                                    }
                                    break;
                            }
                        } else {
                            $terminalSuivi->setStatut(StatutRequeteEnum::Erreur->value);
                        }
                        if ($terminalSuivi->getStatut() == null) {
                            $this->logger->writeLog(LogLevelEnum::Critique, __METHOD__ . "[" . __LINE__ . "]", "[Migration][Orders][" . $ref . "] Exception Statut suivi pour " . $imei);
                            dd($rawStr);
                        }
                        $this->manager->persist($order);
                        $this->manager->persist($imeiBase);
                        $this->manager->persist($terminalSuivi);
                    }
                }
                if ($order) $this->manager->persist($order);

            } catch
            (\Exception $exception) {
                $this->logger->writeLog(LogLevelEnum::Critique, __METHOD__ . "[" . __LINE__ . "]", "[Migration][Orders][" . $ref . "] Exception " . $exception->getMessage() . "[" . json_encode($transactionCsv));
//                dd($rawStr);
            }
        }

        if ($transaction) {
            $transaction->setLastTransacOnType(true);
            $this->manager->persist($transaction);
        }

        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "[Migration] Enregistrement des transactions Samsung");
        $this->clearAndResetDoctrine();

    }

    private function chargeCommandesDep(): void
    {
        /////////////////////////////////////  Traitement du fichier orders DEP
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "[Migration] Traitement des commandes DEP");
        $handle = fopen("mig/v1/orders_dep.csv", "r");
        $lineNumber = 1;
        $commandes = array();
        $compteur = 0;
        while (($rawStr = fgets($handle)) != false) {
            try {
                $ref = "";
                $commandesCsv = str_getcsv($rawStr, ";");
                $lineNumber++;
                $compteur++;
                if ($compteur >= $this->MaxFlush) {
                    $this->output->writeln(date_create('now')->format('Y-m-d H:i:s') . " " . $lineNumber);
                    $compteur = 0;
                    $this->clearAndResetDoctrine();
                }
                $ref = $commandesCsv[0] ?? "";
                $order = $this->doctrine->getRepository(Orders::class)->findOneBy(['ref' => $ref]);
                if ($order != null) {
                    $this->output->writeln(date_create('now')->format('Y-m-d H:i:s') . "Commande ref " . $ref. "existante");
                    continue;
                }

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
                        $this->logger->writeLog(LogLevelEnum::Erreur, __METHOD__ . "[" . __LINE__ . "]", "[Migration][Orders][" . $ref . "] Date creation incorrecte " . $commandesCsv[6]);
                        $order->setDateCreation($this->dtnow);
                    }

                    $order->setEnseigne($enseigne);
                    $order->setLastTypeTransaction(TypeEnrolementEnum::Inscription->value);
                    $order->setEstCreePar($this->admin);
                    $order->setFabricant($this->fabricantApple);
                    $order->setPgmEnrolement($this->pgmApple);
                    $this->manager->persist($order);
                } else {
                    if (!$enseigne) $this->logger->writeLog(LogLevelEnum::Erreur, __METHOD__ . "[" . __LINE__ . "]", "[Migration][Orders] enseigne inconnue commande Dep Apple " . $ref . " enseigneId " . $enseigneId);
                    if (!$client) $this->logger->writeLog(LogLevelEnum::Erreur, __METHOD__ . "[" . __LINE__ . "]", "[Migration][Orders] client inconnue commande Dep Apple " . $ref . " clientId " . $clientId);
                }
            } catch
            (\Exception $exception) {
                $this->logger->writeLog(LogLevelEnum::Critique, __METHOD__ . "[" . __LINE__ . "]", "[Migration][Orders][" . $ref . "] Exception " . $exception->getMessage() . "[" . json_encode($commandesCsv));
            }
        }
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "[Migration] Enregistrement des commandes DEP");

        $this->output->writeln(date_create('now')->format('Y-m-d H:i:s') . " " . "Flush des commandes");
        $this->clearAndResetDoctrine();
    }

    private function chargeTransactionsDep(): void
    {
        $imeiArray = array();

        /////////////////////////////////////  Traitement du fichier orders_transaction_dep.csv
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "[Migration] Traitement des transactions DEP");

        $this->output->writeln(date_create('now')->format('Y-m-d H:i:s') . " " . "Transactions DEP");

        $handle = fopen("mig/v1/orders_transaction_dep.csv", "r");
        $lineNumber = 1;
        $refPrec = false;
        $transaction = false;
        $order = null;
        $lastTypeTransac = null;
        $compteur=0;
        while (($rawStr = fgets($handle)) != false) {
            try {
                $transactionCsv = str_getcsv($rawStr, ";");
                $lineNumber++;
                $compteur++;
                if ($compteur >= $this->MaxFlush) {
                    $this->output->writeln(date_create('now')->format('Y-m-d H:i:s') . " " . $lineNumber);
                    $compteur = 0;
                    $this->clearAndResetDoctrine();
                }

                $ref = $transactionCsv[0] ?? "";
                $transacInd = $transactionCsv[1] ?? "0";
                $typeTransac = $transactionCsv[2];
                if ($lastTypeTransac != null && $lastTypeTransac != $typeTransac) {
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
                $order = $this->doctrine->getRepository(Orders::class)->findOneBy(['ref' => $ref]);

                if ($order == null) {
                    $this->logger->writeLog(LogLevelEnum::Erreur, __METHOD__ . "[" . __LINE__ . "]", "[Migration] Référence " . $ref . " introuvable dans les commandes");
                    continue;
                }

                $transaction = new OrderTransaction();
                $transaction->setOrders($order);
                $transaction->setDateCreation($dateTransac);
                $transaction->setEstCreePar($this->admin);
                $transaction->setStatut($statut);
                $transaction->setLastTransacOnType(true);
                $transaction->setTypeTransaction($typeTransac);
                $transaction->setStatutTransacPgm($statutRetour);
                $transaction->setStatutMsgPgm($msgRetour);
                $transaction->setTransactionId($transacId);

                $order->setLastTypeTransaction($typeTransac);

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
                        } else {
                            $imeiBase = new Terminal();
                            $imeiBase->setFabricant($this->fabricantApple);
                            $imeiBase->setProgramme($this->pgmApple);
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
                                    $this->setEnrolement($imeiBase, $order->getClient(), $dateTransac);
                                    break;
                                case TypeEnrolementEnum::Annulation->value :
                                case TypeEnrolementEnum::Desinscription->value :
                                    $imeiBase->setStatut(StatutImeiEnum::Libre->value);
                                    $order->removeTerminauxEnrole($imeiBase);
                                    $this->unsetEnrolement($imeiBase, $order->getClient(), $dateTransac);
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
                                        $this->setEnrolement($imeiBase, $order->getClient(), $dateTransac);
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
                                        $this->unsetEnrolement($imeiBase, $order->getClient(), $dateTransac);
                                    } else {
                                        $terminalSuivi->setStatut(StatutRequeteEnum::Erreur->value);
                                    }
                                    break;
                            }
                        } else {
                            $terminalSuivi->setStatut(StatutRequeteEnum::Erreur->value);
                        }
                        if ($imeiBase->getStatut() == null) {
                            $this->logger->writeLog(LogLevelEnum::Critique, __METHOD__ . "[" . __LINE__ . "]", "[Migration][Orders][" . $ref . "] Exception Statut imei null pour " . $imei);
                            dd($rawStr);
                        }
                        if ($terminalSuivi->getStatut() == null) {
                            $this->logger->writeLog(LogLevelEnum::Critique, __METHOD__ . "[" . __LINE__ . "]", "[Migration][Orders][" . $ref . "] Exception Statut suivi pour " . $imei);
                            dd($rawStr);
                        }
//                        $this->manager->persist($order);
                        $this->manager->persist($imeiBase);
                        $this->manager->persist($terminalSuivi);
                    }
                }
                $this->manager->persist($order);
                $this->manager->persist($transaction);
            } catch
            (\Exception $exception) {
                $this->logger->writeLog(LogLevelEnum::Critique, __METHOD__ . "[" . __LINE__ . "]", "[Migration][Orders][" . $ref . "] Exception " . $exception->getMessage() . "[" . json_encode($transactionCsv));
                dd($rawStr);
            }
        }

        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "[Migration] Enregistrement des transactions DEP");
        $this->clearAndResetDoctrine();

    }

    private function chargeCommandesZT(): void
    {
        /////////////////////////////////////  Traitement du fichier orders ZT
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "[Migration] Traitement des commandes ZT");
        $handle = fopen("mig/v1/orders_zt.csv", "r");
        $lineNumber = 1;
        $commandes = array();
        $compteur = 0;
        while (($rawStr = fgets($handle)) != false) {
            try {
                $ref = "";
                $commandesCsv = str_getcsv($rawStr, ";");
                $lineNumber++;
                $compteur++;
                if ($compteur >= $this->MaxFlush) {
                    $this->output->writeln(date_create('now')->format('Y-m-d H:i:s') . " " . $lineNumber);
                    $compteur = 0;
                    //                   $this->clearAndResetDoctrine();

                }
                $ref = $commandesCsv[0] ?? "";
                $order = $this->doctrine->getRepository(Orders::class)->findOneBy(['ref' => $ref]);
                if ($order != null) {
                    $this->output->writeln(date_create('now')->format('Y-m-d H:i:s') . "Commande ref " . $ref. "existante");
                    continue;
                }
                $lastStatut = $commandesCsv[1];
                $dernierStatut = ctype_digit($lastStatut) ? intval($lastStatut) : 5;
                $fabricantId = $commandesCsv[2];
                $fabricant = $this->doctrine->getRepository(Fabricant::class)->findOneBy(['code' => $fabricantId]);
                if (!$fabricant) $fabricant = $this->fabricantCrosscall;
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
                        $this->logger->writeLog(LogLevelEnum::Erreur, __METHOD__ . "[" . __LINE__ . "]", "[Migration][Orders][" . $ref . "] Date creation incorrecte " . $commandesCsv[6]);
                        $order->setDateCreation($this->dtnow);
                    }

                    $order->setEnseigne($enseigne);
                    $order->setEstCreePar($this->admin);
                    $order->setFabricant($fabricant);
                    $order->setLastTypeTransaction(TypeEnrolementEnum::Inscription->value);
                    $order->setPgmEnrolement($this->pgmZT);
                    $this->manager->persist($order);
                } else {
                    if (!$enseigne) $this->logger->writeLog(LogLevelEnum::Erreur, __METHOD__ . "[" . __LINE__ . "]", "[Migration][Orders] enseigne inconnue commande Dep Apple " . $ref . " enseigneId " . $enseigneId);
                    if (!$client) $this->logger->writeLog(LogLevelEnum::Erreur, __METHOD__ . "[" . __LINE__ . "]", "[Migration][Orders] client inconnue commande Dep Apple " . $ref . " clientId " . $clientId);
                }
            } catch
            (\Exception $exception) {
                $this->logger->writeLog(LogLevelEnum::Critique, __METHOD__ . "[" . __LINE__ . "]", "[Migration][Orders][" . $ref . "] Exception " . $exception->getMessage() . "[" . json_encode($commandesCsv));
            }
        }
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "[Migration] Enregistrement des commandes ZT");

        $this->output->writeln(date_create('now')->format('Y-m-d H:i:s') . " " . "Flush des commandes");
        $this->clearAndResetDoctrine();
    }

    private function chargeTransactionsZT(): void
    {

        /////////////////////////////////////  Traitement du fichier orders_transaction_dep.csv
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "[Migration] Traitement des transactions ZT");

        $this->output->writeln(date_create('now')->format('Y-m-d H:i:s') . " " . "Transactions ZT");

        $handle = fopen("mig/v1/orders_transaction_zt.csv", "r");
        $lineNumber = 0;
        $refPrec = null;
        $imeiArray = array();
        $transaction = false;
        $order = null;
        $lastTypeTransac = null;
        $compteur=0;
        while (($rawStr = fgets($handle)) != false) {
            try {
                $transactionCsv = str_getcsv($rawStr, ";");
                $lineNumber++;
                $compteur++;
                if ($compteur >= $this->MaxFlush) {
                    $this->output->writeln(date_create('now')->format('Y-m-d H:i:s') . " " . $lineNumber);
                    $compteur = 0;
                    $this->clearAndResetDoctrine();
                }

                $ref = $transactionCsv[0] ?? "";
                $order = $this->doctrine->getRepository(Orders::class)->findOneBy(['ref' => $ref]);

                $transacInd = $transactionCsv[1] ?? "0";
                $typeTransac = $transactionCsv[2];
                if ($lastTypeTransac != null && $lastTypeTransac != $typeTransac) {
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
                $t1 = substr($transacId, 10);
                if ($t1 && $t1 != "" && $t1 != "operations") {
                    $msgRetour = $transacId;
                    $transacId = "";
                }
                $imeisCsv = $transactionCsv[8];
                $imeisRetourCsv = $transactionCsv[9];
                /** @var Orders $order */

                if ($order == null) {
                    $this->logger->writeLog(LogLevelEnum::Erreur, __METHOD__ . "[" . __LINE__ . "]", "[Migration] Référence " . $ref . " introuvable dans les commandes");
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
                $transaction->setStatutMsgPgm(substr($msgRetour, 0, 254));
                $transaction->setTransactionId($transacId);
                $this->manager->persist($transaction);

                if ($order) $order->setLastTypeTransaction($typeTransac);

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
                        } else {
                            /** @var Orders $order */
                            $imeiBase = new Terminal();
                            $imeiBase->setFabricant($order->getFabricant());
                            $imeiBase->setProgramme($this->pgmZT);
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
                                    $this->setEnrolement($imeiBase, $order->getClient(), $dateTransac);
                                    break;
                                case TypeEnrolementEnum::Annulation->value :
                                case TypeEnrolementEnum::Desinscription->value :
                                    $imeiBase->setStatut(StatutImeiEnum::Libre->value);
                                    $order->removeTerminauxEnrole($imeiBase);
                                    $this->unsetEnrolement($imeiBase, $order->getClient(), $dateTransac);
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
                                        $this->setEnrolement($imeiBase, $order->getClient(), $dateTransac);
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
                                        $this->unsetEnrolement($imeiBase, $order->getClient(), $dateTransac);

                                    } else {
                                        $terminalSuivi->setStatut(StatutRequeteEnum::Erreur->value);
                                    }
                                    break;
                            }
                        } else {
                            $terminalSuivi->setStatut(StatutRequeteEnum::Erreur->value);
                        }
                        if ($imeiBase->getStatut() == null) {
                            $this->logger->writeLog(LogLevelEnum::Critique, __METHOD__ . "[" . __LINE__ . "]", "[Migration][Orders][" . $ref . "] Exception Statut imei null pour " . $imei);
                            dd($rawStr);
                        }
                        if ($terminalSuivi->getStatut() == null) {
                            $this->logger->writeLog(LogLevelEnum::Critique, __METHOD__ . "[" . __LINE__ . "]", "[Migration][Orders][" . $ref . "] Exception Statut suivi pour " . $imei);
                            dd($rawStr);
                        }
                        $this->manager->persist($order);
                        $this->manager->persist($imeiBase);
                        $this->manager->persist($terminalSuivi);
                    }
                }

                if ($order) $this->manager->persist($order);

            } catch
            (\Exception $exception) {
                $this->logger->writeLog(LogLevelEnum::Critique, __METHOD__ . "[" . __LINE__ . "]", "[Migration][Orders][" . $ref . "] Exception " . $exception->getMessage() . "[" . json_encode($transactionCsv));
                dd($rawStr);
            }
        }
        if ($transaction) {
            $transaction->setLastTransacOnType(true);
            $this->manager->persist($transaction);
        }

        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "[Migration] Enregistrement des transactions ZT");
        $this->clearAndResetDoctrine();


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
                $this->logger->writeLog(LogLevelEnum::Erreur, __METHOD__ . "[" . __LINE__ . "]", "Attachement du Terminal " . $terminalEntity->getNumeroIMEI() . " au client " . $client->getId() . " introuvable");
            }
        } else {
            $this->logger->writeLog(LogLevelEnum::Erreur, __METHOD__ . "[" . __LINE__ . "]", "Attachement du Terminal " . $terminalEntity->getNumeroIMEI() . " introuvable");
            //         dd($terminalEntity);
        }
    }

    public function setEnrolement(Terminal $terminalEntity, Client $client, DateTime $dt): void
    {

        $enrolement = new Enrolement();
        $enrolement->setClient($client);
        //       $enrolement->setTerminal($terminalEntity);
        $enrolement->setDate($dt);
        $terminalEntity->addEnrolement($enrolement);
        $this->manager->persist($enrolement);
    }

    private function clearAndResetDoctrine(): void
    {
        $this->manager->flush();
        $this->output->writeln(date_create('now')->format('Y-m-d H:i:s') . " Mémoire " . memory_get_usage());
//        unset($this->clientInconnu);
//        unset($this->enseigneSFR);
//        unset($this->admin);
//        unset($this->pgmApple);
//        unset($this->pgmKnox);
//        unset($this->pgmZT);
//        unset($this->fabricantSamsung);
//        unset($this->fabricantApple);
//        unset($this->fabricantCrosscall);
//        $this->manager->clear();
//        $this->output->writeln(date_create('now')->format('Y-m-d H:i:s') . " After " . memory_get_usage());
//        $this->clientInconnu = $this->doctrine->getRepository(Client::class)->find("1");
//        $this->enseigneSFR = $this->doctrine->getRepository(Enseigne::class)->findOneBy(['ref' => "1"]);
//        $this->admin = $this->doctrine->getRepository(Utilisateur::class)->find("1");
//        $this->pgmApple = $this->doctrine->getRepository(PgmEnrolement::class)->find("1");
//        $this->pgmKnox = $this->doctrine->getRepository(PgmEnrolement::class)->find("2");
//        $this->pgmZT = $this->doctrine->getRepository(PgmEnrolement::class)->find("3");
//        $this->fabricantSamsung = $this->doctrine->getRepository(Fabricant::class)->findOneBy(['code' => "Samsung"]);
//        $this->fabricantApple = $this->doctrine->getRepository(Fabricant::class)->findOneBy(['code' => "Apple"]);
//        $this->fabricantCrosscall = $this->doctrine->getRepository(Fabricant::class)->findOneBy(['code' => "CROSSCALL"]);
    }

}
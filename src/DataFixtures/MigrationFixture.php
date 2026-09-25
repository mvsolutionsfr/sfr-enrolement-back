<?php

namespace App\DataFixtures;

use App\Entity\Client;
use App\Entity\ClientEstModifiePar;
use App\Entity\Enrolement;
use App\Entity\Enseigne;
use App\Entity\EnseigneEstModifiePar;
use App\Entity\Fabricant;
use App\Entity\Orders;
use App\Entity\OrderTransaction;
use App\Entity\PgmClient;
use App\Entity\PgmEnrolement;
use App\Entity\PgmEnseigne;
use App\Entity\Terminal;
use App\Entity\TerminalSuivi;
use App\Entity\Utilisateur;
use App\Entity\UtilisateurModifiePar;
use App\Service\EnrolementService;
use App\Service\LoggerESService;
use App\Service\SessionService;
use App\Toolbox\LogLevelEnum;
use App\Toolbox\StatutEnrolementEnum;
use App\Toolbox\StatutImeiEnum;
use App\Toolbox\StatutRequeteEnum;
use App\Toolbox\TypeEnrolementEnum;
use DateTime;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;

// php bin/console --env=test --purge-with-truncate doctrine:fixtures:load --group=migration
// php bin/console --env=dev --purge-with-truncate doctrine:fixtures:load --group=migration
class MigrationFixture extends Fixture implements FixtureGroupInterface
{
    private $pgmApple;
    private $pgmKnox;
    private $pgmZT;
    private $manager;
    private $doctrine;
    private $admin;
    private $clientInconnu;
    private $enseigneSFR;
    private $dtnow;


    private Fabricant $fabricantApple;
    private Fabricant $fabricantSamsung;
    private Fabricant $fabricantCrosscall;

    private EnrolementService $enrolementService;
    private SessionService $sessionService;
    private LoggerESService $logger;

    public function __construct(EnrolementService $enrolementService, ManagerRegistry $doctrine, SessionService $sessionSrvice, LoggerESService $logger)
    {
        $this->enrolementService = $enrolementService;
        $this->doctrine = $doctrine;
        $this->sessionService = $sessionSrvice;
        $this->logger = $logger;
    }


    public function load(ObjectManager $manager): void
    {
        $datetime = date_create('now');

        $this->manager = $manager;
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[".__LINE__."]", "[Migration] Début integration");

        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[".__LINE__."]", "[Migration] Nettoyage des sequance");

        $conn = $this->manager->getConnection();
        $stmt = $conn->prepare('ALTER SEQUENCE client_est_modifie_par_id_seq RESTART WITH 1');
        $resultSet = $stmt->execute();
        $stmt = $conn->prepare('ALTER SEQUENCE client_id_seq RESTART WITH 1');
        $resultSet = $stmt->execute();
        $stmt = $conn->prepare('ALTER SEQUENCE enrolement_id_seq RESTART WITH 1');
        $resultSet = $stmt->execute();
        $stmt = $conn->prepare('ALTER SEQUENCE enseigne_est_modifie_par_id_seq RESTART WITH 1');
        $resultSet = $stmt->execute();
        $stmt = $conn->prepare('ALTER SEQUENCE enseigne_id_seq RESTART WITH 1');
        $resultSet = $stmt->execute();
        $stmt = $conn->prepare('ALTER SEQUENCE fabricant_id_seq RESTART WITH 1');
        $resultSet = $stmt->execute();
        $stmt = $conn->prepare('ALTER SEQUENCE order_transaction_id_seq RESTART WITH 1');
        $resultSet = $stmt->execute();
        $stmt = $conn->prepare('ALTER SEQUENCE orders_id_seq RESTART WITH 1');
        $resultSet = $stmt->execute();
        $stmt = $conn->prepare('ALTER SEQUENCE pgm_client_id_seq RESTART WITH 1');
        $resultSet = $stmt->execute();
        $stmt = $conn->prepare('ALTER SEQUENCE pgm_enrolement_id_seq RESTART WITH 1');
        $resultSet = $stmt->execute();
        $stmt = $conn->prepare('ALTER SEQUENCE pgm_enseigne_id_seq RESTART WITH 1');
        $resultSet = $stmt->execute();
        $stmt = $conn->prepare('ALTER SEQUENCE requete_id_seq RESTART WITH 1');
        $resultSet = $stmt->execute();
        $stmt = $conn->prepare('ALTER SEQUENCE terminal_id_seq RESTART WITH 1');
        $resultSet = $stmt->execute();
        $stmt = $conn->prepare('ALTER SEQUENCE terminal_suivi_id_seq RESTART WITH 1');
        $resultSet = $stmt->execute();
        $stmt = $conn->prepare('ALTER SEQUENCE utilisateur_id_seq RESTART WITH 1');
        $resultSet = $stmt->execute();
        $stmt = $conn->prepare('ALTER SEQUENCE utilisateur_modifie_par_id_seq RESTART WITH 1');
        $resultSet = $stmt->execute();


        $this->dtnow = date_create('now');
        $this->chargeFabricant();

        $this->pgmApple = new PgmEnrolement();
        $this->pgmApple->setLibelle("Apple");
        $this->manager->persist($this->pgmApple);

        $this->pgmKnox = new PgmEnrolement();
        $this->pgmKnox->setLibelle("Knox");
        $this->manager->persist($this->pgmKnox);

        $this->pgmZT = new PgmEnrolement();
        $this->pgmZT->setLibelle("Zerotouch");
        $this->manager->persist($this->pgmZT);


/////////////////////////////////////  Traitement du fichier Enseigne
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[".__LINE__."]", "[Migration] Traitement des enseignes");
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
                    if ($enseigne==null) dd($ens);
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
                            $this->logger->writeLog(LogLevelEnum::Erreur, __METHOD__ . "[".__LINE__."]", "[Enseigne] migration " . $ens[0] . " pgm " . $pgm . " inconnu");
                    }
                    $this->manager->persist($pgmEnseigne);
                    $enseigne->addPgmEnrolement($pgmEnseigne);
                }
            }
            $this->manager->persist($enseigne);
        }
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[".__LINE__."]", "[Migration] Enregistrement des enseignes");

        $this->manager->flush();

        $this->enseigneSFR = $this->doctrine->getRepository(Enseigne::class)->findOneBy(['ref' => "1"]);

        $this->admin = new Utilisateur();
        $this->admin->setNom("Admin");
        $this->admin->setPrenom("");
        $this->admin->setAdmin(true);
        $this->admin->setIdAui("admindep");
        $this->admin->setActif(true);
        $this->admin->setEnseigne($this->enseigneSFR);
        $this->manager->persist($this->admin);


        $this->setUtilisateur("RAVE","Eric","u163116",true);
        $this->setUtilisateur("VAPPEREAU","Nicolas","u166978",true);
        $this->setUtilisateur("VAPPEREAU","Emilie","u144914",true);
        $this->setUtilisateur("PERCHERON","Laure","u145143",true);
        $this->setUtilisateur("MASQUELEZ","Nathalie","u151907",true);
        $this->setUtilisateur("CORIOU","Corentin","u145169",true);
        $this->setUtilisateur("MOTELLA","Clotilde","u094723",true);
        $this->setUtilisateur("LABREUIL","Frédéric","u102849",true);
        $this->setUtilisateur("BERTHOU","Agnès","u152620",true);
        $this->setUtilisateur("SIX","Frédéric","u153529",true);
        $this->setUtilisateur("BOURLIER","Marie-Hélène","u153388",true);


        $this->clientInconnu = new Client();
        $this->clientInconnu->setRaisonSociale("Client Inconnu");
        $this->clientInconnu->setSiren("");
        $this->clientInconnu->setRef("");
        $this->clientInconnu->setActif(false);
        $this->clientInconnu->setEnseigne($this->enseigneSFR);
        $this->manager->persist($this->clientInconnu);

        $this->manager->flush();

/////////////////////////////////////  Traitement du fichier Client
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[".__LINE__."]", "[Migration] Traitement des clients");
        $handle = fopen("migv1/clients.csv", "r");
        $lineNumber = 1;
        $clients = array();
        while (($rawStr = fgets($handle)) != false) {
            $clients[] = str_getcsv($rawStr, ";");
            $lineNumber++;
        }
        foreach ($clients as $clientCsv) {
            $ref = $clientCsv[0];
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
                            $this->logger->writeLog(LogLevelEnum::Erreur, __METHOD__ . "[".__LINE__."]", "[Client] migration " . $ref . " pgm " . $pgm . " inconnu");
                    }
                }
            }
            $this->manager->persist($client);
        }
        fclose($handle);
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[".__LINE__."]", "[Migration] Enregistrement des clients");

        $this->manager->flush();

        $this->chargeDep();
        $this->chargeKnox();
        $this->chargeZT();
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[".__LINE__."]", "[Migration] Fin integration");
    }

    public function creeTerminaux(int $nb, $fabricant, $pgm, $client, $statut, $dt): void
    {

        for ($i = 1; $i <= $nb; $i++) {
            $terminal = new Terminal();
            $imei = rand(352236589000000, 352236589999999);
            $statut = rand(0, 3);

            $terminal->setFabricant($fabricant);
            $terminal->setNumeroIMEI($imei);
            $terminal->setStatut($statut);
            $terminal->setProgramme($pgm);
            $this->manager->persist($terminal);

            $enrolement = new Enrolement();
            $enrolement->setTerminal($terminal);
            $enrolement->setClient($client);
            $enrolement->setDate($dt);
            $this->manager->persist($enrolement);
        }
    }


    public function setPgmToClient($client, $pgm, $customerId): PgmClient
    {
        // Knox
        $pgmClient = new PgmClient();
        $pgmClient->setActif(true);
        $pgmClient->setClient($client);
        $pgmClient->setPgmEnrolement($pgm);
        $pgmClient->setCustomerId($customerId);
        $pgmClient->setEmail("");
        $pgmClient->setGestionSfr(false);
        $this->manager->persist($pgmClient);

        return $pgmClient;
    }

    public function createClient($rs, $referent, $enseigne, $utilisateur, $pgm = null, $customerId = null, $siren = ""): Client
    {
        $dt = date_create('now');
        $client = new Client();
        $client->setRaisonSociale($rs);
        $client->setReferent($referent);
        $client->setSiren($siren);
        $client->setActif(true);
        $this->manager->persist($client);


        $enseigne->addClient($client);
        $this->manager->persist($enseigne);

        $clientEstModifiePar = new ClientEstModifiePar();
        $clientEstModifiePar->setClient($client);
        $clientEstModifiePar->setUtilisateur($utilisateur);
        $clientEstModifiePar->setDate($dt);
        $this->manager->persist($clientEstModifiePar);

        if ($pgm) $this->setPgmToClient($client, $pgm, $customerId);

        return $client;
    }

    private function chargeFabricant(): void
    {
        $this->manager = $this->manager;
        $fabricant = new Fabricant();
        $fabricant->setLibelle("Alcatel");
        $fabricant->setCode("TCL");
        $this->manager->persist($fabricant);
        $this->fabricantApple = new Fabricant();
        $this->fabricantApple->setLibelle("Apple");
        $this->fabricantApple->setCode("Apple");
        $this->manager->persist($this->fabricantApple);
        $fabricant = new Fabricant();
        $fabricant->setLibelle("Ascom");
        $fabricant->setCode("Ascom");
        $this->manager->persist($fabricant);
        $fabricant = new Fabricant();
        $fabricant->setLibelle("BlackBerry");
        $fabricant->setCode("BlackBerry");
        $this->manager->persist($fabricant);
        $fabricant = new Fabricant();
        $fabricant->setLibelle("BLU");
        $fabricant->setCode("BLU");
        $this->manager->persist($fabricant);
        $fabricant = new Fabricant();
        $fabricant->setLibelle("Bluebird");
        $fabricant->setCode("Bluebird");
        $this->manager->persist($fabricant);
        $fabricant = new Fabricant();
        $fabricant->setLibelle("BQ");
        $fabricant->setCode("BQ");
        $this->manager->persist($fabricant);
        $fabricant = new Fabricant();
        $fabricant->setLibelle("Bullitt Group");
        $fabricant->setCode("BullittGroupLimited");
        $this->manager->persist($fabricant);
        $fabricant = new Fabricant();
        $fabricant->setLibelle("Cat");
        $fabricant->setCode("Cat");
        $this->manager->persist($fabricant);
        $fabricant = new Fabricant();
        $fabricant->setLibelle("Chainway");
        $fabricant->setCode("CHAINWAY");
        $this->manager->persist($fabricant);
        $fabricant = new Fabricant();
        $fabricant->setLibelle("CipherLab");
        $fabricant->setCode("CipherLab");
        $this->manager->persist($fabricant);
        $this->fabricantCrosscall = new Fabricant();
        $this->fabricantCrosscall->setLibelle("Crosscall");
        $this->fabricantCrosscall->setCode("CROSSCALL");
        $this->manager->persist($this->fabricantCrosscall);
        $fabricant = new Fabricant();
        $fabricant->setLibelle("Cyrus");
        $fabricant->setCode("Cyrus");
        $this->manager->persist($fabricant);
        $fabricant = new Fabricant();
        $fabricant->setLibelle("Datalogic");
        $fabricant->setCode("Datalogic");
        $this->manager->persist($fabricant);
        $fabricant = new Fabricant();
        $fabricant->setLibelle("ecom");
        $fabricant->setCode("Pepperl+Fuchs GmbH");
        $this->manager->persist($fabricant);
        $fabricant = new Fabricant();
        $fabricant->setLibelle("Elo Touch Solutions");
        $fabricant->setCode("Elo Touch Solutions");
        $this->manager->persist($fabricant);
        $fabricant = new Fabricant();
        $fabricant->setLibelle("Fujitsu");
        $fabricant->setCode("Fujitsu");
        $this->manager->persist($fabricant);
        $fabricant = new Fabricant();
        $fabricant->setLibelle("Getac");
        $fabricant->setCode("Getac");
        $this->manager->persist($fabricant);
        $fabricant = new Fabricant();
        $fabricant->setLibelle("Gigaset");
        $fabricant->setCode("Gigaset");
        $this->manager->persist($fabricant);
        $fabricant = new Fabricant();
        $fabricant->setLibelle("Google");
        $fabricant->setCode("Google");
        $this->manager->persist($fabricant);
        $fabricant = new Fabricant();
        $fabricant->setLibelle("Honeywell");
        $fabricant->setCode("Honeywell");
        $this->manager->persist($fabricant);
        $fabricant = new Fabricant();
        $fabricant->setLibelle("HTC");
        $fabricant->setCode("HTC");
        $this->manager->persist($fabricant);
        $fabricant = new Fabricant();
        $fabricant->setLibelle("Huawei");
        $fabricant->setCode("Huawei");
        $this->manager->persist($fabricant);
        $fabricant = new Fabricant();
        $fabricant->setLibelle("iWaylink");
        $fabricant->setCode("iWaylink");
        $this->manager->persist($fabricant);
        $fabricant = new Fabricant();
        $fabricant->setLibelle("Janam Technologies");
        $fabricant->setCode("Janam Technologies");
        $this->manager->persist($fabricant);
        $fabricant = new Fabricant();
        $fabricant->setLibelle("Kyocera");
        $fabricant->setCode("KYOCERA");
        $this->manager->persist($fabricant);
        $fabricant = new Fabricant();
        $fabricant->setLibelle("Lenovo");
        $fabricant->setCode("LENOVO");
        $this->manager->persist($fabricant);
        $fabricant = new Fabricant();
        $fabricant->setLibelle("LG Electronics");
        $fabricant->setCode("LGE");
        $this->manager->persist($fabricant);
        $fabricant = new Fabricant();
        $fabricant->setLibelle("M3 Mobile");
        $fabricant->setCode("M3Mobile");
        $this->manager->persist($fabricant);
        $fabricant = new Fabricant();
        $fabricant->setLibelle("MobileDemand");
        $fabricant->setCode("MobileDemand");
        $this->manager->persist($fabricant);
        $fabricant = new Fabricant();
        $fabricant->setLibelle("MobiWire");
        $fabricant->setCode("MobiWire");
        $this->manager->persist($fabricant);
        $fabricant = new Fabricant();
        $fabricant->setLibelle("Motorola");
        $fabricant->setCode("Motorola");
        $this->manager->persist($fabricant);
        $fabricant = new Fabricant();
        $fabricant->setLibelle("Motorola Solutions");
        $fabricant->setCode("Motorola Solutions");
        $this->manager->persist($fabricant);
        $fabricant = new Fabricant();
        $fabricant->setLibelle("mPTech");
        $fabricant->setCode("myPhone");
        $this->manager->persist($fabricant);
        $fabricant = new Fabricant();
        $fabricant->setLibelle("Multilaser");
        $fabricant->setCode("Multilaser");
        $this->manager->persist($fabricant);
        $fabricant = new Fabricant();
        $fabricant->setLibelle("Nokia (owned by HMD Global)");
        $fabricant->setCode("HMD Global");
        $this->manager->persist($fabricant);
        $fabricant = new Fabricant();
        $fabricant->setLibelle("OnePlus");
        $fabricant->setCode("OnePlus");
        $this->manager->persist($fabricant);
        $fabricant = new Fabricant();
        $fabricant->setLibelle("Oppo");
        $fabricant->setCode("OPPO");
        $this->manager->persist($fabricant);
        $fabricant = new Fabricant();
        $fabricant->setLibelle("Opticon");
        $fabricant->setCode("Opticon");
        $this->manager->persist($fabricant);
        $fabricant = new Fabricant();
        $fabricant->setLibelle("Panasonic");
        $fabricant->setCode("PANASONIC");
        $this->manager->persist($fabricant);
        $fabricant = new Fabricant();
        $fabricant->setLibelle("PointMobile");
        $fabricant->setCode("POINTMOBILE");
        $this->manager->persist($fabricant);
        $fabricant = new Fabricant();
        $fabricant->setLibelle("Positivo");
        $fabricant->setCode("Positivo");
        $this->manager->persist($fabricant);
        $fabricant = new Fabricant();
        $fabricant->setLibelle("Rhino Mobility");
        $fabricant->setCode("RHINO");
        $this->manager->persist($fabricant);
        $this->fabricantSamsung = new Fabricant();
        $this->fabricantSamsung->setLibelle("Samsung");
        $this->fabricantSamsung->setCode("Samsung");
        $this->manager->persist($this->fabricantSamsung);
        $fabricant = new Fabricant();
        $fabricant->setLibelle("Sharp");
        $fabricant->setCode("SHARP");
        $this->manager->persist($fabricant);
        $fabricant = new Fabricant();
        $fabricant->setLibelle("Sonim Technologies");
        $fabricant->setCode("Sonimtech");
        $this->manager->persist($fabricant);
        $fabricant = new Fabricant();
        $fabricant->setLibelle("Sony");
        $fabricant->setCode("Sony");
        $this->manager->persist($fabricant);
        $fabricant = new Fabricant();
        $fabricant->setLibelle("Spectralink");
        $fabricant->setCode("Spectralink");
        $this->manager->persist($fabricant);
        $fabricant = new Fabricant();
        $fabricant->setLibelle("TCL");
        $fabricant->setCode("TCL");
        $this->manager->persist($fabricant);
        $fabricant = new Fabricant();
        $fabricant->setLibelle("Unitech");
        $fabricant->setCode("Unitech_Electronics");
        $this->manager->persist($fabricant);
        $fabricant = new Fabricant();
        $fabricant->setLibelle("Urovo Technology");
        $fabricant->setCode("Urovo");
        $this->manager->persist($fabricant);
        $fabricant = new Fabricant();
        $fabricant->setLibelle("Vsmart");
        $fabricant->setCode("Vsmart");
        $this->manager->persist($fabricant);
        $fabricant = new Fabricant();
        $fabricant->setLibelle("Xiaomi");
        $fabricant->setCode("Xiaomi");
        $this->manager->persist($fabricant);
        $fabricant = new Fabricant();
        $fabricant->setLibelle("Wiko");
        $fabricant->setCode("WIKO");
        $this->manager->persist($fabricant);
        $fabricant = new Fabricant();
        $fabricant->setLibelle("Wishtel");
        $fabricant->setCode("Wishtel");
        $this->manager->persist($fabricant);
        $fabricant = new Fabricant();
        $fabricant->setLibelle("Zebra");
        $fabricant->setCode("Zebra Technologies");
        $this->manager->persist($fabricant);
        $fabricant = new Fabricant();
        $fabricant->setLibelle("ZTE");
        $fabricant->setCode("ZTE");
        $this->manager->persist($fabricant);
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
                if ($fabricant == null )  $fabricant = $this->fabricantCrosscall;
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
//                    if ($ref == "ZT1900014") dd($order);

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
        /** @var Orders $order */
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

    private function setUtilisateur(string $nom, string $prenom, string $idAui, bool $admin) : void
    {
        $utilisateur2 = new Utilisateur();
        $utilisateur2->setNom($nom);
        $utilisateur2->setPrenom($prenom);
        $utilisateur2->setIdAui($idAui);
        $utilisateur2->setAdmin($admin);
//        dd($this->enseigneSFR);
        $utilisateur2->setEnseigne($this->enseigneSFR);
        $utilisateur2->setActif(true);
        $this->manager->persist($utilisateur2);
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

    public static function getGroups(): array
    {
        return ['migration'];
    }
}

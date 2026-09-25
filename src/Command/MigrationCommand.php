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
use App\Service\AdminService;
use App\Service\EnrolementService;
use App\Service\LoggerESService;
use App\Service\SessionService;
use App\Toolbox\LogLevelEnum;
use App\Toolbox\StatutImeiEnum;
use App\Toolbox\StatutRequeteEnum;
use App\Toolbox\TypeEnrolementEnum;
use DateTime;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\RequestStack;

// ATTENTION: POINTER SUR LA BBD CIBLE !!!!!!!!!!!!!!
// php bin/console --env=dev --force doctrine:schema:drop
// php bin/console --env=dev doctrine:schema:create
// php bin/console app:migration
// the name of the command is what users type after "php bin/console app:migration"
#[AsCommand(
    name: 'app:migration',
    description: 'Cette command intègre les données des fichiers présent dans mig/uat sans effacer la base existante',
    hidden: false
)]
class MigrationCommand extends Command
{

    private $pgmApple;
    private $pgmKnox;
    private $pgmZT;
    private $admin;
    private $automate;

    private $clientInconnu;
    private $dtnow;
    private $MaxFlush=400;

    private Fabricant $fabricantApple;
    private Fabricant $fabricantSamsung;
    private Fabricant $fabricantCrosscall;

    private EnrolementService $enrolementService;
    private SessionService $sessionService;

    protected LoggerESService $logger;
    private AdminService $adminService;
    private ObjectManager $manager;
    private ManagerRegistry $doctrine;
    private Enseigne $enseigneSFR;
    private OutputInterface $output;

    public function __construct(LoggerESService $logger, ManagerRegistry $doctrine, AdminService $adminService)
    {
        $this->logger = $logger;
        $this->manager = $doctrine->getManager();
        $this->doctrine = $doctrine;
        $this->adminService = $adminService;
        ini_set("memory_limit", -1);
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->output = $output;

        $this->dtnow = date_create('now');

        $output->writeln(date_create('now')->format('Y-m-d H:i:s')." ". " Début migration");

        $output->writeln(date_create('now')->format('Y-m-d H:i:s')." "."Vidage des id");
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

        $output->writeln(date_create('now')->format('Y-m-d H:i:s')." "."Creation des pgm enrolement");
        $this->pgmApple = new PgmEnrolement();
        $this->pgmApple->setLibelle("Apple");
        $this->manager->persist($this->pgmApple);

        $this->pgmKnox = new PgmEnrolement();
        $this->pgmKnox->setLibelle("Knox");
        $this->manager->persist($this->pgmKnox);

        $this->pgmZT = new PgmEnrolement();
        $this->pgmZT->setLibelle("Zerotouch");
        $this->manager->persist($this->pgmZT);


        $output->writeln(date_create('now')->format('Y-m-d H:i:s')." "."Enseignes");
/////////////////////////////////////  Traitement du fichier Enseigne
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[".__LINE__."]", "[Migration] Traitement des enseignes");
        $handle = fopen("mig/uat/enseignes.csv", "r");
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

        $output->writeln(date_create('now')->format('Y-m-d H:i:s')." "."Flush");

        $this->manager->flush();

        $this->enseigneSFR = $this->doctrine->getRepository(Enseigne::class)->findOneBy(['ref' => "1"]);

        $output->writeln(date_create('now')->format('Y-m-d H:i:s')." "."Création des utilisateurs");
        $this->admin = new Utilisateur();
        $this->admin->setNom("Admin");
        $this->admin->setPrenom("");
        $this->admin->setAdmin(true);
        $this->admin->setIdAui("admindep");
        $this->admin->setActif(true);
        $this->admin->setEnseigne($this->enseigneSFR);
        $this->manager->persist($this->admin);

        $this->automate = new Utilisateur();
        $this->automate->setNom("Automate");
        $this->automate->setPrenom("");
        $this->automate->setAdmin(false);
        $this->automate->setIdAui("automate");
        $this->automate->setActif(true);
        $this->automate->setEnseigne($this->enseigneSFR);
        $this->manager->persist($this->automate);


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
        $this->setUtilisateur("NACER","Tlenda","u144924",true);

        $this->clientInconnu = new Client();
        $this->clientInconnu->setRaisonSociale("Client Inconnu");
        $this->clientInconnu->setSiren("");
        $this->clientInconnu->setRef("");
        $this->clientInconnu->setActif(false);
        $this->clientInconnu->setEnseigne($this->enseigneSFR);
        $this->manager->persist($this->clientInconnu);

        $output->writeln(date_create('now')->format('Y-m-d H:i:s')." "."Flush");


        $output->writeln(date_create('now')->format('Y-m-d H:i:s')." "."Clients");
/////////////////////////////////////  Traitement du fichier Client
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[".__LINE__."]", "[Migration] Traitement des clients");
        $handle = fopen("mig/uat/clients.csv", "r");
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

        $output->writeln(date_create('now')->format('Y-m-d H:i:s')." "."Flush");
        $this->clearAndResetDoctrine();

        $output->writeln(date_create('now')->format('Y-m-d H:i:s')." "."Apple");
        $this->chargeDep();
        $output->writeln(date_create('now')->format('Y-m-d H:i:s')." "."Knox");
        $this->chargeKnox();
        $output->writeln(date_create('now')->format('Y-m-d H:i:s')." "."ZT");
        $this->chargeZT();
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[".__LINE__."]", "[Migration] Fin integration");


        $output->writeln(date_create('now')->format('Y-m-d H:i:s')." ".$this->dtnow->format('Y-m-d H:i:s'). "  Fin migration");


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
            ->setHelp('Cette command intègre les données des fichiers présent dans mig/uat sans effacter la base existante')
        ;
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

    function chargeEnseigne(): void
    {
        /////////////////////////////////////  Traitement du fichier Enseigne
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "[Migration] Traitement des enseignes");
        $output->writeln(date_create('now')->format('Y-m-d H:i:s')." "."Traitement des enseignes");
        $handle = fopen("mig/uat/enseignes.csv", "r");
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
                    if ($enseigne == null) dd($ens);
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
    }

    protected function chargeClient() : void {
        /////////////////////////////////////  Traitement du fichier Client
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "[Migration] Traitement des clients");
        $this->output->writeln(date_create('now')->format('Y-m-d H:i:s')." "."Traitement des clients");
        $handle = fopen("mig/uat/clients.csv", "r");
        $lineNumber = 1;
        $clients = array();
        while (($rawStr = fgets($handle)) != false) {
            $clients[] = str_getcsv($rawStr, ";");
            $lineNumber++;
        }
        foreach ($clients as $clientCsv) {
            $ref = $clientCsv[0];
            $enseigneId = $clientCsv[2];
            /** @var Client $clientExistant */
            $clientExistant = $this->doctrine->getRepository(Client::class)->findOneBy(['ref' => $ref]);
            if ($enseigneId && $clientExistant->getEnseigne()->getRef() != $enseigneId) {
                   $this->output->write($ref . " [" . $clientCsv[1]. "][".$enseigneId."]");
                if ($clientExistant) {
                    $this->output->writeln(date_create('now')->format('Y-m-d H:i:s')." ".$clientExistant->getEnseigne()->getRef() . " - " . $enseigneId);
                } else {
                    $this->output->writeln(date_create('now')->format('Y-m-d H:i:s')." "."ABSENT");
                    break;
                }

                   $enseigne = $this->doctrine->getRepository(Enseigne::class)->findOneBy(['ref' => $enseigneId]);
                   if (!$enseigne) {
                       $this->output->writeln(date_create('now')->format('Y-m-d H:i:s')." "."Nouvelle enseigne [" . $enseigne->getId());
                   } else {
                       $clientExistant->setEnseigne($enseigne);
                       $this->manager->persist($clientExistant);
                   }
//                   dd($enseigne);
            }
//            $client = new Client();
//            $client->setRaisonSociale($clientCsv[1]);
//            $client->setSiren($clientCsv[7]);
//            $client->setRef($ref);
//            $client->setActif(true);
//            $client->setEnseigne($enseigne);
//            $pgms = str_getcsv($clientCsv[3], "/");
//            $customerIds = str_getcsv($clientCsv[4], "/");
//            $pgmsNb = count($pgms);
//            for ($i = 0; $i < $pgmsNb; $i++) {
//                $pgm = $pgms[$i];
//                $customerId = "";
//                if (count($customerIds) > $i) $customerId = $customerIds[$i];
//                if ($customerId && $customerId != "") {
//                    $pgmClient = new PgmClient();
//                    $pgmClient->setClient($client);
//                    $pgmClient->setActif(true);
//                    $pgmClient->setCustomerId($customerId);
//                    switch ($pgm) {
//                        case "depApple" :
//                            $pgmClient->setPgmEnrolement($this->pgmApple);
//                            $this->manager->persist($pgmClient);
//                            $client->addPgmEnrolement($pgmClient);
//                            break;
//                        case "ZeroTouch" :
//                            $pgmClient->setPgmEnrolement($this->pgmZT);
//                            $this->manager->persist($pgmClient);
//                            $client->addPgmEnrolement($pgmClient);
//                            break;
//                        case "knox" :
//                            $pgmClient->setPgmEnrolement($this->pgmKnox);
//                            $this->manager->persist($pgmClient);
//                            $client->addPgmEnrolement($pgmClient);
//                            break;
//                        default:
//                            $this->logger->writeLog(LogLevelEnum::Erreur, __METHOD__ . "[" . __LINE__ . "]", "[Client] migration " . $ref . " pgm " . $pgm . " inconnu");
//                    }
//                }
//            }
//            $this->manager->persist($client);
        }
//        $this->output->writeln(date_create('now')->format('Y-m-d H:i:s')." "."Enregistrement des clients");
//
        $this->manager->flush();
        fclose($handle);
    }

    private function chargeKnox(): void
    {
        /////////////////////////////////////  Traitement du fichier orders KNOX
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[".__LINE__."]", "[Migration] Traitement des commandes Knox");
        $handle = fopen("mig/uat/orders_knox.csv", "r");
        $lineNumber = 1;
        $compteur=0;
        while (($rawStr = fgets($handle)) != false) {
            try {
                $ref="";
                $commandesCsv = str_getcsv($rawStr, ";");
                $lineNumber++;
                $compteur++;
                if ($compteur>=$this->MaxFlush) {
                    $this->output->writeln(date_create('now')->format('Y-m-d H:i:s')." ".$lineNumber);
                    $compteur=0;
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
        fclose($handle);

        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[".__LINE__."]", "[Migration] Enregistrement des commandes Knox");
        $this->output->writeln(date_create('now')->format('Y-m-d H:i:s')." "."Flush des commandes");
        $this->clearAndResetDoctrine();

        /////////////////////////////////////  Traitement du fichier orders_transaction_dep.csv
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[".__LINE__."]", "[Migration] Traitement des transactions Knox");

        $this->output->writeln(date_create('now')->format('Y-m-d H:i:s')." "."Transactions Knox");

        $handle = fopen("mig/uat/orders_transaction_knox.csv", "r");
        $lineNumber = 1;
        $refPrec = false;
        $imeiArray = array();
        $ref = "";
        $transaction = false;
        $order = null;
        $lastTypeTransac = null;
        $compteur=0;

        while (($rawStr = fgets($handle)) != false) {
            try {
                $transactionCsv = str_getcsv($rawStr, ";");
                $lineNumber++;
                $compteur++;
                if ($compteur>=$this->MaxFlush) {
                    $this->output->writeln(date_create('now')->format('Y-m-d H:i:s')." ".$lineNumber);
                    $compteur=0;
                    $this->clearAndResetDoctrine();
                }
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
                $order =null;
                $order = $this->doctrine->getRepository(Orders::class)->findOneBy(['ref' => $ref]);

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
                $this->manager->persist($order);
                $this->manager->persist($transaction);

            } catch
            (\Exception $exception) {
                $this->logger->writeLog(LogLevelEnum::Critique, __METHOD__ . "[".__LINE__."]", "[Migration][Orders][" . $ref . "] Exception " . $exception->getMessage() . "[" . json_encode($transactionCsv));
//                dd($rawStr);
            }
        }
        fclose($handle);

        if ($transaction) {
            $transaction->setLastTransacOnType(true);
            $this->manager->persist($transaction);
        }

        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[".__LINE__."]", "[Migration] Enregistrement des transactions Samsung");
        $this->clearAndResetDoctrine();

    }

    private function chargeDep(): void
    {
        /////////////////////////////////////  Traitement du fichier orders DEP
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[".__LINE__."]", "[Migration] Traitement des commandes DEP");
        $handle = fopen("mig/uat/orders_dep.csv", "r");
        $lineNumber = 1;
        $compteur=0;
        while (($rawStr = fgets($handle)) != false) {
            try {
                $ref="";
                $commandesCsv = str_getcsv($rawStr, ";");
                $lineNumber++;
                $compteur++;
                if ($compteur>=$this->MaxFlush) {
                    $this->output->writeln(date_create('now')->format('Y-m-d H:i:s')." ".$lineNumber);
                    $compteur=0;
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
        fclose($handle);
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[".__LINE__."]", "[Migration] Enregistrement des commandes DEP");

        $this->output->writeln(date_create('now')->format('Y-m-d H:i:s')." "."Flush des commandes");
        $this->clearAndResetDoctrine();

        /////////////////////////////////////  Traitement du fichier orders_transaction_dep.csv
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[".__LINE__."]", "[Migration] Traitement des transactions DEP");

        $this->output->writeln(date_create('now')->format('Y-m-d H:i:s')." "."Transactions DEP");

        $handle = fopen("mig/uat/orders_transaction_dep.csv", "r");
        $lineNumber = 1;
        $refPrec = false;
        $transaction = false;
        $order = null;
        $lastTypeTransac=null;
        $imeiArray = array();

        while (($rawStr = fgets($handle)) != false) {
            try {
                $transactionCsv = str_getcsv($rawStr, ";");
                $lineNumber++;
                $compteur++;
                if ($compteur>=$this->MaxFlush) {
                    $this->output->writeln(date_create('now')->format('Y-m-d H:i:s')." ".$lineNumber);
                    $compteur=0;
                    $this->clearAndResetDoctrine();
                }

                $ref = $transactionCsv[0] ?? "";
                $order = $this->doctrine->getRepository(Orders::class)->findOneBy(['ref' => $ref]);
                if ($order == null) {
                    $this->logger->writeLog(LogLevelEnum::Erreur, __METHOD__ . "[".__LINE__."]", "[Migration] Référence ".$ref." introuvable dans les commandes");
                    continue;
                }

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
                $this->manager->persist($order);
                $this->manager->persist($transaction);
            } catch
            (\Exception $exception) {
                $this->logger->writeLog(LogLevelEnum::Critique, __METHOD__ . "[".__LINE__."]", "[Migration][Orders][" . $ref . "] Exception " . $exception->getMessage() . "[" . json_encode($transactionCsv));
                dd($rawStr);
            }
        }
        fclose($handle);

//        if ($transaction) {
//            $transaction->setLastTransacOnType(true);
//            $this->manager->persist($transaction);
//        }

        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[".__LINE__."]", "[Migration] Enregistrement des transactions DEP");
        $this->clearAndResetDoctrine();

    }

    private function chargeZT(): void
    {
        /////////////////////////////////////  Traitement du fichier orders ZT
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[".__LINE__."]", "[Migration] Traitement des commandes ZT");
        $handle = fopen("mig/uat/orders_zt.csv", "r");
        $lineNumber = 1;
        $compteur=0;
        while (($rawStr = fgets($handle)) != false) {
            try {
                $ref = "";
                $commandesCsv = str_getcsv($rawStr, ";");
                $lineNumber++;
                $compteur++;
                if ($compteur>=$this->MaxFlush) {
                    $this->output->writeln(date_create('now')->format('Y-m-d H:i:s')." ".$lineNumber);
                    $compteur=0;
 //                   $this->clearAndResetDoctrine();

                }
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
        fclose($handle);

        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[".__LINE__."]", "[Migration] Enregistrement des commandes ZT");

        $this->output->writeln(date_create('now')->format('Y-m-d H:i:s')." "."Flush des commandes");
        $this->clearAndResetDoctrine();

        /////////////////////////////////////  Traitement du fichier orders_transaction_dep.csv
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[".__LINE__."]", "[Migration] Traitement des transactions ZT");

        $this->output->writeln(date_create('now')->format('Y-m-d H:i:s')." "."Transactions ZT");

        $handle = fopen("mig/uat/orders_transaction_zt.csv", "r");
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
                $compteur++;
                if ($compteur>=$this->MaxFlush) {
                    $this->output->writeln(date_create('now')->format('Y-m-d H:i:s')." ".$lineNumber);
                    $compteur=0;
                    $this->clearAndResetDoctrine();
                }

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
                /** @var Orders $order */
                $order = null;
                $order = $this->doctrine->getRepository(Orders::class)->findOneBy(['ref' => $ref]);
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
                        }
                        else {
                            /** @var Orders $order */
                            $imeiBase = new Terminal();
                            $imeiBase->setFabricant($order->getFabricant());
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

                $this->manager->persist($order);
                $this->manager->persist($transaction);

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
        fclose($handle);

        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[".__LINE__."]", "[Migration] Enregistrement des transactions ZT");
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

    private function clearAndResetDoctrine():void
    {
        $this->output->writeln(date_create('now')->format('Y-m-d H:i:s')." Before ".memory_get_usage());
        $this->manager->flush();
        unset($this->clientInconnu);
        unset($this->enseigneSFR);
        unset($this->admin);
        unset($this->pgmApple);
        unset($this->pgmKnox);
        unset($this->pgmZT);
        unset($this->fabricantSamsung );
        unset($this->fabricantApple);
        unset($this->fabricantCrosscall);
        $this->manager->clear();
        $this->output->writeln(date_create('now')->format('Y-m-d H:i:s')." After ".memory_get_usage());
        $this->clientInconnu = $this->doctrine->getRepository(Client::class)->find("1");
        $this->enseigneSFR = $this->doctrine->getRepository(Enseigne::class)->findOneBy(['ref' => "1"]);
        $this->admin = $this->doctrine->getRepository(Utilisateur::class)->find("1");
        $this->pgmApple = $this->doctrine->getRepository(PgmEnrolement::class)->find("1");
 	    $this->pgmKnox = $this->doctrine->getRepository(PgmEnrolement::class)->find("2");
        $this->pgmZT = $this->doctrine->getRepository(PgmEnrolement::class)->find("3");
 	    $this->fabricantSamsung  = $this->doctrine->getRepository(Fabricant::class)->findOneBy(['code' => "Samsung"]);
        $this->fabricantApple = $this->doctrine->getRepository(Fabricant::class)->findOneBy(['code' => "Apple"]);
		$this->fabricantCrosscall = $this->doctrine->getRepository(Fabricant::class)->findOneBy(['code' => "CROSSCALL"]);
    }
}
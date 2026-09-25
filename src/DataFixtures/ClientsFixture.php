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
use App\Toolbox\StatutEnrolementEnum;
use App\Toolbox\StatutImeiEnum;
use App\Toolbox\TypeEnrolementEnum;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;

// php bin/console --env=test --purge-with-truncate doctrine:fixtures:load --group=clients
// php bin/console --env=dev --purge-with-truncate doctrine:fixtures:load --group=clients
class ClientsFixture extends Fixture implements FixtureGroupInterface
{
    private $pgmApple;
    private $pgmKnox;
    private $pgmZT;
    private $manager;
    private $doctrine;

    private Fabricant $fabricantApple;
    private Fabricant $fabricantSamsung;
    private Fabricant $fabricantCrosscall;

    private Enseigne $enseigne;
private PgmEnrolement $pgmTmp;
    private EnrolementService $enrolementService;
    private SessionService $sessionService;
    private LoggerESService $logger;
    private Utilisateur $utilisateur;

    public function __construct(EnrolementService $enrolementService, ManagerRegistry $doctrine, SessionService $sessionSrvice, LoggerESService $logger)
    {
        $this->enrolementService = $enrolementService;
        $this->doctrine = $doctrine;
        $this->sessionService = $sessionSrvice;
    }


    public function load(ObjectManager $manager): void
    {
        $this->manager = $manager;

        $conn = $this->manager->getConnection();
        $stmt = $conn->prepare('ALTER SEQUENCE client_est_modifie_par_id_seq RESTART WITH 1'); $resultSet = $stmt->execute();
        $stmt = $conn->prepare('ALTER SEQUENCE client_id_seq RESTART WITH 1');$resultSet = $stmt->execute();
        $stmt = $conn->prepare('ALTER SEQUENCE enrolement_id_seq RESTART WITH 1');$resultSet = $stmt->execute();
        $stmt = $conn->prepare('ALTER SEQUENCE enseigne_est_modifie_par_id_seq RESTART WITH 1');$resultSet = $stmt->execute();
        $stmt = $conn->prepare('ALTER SEQUENCE enseigne_id_seq RESTART WITH 1');$resultSet = $stmt->execute();
        $stmt = $conn->prepare('ALTER SEQUENCE fabricant_id_seq RESTART WITH 1');$resultSet = $stmt->execute();
        $stmt = $conn->prepare('ALTER SEQUENCE order_transaction_id_seq RESTART WITH 1');$resultSet = $stmt->execute();
        $stmt = $conn->prepare('ALTER SEQUENCE orders_id_seq RESTART WITH 1');$resultSet = $stmt->execute();
        $stmt = $conn->prepare('ALTER SEQUENCE pgm_client_id_seq RESTART WITH 1');$resultSet = $stmt->execute();
        $stmt = $conn->prepare('ALTER SEQUENCE pgm_enrolement_id_seq RESTART WITH 1');$resultSet = $stmt->execute();
        $stmt = $conn->prepare('ALTER SEQUENCE pgm_enseigne_id_seq RESTART WITH 1');$resultSet = $stmt->execute();
        $stmt = $conn->prepare('ALTER SEQUENCE requete_id_seq RESTART WITH 1');$resultSet = $stmt->execute();
        $stmt = $conn->prepare('ALTER SEQUENCE terminal_id_seq RESTART WITH 1');$resultSet = $stmt->execute();
        $stmt = $conn->prepare('ALTER SEQUENCE terminal_suivi_id_seq RESTART WITH 1');$resultSet = $stmt->execute();
        $stmt = $conn->prepare('ALTER SEQUENCE utilisateur_id_seq RESTART WITH 1');$resultSet = $stmt->execute();
        $stmt = $conn->prepare('ALTER SEQUENCE utilisateur_modifie_par_id_seq RESTART WITH 1');$resultSet = $stmt->execute();


        $datetime = date_create('now');

        $enseigne = new Enseigne();
        $enseigne->setRaisonSociale("SFR");
        $enseigne->setReferent("");
        $enseigne->setCommentaire("");

        $this->enseigne = $enseigne;

        $enseigne2 = new Enseigne();
        $enseigne2->setRaisonSociale("Coriolis");
        $enseigne2->setReferent("");
        $enseigne2->setCommentaire("");

        $automate = new Utilisateur();
        $automate->setNom("Automate");
        $automate->setPrenom("");
        $automate->setAdmin(false);
        $automate->setIdAui("");
        $automate->setEnseigne($enseigne);
        $automate->setActif(false);
        $manager->persist($automate);

        $admin = new Utilisateur();
        $admin->setNom("RAVE");
        $admin->setPrenom("Eric");
        $admin->setAdmin(true);
        $admin->setIdAui("u163116");
        $admin->setEnseigne($enseigne2);
        $admin->setActif(true);
        $manager->persist($admin);

        $utilisateur = new Utilisateur();
        $utilisateur->setNom("VAPPEREAU");
        $utilisateur->setPrenom("Nicolas");
        $utilisateur->setIdAui("u166978");
        $utilisateur->setAdmin(false);
        $utilisateur->setEnseigne($enseigne);
        $utilisateur->setActif(true);
        $manager->persist($utilisateur);

        $utilisateur2 = new Utilisateur();
        $utilisateur2->setNom("VAPPEREAU");
        $utilisateur2->setPrenom("Emilie");
        $utilisateur2->setIdAui("u144914");
        $utilisateur2->setAdmin(false);
        $utilisateur2->setEnseigne($enseigne);
        $utilisateur2->setActif(true);
        $manager->persist($utilisateur2);


        $this->utilisateur = $utilisateur;

        $utilModifierPar = new UtilisateurModifiePar();
        $utilModifierPar->setUtilModifie($utilisateur);
        $utilModifierPar->setUtilModifiePar($admin);
        $utilModifierPar->setDate($datetime);
        $manager->persist($utilModifierPar);

        $utilModifierPar = new UtilisateurModifiePar();
        $utilModifierPar->setUtilModifie($utilisateur2);
        $utilModifierPar->setUtilModifiePar($admin);
        $utilModifierPar->setDate($datetime);
        $manager->persist($utilModifierPar);

        $this->pgmApple = new PgmEnrolement();
        $this->pgmApple->setLibelle("Apple");
        $manager->persist($this->pgmApple);

        $this->pgmKnox = new PgmEnrolement();
        $this->pgmKnox->setLibelle("Knox");
        $manager->persist($this->pgmKnox);

        $this->pgmZT = new PgmEnrolement();
        $this->pgmZT->setLibelle("Zerotouch");
        $manager->persist($this->pgmZT);


        // CLIENTS ////////////////////////////////////////////////////////////////////

        $clientMV = $this->createClient("KEP Test1", "Eric RAVE", $enseigne, $utilisateur, $this->pgmKnox, "3995610410", "483443891");
        $clientDIOR = $this->createClient("DIOR", "Matt Damon", $enseigne, $utilisateur, $this->pgmKnox, "25656564");
        $this->setPgmToClient($clientDIOR,$this->pgmApple,"54646464");
        $clientVINCI = $this->createClient("VINCI", "Johny Autoroute", $enseigne, $utilisateur, $this->pgmKnox, "75658884");
        $this->setPgmToClient($clientVINCI,$this->pgmApple,"98897987");
        $clientTA = $this->createClient("TOURAINE ALARME", "Frédéric NEVEU", $enseigne, $utilisateur, $this->pgmKnox, "54646464");
        $this->setPgmToClient($clientTA,$this->pgmApple,"658886464");
        $clientACCORD = $this->createClient("ACCORD", "Joe BIDEN", $enseigne, $utilisateur, $this->pgmKnox, "646466464");
        $this->setPgmToClient($clientACCORD,$this->pgmApple,"64444464");
        $clientFNAC = $this->createClient("FNAC", "James DEAN", $enseigne, $utilisateur, $this->pgmKnox, "546466564");
        $this->setPgmToClient($clientFNAC,$this->pgmApple,"6532131464");

        $clientTOTAL = $this->createClient("TOTAL", "Sophie ALBET", $enseigne, $utilisateur, $this->pgmKnox, "446466564");
        $this->setPgmToClient($clientTOTAL,$this->pgmApple,"6546464464");
        $clientAUCHAN = $this->createClient("AUCHAN", "Eric FLUTE", $enseigne, $utilisateur, $this->pgmKnox, "346466564");
        $this->setPgmToClient($clientAUCHAN,$this->pgmApple,"611116464");
        $clientSKF = $this->createClient("SKF", "Thierry DEAL", $enseigne, $utilisateur, $this->pgmKnox, "246466564");
        $this->setPgmToClient($clientSKF,$this->pgmApple,"333336464");
        $clientSNCF = $this->createClient("SNCF", "Marie AUNEZ", $enseigne, $utilisateur, $this->pgmKnox, "146466564");
        $this->setPgmToClient($clientSNCF,$this->pgmApple,"6546646464");
        $clientBOUYGUES = $this->createClient("BOUYGUES", "Thomas NIKE", $enseigne, $utilisateur, $this->pgmKnox, "8456466564");
        $this->setPgmToClient($clientBOUYGUES,$this->pgmApple,"444446464");
        $clientCHANTEAU = $this->createClient("CHANTEAUX", "Albert Einstein", $enseigne, $utilisateur, $this->pgmKnox, "346463264");
        $this->setPgmToClient($clientCHANTEAU,$this->pgmApple,"654633334");



        $this->setPgmToClient($clientMV,$this->pgmApple,"654646464");
        $this->setPgmToClient($clientMV,$this->pgmApple,"888888888");
        $this->setPgmToClient($clientMV,$this->pgmApple,"111111111");
//        $this->setPgmToClient($clientMV,$this->pgmApple,"222222222");
//        $this->setPgmToClient($clientMV,$this->pgmApple,"333333333");
        $this->setPgmToClient($clientMV,$this->pgmZT,"1233123213");



//       $this->load10Client($manager);
//        $this->load2Client($manager);


        //////////////////////////////////////////////////////////////////////

        $enseigneModifiePar = new EnseigneEstModifiePar();
        $enseigneModifiePar->setEnseigne($enseigne2);
        $enseigneModifiePar->setUtilisateur($admin);
        $enseigneModifiePar->setDate($datetime);
        $manager->persist($enseigneModifiePar);

        $resellerId = "7854573063";
        $pgmEnseigne = new PgmEnseigne();
        $pgmEnseigne->setPgmEnrolement($this->pgmKnox);
        $pgmEnseigne->setResellerId($resellerId);
        $pgmEnseigne->setEnseignes($enseigne2);
        $manager->persist($pgmEnseigne);

        $resellerId = "0000657274";
        $pgmEnseigne = new PgmEnseigne();
        $pgmEnseigne->setPgmEnrolement($this->pgmApple);
        $pgmEnseigne->setResellerId($resellerId);
        $pgmEnseigne->setEnseignes($enseigne2);
        $manager->persist($pgmEnseigne);

        //////////////////////////////////////////////////////////////////////

        $enseigneModifiePar = new EnseigneEstModifiePar();
        $enseigneModifiePar->setEnseigne($enseigne);
        $enseigneModifiePar->setUtilisateur($admin);
        $enseigneModifiePar->setDate($datetime);
        $manager->persist($enseigneModifiePar);

        $resellerId = "7859461152";
        $pgmEnseigne = new PgmEnseigne();
        $pgmEnseigne->setPgmEnrolement($this->pgmKnox);
        $pgmEnseigne->setResellerId($resellerId);
        $pgmEnseigne->setEnseignes($enseigne);
        $manager->persist($pgmEnseigne);

        $resellerId = "YYYYYYYYY";
        $pgmEnseigne = new PgmEnseigne();
        $pgmEnseigne->setPgmEnrolement($this->pgmApple);
        $pgmEnseigne->setResellerId($resellerId);
        $pgmEnseigne->setEnseignes($enseigne);
        $manager->persist($pgmEnseigne);

        $resellerId = "1155496840";
        $pgmEnseigne = new PgmEnseigne();
        $pgmEnseigne->setPgmEnrolement($this->pgmZT);
        $pgmEnseigne->setResellerId($resellerId);
        $pgmEnseigne->setEnseignes($enseigne);
        $manager->persist($pgmEnseigne);

        $manager->persist($admin);
        $manager->persist($utilisateur);


        $datetime = date_create('now');
        $statut = 1;

        $this->chargeFabricant();

        $manager->flush();

//        $this->creeTerminaux(5, $this->fabricantSamsung, $this->pgmKnox, $clientACCORD, $statut, $datetime);
//        $this->creeTerminaux(6, $this->fabricantSamsung, $this->pgmKnox, $clientMV, $statut, $datetime);
//        $this->creeTerminaux(3, $this->fabricantSamsung, $this->pgmKnox, $clientTA, $statut, $datetime);
//        $this->creeTerminaux(4, $this->fabricantSamsung, $this->pgmKnox, $clientVINCI, $statut, $datetime);
//        $this->creeTerminaux(2, $this->fabricantSamsung, $this->pgmKnox, $clientDIOR, $statut, $datetime);
//        $this->creeTerminaux(5, $this->fabricantSamsung, $this->pgmKnox, $clientFNAC, $statut, $datetime);
//
//        $this->creeTerminaux(3, $this->fabricantSamsung, $this->pgmKnox, $clientAUCHAN, $statut, $datetime);
//        $this->creeTerminaux(4, $this->fabricantSamsung, $this->pgmKnox, $clientSKF, $statut, $datetime);
//        $this->creeTerminaux(6, $this->fabricantSamsung, $this->pgmKnox, $clientBOUYGUES, $statut, $datetime);
//
//       $this->creeTerminaux(4, $this->fabricantApple, $this->pgmApple, $clientAUCHAN, $statut, $datetime);
//       $this->creeTerminaux(3, $this->fabricantApple, $this->pgmApple, $clientSKF, $statut, $datetime);
//
//        $this->creeTerminaux(2, $this->fabricantApple, $this->pgmApple, $clientSNCF, $statut, $datetime);
//        $this->creeTerminaux(5, $this->fabricantApple, $this->pgmApple, $clientBOUYGUES, $statut, $datetime);
//
//        $this->creeTerminaux(1, $this->fabricantSamsung, $this->pgmKnox, $clientSNCF, $statut, $datetime);

        $datetime->sub(new \DateInterval("P1M"));

        $datetime = new \DateTime('now');

        $manager->flush();


        // Enrolement
        $terminaux = array("356809112293315","352983115844087");
        $retour = $this->enrolementService->enrolement($utilisateur, $this->pgmApple, "654646464", $enseigne, "Enrolement 1", $terminaux, $this->fabricantApple);

        $terminaux = array("356558083681400","357850270644310");
        $retour = $this->enrolementService->enrolement($utilisateur, $this->pgmApple, "888888888", $enseigne, "Enrolement 2", $terminaux, $this->fabricantApple);
//
        $this->enrolementService->processCommandesByStatus();
        $this->enrolementService->processCommandesByStatus();


        //
//        $manager->flush();
//        $terminalEntity = $this->doctrine->getRepository(Terminal::class)->findByIMEIOrSerie(356809112293315);
//
//        //$manager->flush();
////        /** @var OrderTransaction $ll */
////        $ll = $this->doctrine->getRepository(Orders::class)->find("1");
////        $this->enrolementService->procedeEnrolement($ll);
// //       $t = $ll->getTerminalSuivis();
//
//        $this->enrolementService->processCommandesByStatus();
//        $terminaux = array("357211090031719");
//        $retour = $this->enrolementService->enrolement($utilisateur, $this->pgmApple, "654646464", $enseigne, "Enrolement 2", $terminaux, $this->fabricantApple);
//        $this->enrolementService->processCommandesByStatus();
//        $terminaux = array("869773045874999");
//        $retour = $this->enrolementService->enrolement($utilisateur, $this->pgmApple, "654646464", $enseigne, "Enrolement 3", $terminaux, $this->fabricantApple);
    }

    public function creeTerminaux(int $nb, $fabricant,PgmEnrolement $pgm, Client $client, $statut, $datetime): void
    {

        $numCmd = "Commande ". rand();
        $this->pgmTmp = $pgm;
        $t=$client->getPgmEnrolements();
        $pgmClients = $client->getPgmEnrolements()->filter(function ($value) {
            return $value->getPgmEnrolement()->getId() == $this->pgmTmp->getId();
        });

         /** @var PgmClient $pgmClient */
        $pgmClient = null;
        if ($pgmClients) $pgmClient = $pgmClients->first();

        if ($pgmClient) {
            $imei = array();
            for ($i = 1; $i <= $nb; $i++) {
                $imei = array(rand(352236589000000, 352236589999999));
                $retour = $this->enrolementService->enrolement($this->utilisateur, $pgm, $pgmClient->getCustomerId(), $this->enseigne, $numCmd, $imei, $fabricant);
            }
        }
        else
        {
            dd($pgmClient);
        }
    }


    public function setPgmToClient(Client $client, $pgm,$customerId): PgmClient
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
        $client->addPgmEnrolement($pgmClient);

        return $pgmClient;
    }

    public function createClient($rs, $referent, $enseigne, $utilisateur, $pgm = null, $customerId=null, $siren = "" ): Client
    {
        $datetime = date_create('now');
        $client = new Client();
        $client->setRaisonSociale($rs);
        $client->setReferent($referent);
        if ($siren === "" ) $siren = rand(200000000, 999999999);
        $client->setSiren($siren);
        $client->setActif(true);
        $this->manager->persist($client);


        $enseigne->addClient($client);
        $this->manager->persist($enseigne);

        $clientEstModifiePar = new ClientEstModifiePar();
        $clientEstModifiePar->setClient($client);
        $clientEstModifiePar->setUtilisateur($utilisateur);
        $clientEstModifiePar->setDate($datetime);
        $this->manager->persist($clientEstModifiePar);

        if ($pgm) $this->setPgmToClient($client,$pgm,$customerId);

        return $client;
    }

    private function chargeFabricant(): void
    {
        $manager = $this->manager;
        $fabricant = new Fabricant(); $fabricant->setLibelle("Alcatel"); $fabricant->setCode("TCL"); $manager->persist($fabricant);
        $this->fabricantApple = new Fabricant(); $this->fabricantApple->setLibelle("Apple"); $this->fabricantApple->setCode("Apple"); $manager->persist($this->fabricantApple);
        $fabricant = new Fabricant(); $fabricant->setLibelle("Ascom"); $fabricant->setCode("Ascom"); $manager->persist($fabricant);
        $fabricant = new Fabricant(); $fabricant->setLibelle("BlackBerry"); $fabricant->setCode("BlackBerry"); $manager->persist($fabricant);
        $fabricant = new Fabricant(); $fabricant->setLibelle("BLU"); $fabricant->setCode("BLU"); $manager->persist($fabricant);
        $fabricant = new Fabricant(); $fabricant->setLibelle("Bluebird"); $fabricant->setCode("Bluebird"); $manager->persist($fabricant);
        $fabricant = new Fabricant(); $fabricant->setLibelle("BQ"); $fabricant->setCode("BQ"); $manager->persist($fabricant);
        $fabricant = new Fabricant(); $fabricant->setLibelle("Bullitt Group"); $fabricant->setCode("BullittGroupLimited"); $manager->persist($fabricant);
        $fabricant = new Fabricant(); $fabricant->setLibelle("Cat"); $fabricant->setCode("Cat"); $manager->persist($fabricant);
        $fabricant = new Fabricant(); $fabricant->setLibelle("Chainway"); $fabricant->setCode("CHAINWAY"); $manager->persist($fabricant);
        $fabricant = new Fabricant(); $fabricant->setLibelle("CipherLab"); $fabricant->setCode("CipherLab"); $manager->persist($fabricant);
        $this->fabricantCrosscall = new Fabricant(); $this->fabricantCrosscall->setLibelle("Crosscall"); $this->fabricantCrosscall->setCode("CROSSCALL"); $manager->persist($this->fabricantCrosscall);
        $fabricant = new Fabricant(); $fabricant->setLibelle("Cyrus"); $fabricant->setCode("Cyrus"); $manager->persist($fabricant);
        $fabricant = new Fabricant(); $fabricant->setLibelle("Datalogic"); $fabricant->setCode("Datalogic"); $manager->persist($fabricant);
        $fabricant = new Fabricant(); $fabricant->setLibelle("ecom"); $fabricant->setCode("Pepperl+Fuchs GmbH"); $manager->persist($fabricant);
        $fabricant = new Fabricant(); $fabricant->setLibelle("Elo Touch Solutions"); $fabricant->setCode("Elo Touch Solutions"); $manager->persist($fabricant);
        $fabricant = new Fabricant(); $fabricant->setLibelle("Fujitsu"); $fabricant->setCode("Fujitsu"); $manager->persist($fabricant);
        $fabricant = new Fabricant(); $fabricant->setLibelle("Getac"); $fabricant->setCode("Getac"); $manager->persist($fabricant);
        $fabricant = new Fabricant(); $fabricant->setLibelle("Gigaset"); $fabricant->setCode("Gigaset"); $manager->persist($fabricant);
        $fabricant = new Fabricant(); $fabricant->setLibelle("Google"); $fabricant->setCode("Google"); $manager->persist($fabricant);
        $fabricant = new Fabricant(); $fabricant->setLibelle("Honeywell"); $fabricant->setCode("Honeywell"); $manager->persist($fabricant);
        $fabricant = new Fabricant(); $fabricant->setLibelle("HTC"); $fabricant->setCode("HTC"); $manager->persist($fabricant);
        $fabricant = new Fabricant(); $fabricant->setLibelle("Huawei"); $fabricant->setCode("Huawei"); $manager->persist($fabricant);
        $fabricant = new Fabricant(); $fabricant->setLibelle("iWaylink"); $fabricant->setCode("iWaylink"); $manager->persist($fabricant);
        $fabricant = new Fabricant(); $fabricant->setLibelle("Janam Technologies"); $fabricant->setCode("Janam Technologies"); $manager->persist($fabricant);
        $fabricant = new Fabricant(); $fabricant->setLibelle("Kyocera"); $fabricant->setCode("KYOCERA"); $manager->persist($fabricant);
        $fabricant = new Fabricant(); $fabricant->setLibelle("Lenovo"); $fabricant->setCode("LENOVO"); $manager->persist($fabricant);
        $fabricant = new Fabricant(); $fabricant->setLibelle("LG Electronics"); $fabricant->setCode("LGE"); $manager->persist($fabricant);
        $fabricant = new Fabricant(); $fabricant->setLibelle("M3 Mobile"); $fabricant->setCode("M3Mobile"); $manager->persist($fabricant);
        $fabricant = new Fabricant(); $fabricant->setLibelle("MobileDemand"); $fabricant->setCode("MobileDemand"); $manager->persist($fabricant);
        $fabricant = new Fabricant(); $fabricant->setLibelle("MobiWire"); $fabricant->setCode("MobiWire"); $manager->persist($fabricant);
        $fabricant = new Fabricant(); $fabricant->setLibelle("Motorola"); $fabricant->setCode("Motorola"); $manager->persist($fabricant);
        $fabricant = new Fabricant(); $fabricant->setLibelle("Motorola Solutions"); $fabricant->setCode("Motorola Solutions"); $manager->persist($fabricant);
        $fabricant = new Fabricant(); $fabricant->setLibelle("mPTech"); $fabricant->setCode("myPhone"); $manager->persist($fabricant);
        $fabricant = new Fabricant(); $fabricant->setLibelle("Multilaser"); $fabricant->setCode("Multilaser"); $manager->persist($fabricant);
        $fabricant = new Fabricant(); $fabricant->setLibelle("Nokia (owned by HMD Global)"); $fabricant->setCode("HMD Global"); $manager->persist($fabricant);
        $fabricant = new Fabricant(); $fabricant->setLibelle("OnePlus"); $fabricant->setCode("OnePlus"); $manager->persist($fabricant);
        $fabricant = new Fabricant(); $fabricant->setLibelle("Oppo"); $fabricant->setCode("OPPO"); $manager->persist($fabricant);
        $fabricant = new Fabricant(); $fabricant->setLibelle("Opticon"); $fabricant->setCode("Opticon"); $manager->persist($fabricant);
        $fabricant = new Fabricant(); $fabricant->setLibelle("Panasonic"); $fabricant->setCode("PANASONIC"); $manager->persist($fabricant);
        $fabricant = new Fabricant(); $fabricant->setLibelle("PointMobile"); $fabricant->setCode("POINTMOBILE"); $manager->persist($fabricant);
        $fabricant = new Fabricant(); $fabricant->setLibelle("Positivo"); $fabricant->setCode("Positivo"); $manager->persist($fabricant);
        $fabricant = new Fabricant(); $fabricant->setLibelle("Rhino Mobility"); $fabricant->setCode("RHINO"); $manager->persist($fabricant);
        $this->fabricantSamsung = new Fabricant(); $this->fabricantSamsung->setLibelle("Samsung"); $this->fabricantSamsung->setCode("Samsung"); $manager->persist($this->fabricantSamsung);
        $fabricant = new Fabricant(); $fabricant->setLibelle("Sharp"); $fabricant->setCode("SHARP"); $manager->persist($fabricant);
        $fabricant = new Fabricant(); $fabricant->setLibelle("Sonim Technologies"); $fabricant->setCode("Sonimtech"); $manager->persist($fabricant);
        $fabricant = new Fabricant(); $fabricant->setLibelle("Sony"); $fabricant->setCode("Sony"); $manager->persist($fabricant);
        $fabricant = new Fabricant(); $fabricant->setLibelle("Spectralink"); $fabricant->setCode("Spectralink"); $manager->persist($fabricant);
        $fabricant = new Fabricant(); $fabricant->setLibelle("TCL"); $fabricant->setCode("TCL"); $manager->persist($fabricant);
        $fabricant = new Fabricant(); $fabricant->setLibelle("Unitech"); $fabricant->setCode("Unitech_Electronics"); $manager->persist($fabricant);
        $fabricant = new Fabricant(); $fabricant->setLibelle("Urovo Technology"); $fabricant->setCode("Urovo"); $manager->persist($fabricant);
        $fabricant = new Fabricant(); $fabricant->setLibelle("Vsmart"); $fabricant->setCode("Vsmart"); $manager->persist($fabricant);
        $fabricant = new Fabricant(); $fabricant->setLibelle("Xiaomi"); $fabricant->setCode("Xiaomi"); $manager->persist($fabricant);
        $fabricant = new Fabricant(); $fabricant->setLibelle("Wiko"); $fabricant->setCode("WIKO"); $manager->persist($fabricant);
        $fabricant = new Fabricant(); $fabricant->setLibelle("Wishtel"); $fabricant->setCode("Wishtel"); $manager->persist($fabricant);
        $fabricant = new Fabricant(); $fabricant->setLibelle("Zebra"); $fabricant->setCode("Zebra Technologies"); $manager->persist($fabricant);
        $fabricant = new Fabricant(); $fabricant->setLibelle("ZTE"); $fabricant->setCode("ZTE"); $manager->persist($fabricant);
    }

    public static function getGroups(): array
    {
        return ['clients'];
    }
}

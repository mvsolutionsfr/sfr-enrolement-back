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
use App\Toolbox\StatutEnrolementEnum;
use App\Toolbox\StatutImeiEnum;
use App\Toolbox\TypeEnrolementEnum;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

// php bin/console --env=dev --purge-with-truncate doctrine:fixtures:load --group=apple
class AppleFixture extends Fixture implements FixtureGroupInterface
{
    private PgmEnrolement $pgmApple;
    private PgmEnrolement $pgmKnox;
    private PgmEnrolement $pgmZT;
    private ObjectManager $manager;

    private Fabricant $fabricantApple;
    private Fabricant $fabricantSamsung;
    private Fabricant $fabricantCrosscall;

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
        $enseigne->setReferent("Emilie VAPPEREAU");
        $enseigne->setCommentaire("Developpeur SFR");

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

        $utilModifierPar = new UtilisateurModifiePar();
        $utilModifierPar->setUtilModifie($utilisateur);
        $utilModifierPar->setUtilModifiePar($admin);
        $utilModifierPar->setDate($datetime);
        $manager->persist($utilModifierPar);

        $this->pgmKnox = new PgmEnrolement();
        $this->pgmKnox->setLibelle("Knox");
        $manager->persist($this->pgmKnox);

        $this->pgmApple = new PgmEnrolement();
        $this->pgmApple->setLibelle("Apple");
        $manager->persist($this->pgmApple);

        $this->pgmZT = new PgmEnrolement();
        $this->pgmZT->setLibelle("Zerotouch");
        $manager->persist($this->pgmZT);

        // CLIENTS ////////////////////////////////////////////////////////////////////

        $clientMV = $this->createClient("SFR Demo", "Eric RAVE", $enseigne, $utilisateur, $this->pgmKnox, "2455979979", "483443891");
        $clientTA = $this->createClient("TOURAINE ALARME", "Frédéric NEVEU", $enseigne,$utilisateur, $this->pgmKnox, "54646464");


        $this->setPgmToClient($clientMV,$this->pgmApple,"10000");
        $this->setPgmToClient($clientMV,$this->pgmKnox,"35863870");

        //////////////////////////////////////////////////////////////////////

        $enseigneModifiePar = new EnseigneEstModifiePar();
        $enseigneModifiePar->setEnseigne($enseigne);
        $enseigneModifiePar->setUtilisateur($admin);
        $enseigneModifiePar->setDate($datetime);
        $manager->persist($enseigneModifiePar);

        $resellerId = "7854573063";
        $pgmEnseigne = new PgmEnseigne();
        $pgmEnseigne->setPgmEnrolement($this->pgmKnox);
        $pgmEnseigne->setResellerId($resellerId);
        $pgmEnseigne->setEnseignes($enseigne);
        $manager->persist($pgmEnseigne);


        $resellerId = "0000657274";
        $pgmEnseigne = new PgmEnseigne();
        $pgmEnseigne->setPgmEnrolement($this->pgmApple);
        $pgmEnseigne->setResellerId($resellerId);
        $pgmEnseigne->setEnseignes($enseigne);
        $manager->persist($pgmEnseigne);

        $manager->persist($admin);
        $manager->persist($utilisateur);

        $this->chargeFabricant();
        $this->creeTerminaux(5, $this->fabricantApple, $this->pgmApple, $clientMV);

        $manager->flush();
    }

    public function creeTerminaux(int $nb, $fabricant, $pgm, $client): void
    {
        $datetime = date_create('now');

        for ($i = 1; $i <= $nb; $i++) {
            $terminal = new Terminal();
            $imei = rand(3600000000, 3700000000);
            $terminal->setFabricant($fabricant);
            $terminal->setNumeroIMEI($imei);
            $terminal->setStatut(1);
            $terminal->setProgramme($pgm);
            $this->manager->persist($terminal);

            $enrolement = new Enrolement();
            $enrolement->setTerminal($terminal);
            $enrolement->setClient($client);
            $enrolement->setDate($datetime);
            $this->manager->persist($enrolement);
        }
    }

    public function setPgmToClient($client, $pgm,$customerId): PgmClient
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

    public function createClient($rs, $referent, $enseigne, $utilisateur, $pgm = null, $customerId=null ): Client
    {
        $datetime = date_create('now');
        $client = new Client();
        $client->setRaisonSociale($rs);
        $client->setReferent($referent);
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
        return ['apple'];
    }
}

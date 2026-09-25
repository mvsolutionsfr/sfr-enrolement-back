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
use App\Entity\Parametres;
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
use function Symfony\Component\HttpFoundation\setContent;

// php bin/console --env=test --purge-with-truncate doctrine:fixtures:load --group=basetest
class BaseTestFixture extends Fixture implements FixtureGroupInterface
{
    private PgmEnrolement $pgmApple;
    private PgmEnrolement $pgmKnox;
    private $manager;

    public function __construct()
    {
    }

    public function load(ObjectManager $manager): void
    {
        $this->manager = $manager;
        $datetime = date_create('now');

        // Paramètres
        $parametre = new Parametres();
        $parametre->setName("doTasksDep");
        $parametre->setParamBool(true);
        $manager->persist($parametre);
        $parametre = new Parametres();
        $parametre->setName("doTasksKnox");
        $parametre->setParamBool(true);
        $manager->persist($parametre);
        $parametre = new Parametres();
        $parametre->setName("doTasksZT");
        $parametre->setParamBool(true);
        $manager->persist($parametre);
        $parametre->setName("maxRetry");
        $parametre->setParamInt(300);
        $manager->persist($parametre);


// Création des fabricants

        $fabricantSamsung = new Fabricant();
        $fabricantSamsung->setLibelle("Samsung");
        $manager->persist($fabricantSamsung);

        $fabricantApple = new Fabricant();
        $fabricantApple->setLibelle("Apple");
        $manager->persist($fabricantApple);

// Création de l'enseigne SFR

        $enseigne = new Enseigne();
        $enseigne->setRaisonSociale("SFR");
        $enseigne->setReferent("Emilie VAPPEREAU");
        $enseigne->setCommentaire("Developpeur SFR");

// Création de 2 utilisateurs

        $admin = new Utilisateur();
        $admin->setNom("RAVE");
        $admin->setPrenom("Eric");
        $admin->setAdmin(true);
        $admin->setIdAui("u163116");
        $admin->setEnseigne($enseigne);

        $utilisateur = new Utilisateur();
        $utilisateur->setNom("VAPPEREAU");
        $utilisateur->setPrenom("Nicolas");
        $utilisateur->setIdAui("u166978");
        $utilisateur->setAdmin(false);
        $utilisateur->setEnseigne($enseigne);

        $utilModifierPar = new UtilisateurModifiePar();
        $utilModifierPar->setUtilModifie($utilisateur);
        $utilModifierPar->setUtilModifiePar($admin);
        $utilModifierPar->setDate($datetime);
        $manager->persist($utilModifierPar);

// Création des programmes d'enrolement

        $this->pgmKnox = new PgmEnrolement();
        $this->pgmKnox->setLibelle("Knox");
        $manager->persist($this->pgmKnox);

        $this->pgmApple = new PgmEnrolement();
        $this->pgmApple->setLibelle("Apple");
        $manager->persist($this->pgmApple);

        $pgmZT = new PgmEnrolement();
        $pgmZT->setLibelle("Zerotouch");
        $manager->persist($pgmZT);

// Création des 2 Clients et affection des programmes
        $clientMV = $this->createClient("MV SOLUTIONS", "Eric RAVE", $enseigne,$utilisateur, $this->pgmKnox, "4044154996");
        $this->setPgmToClient($clientMV,$this->pgmApple,"XXXXXXX");

        $clientTA = $this->createClient("TOURAINE ALARME", "Frédéric NEVEU", $enseigne,$utilisateur, $this->pgmKnox, "54646464");


//

        $enseigneModifiePar = new EnseigneEstModifiePar();
        $enseigneModifiePar->setEnseigne($enseigne);
        $enseigneModifiePar->setUtilisateur($admin);
        $enseigneModifiePar->setDate($datetime);
        $manager->persist($enseigneModifiePar);

// Affection des programmes aux Enseignes avec resellerId

        $resellerId = "7854573063";
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

        $manager->persist($admin);
        $manager->persist($utilisateur);


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
            $terminal->setProgramme($this->pgmKnox);
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


    public static function getGroups(): array
    {
        return ['basetest'];
    }
}

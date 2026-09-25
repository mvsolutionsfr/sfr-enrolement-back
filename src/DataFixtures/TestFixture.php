<?php

namespace App\DataFixtures;

use App\Entity\Client;
use App\Entity\ClientEstModifiePar;
use App\Entity\Enrolement;
use App\Entity\Enseigne;
use App\Entity\EnseigneEstModifiePar;
use App\Entity\Fabricant;
use App\Entity\PgmClient;
use App\Entity\PgmEnrolement;
use App\Entity\PgmEnseigne;
use App\Entity\Terminal;
use App\Entity\Utilisateur;
use App\Entity\UtilisateurModifiePar;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

class TestFixture extends Fixture implements FixtureGroupInterface
{
    public function load(ObjectManager $manager): void
    {

        $datetime = date_create('now');

        $enseigne = new Enseigne();
        $enseigne->setRaisonSociale("SFR");
        $enseigne->setReferent("Emilie VAPPEREAU");
        $enseigne->setCommentaire("Developpeur SFR");

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

        $pgmKnox = new PgmEnrolement();
        $pgmKnox->setLibelle("Knox");
        $manager->persist($pgmKnox);

        $pgmApple = new PgmEnrolement();
        $pgmApple->setLibelle("Apple");
        $manager->persist($pgmApple);

        $pgmZT = new PgmEnrolement();
        $pgmZT->setLibelle("Zerotouch");
        $manager->persist($pgmZT);

        // CLIENT MV
        $clientMV = new Client();
        $clientMV->setRaisonSociale("MV SOLUTIONS");
        $clientMV->setReferent("Eric RAVE");
        $clientMV->setActif(true);
        $manager->persist($clientMV);
        $enseigne->addClient($clientMV);

        $clientEstModifiePar = new ClientEstModifiePar();
        $clientEstModifiePar->setClient($clientMV);
        $clientEstModifiePar->setUtilisateur($utilisateur);
        $clientEstModifiePar->setDate($datetime);
        $manager->persist($clientEstModifiePar);

        // Apple
        $customerId = "XXXXXXX";
        $pgmClient = new PgmClient();
        $pgmClient->setActif(true);
        $pgmClient->setClient($clientMV);
        $pgmClient->setPgmEnrolement($pgmApple);
        $pgmClient->setCustomerId($customerId);
        $pgmClient->setEmail("");
        $pgmClient->setGestionSfr(false);
        $manager->persist($pgmClient);

        // Knox
        $customerId = "4044154996";
        $pgmClient = new PgmClient();
        $pgmClient->setActif(true);
        $pgmClient->setClient($clientMV);
        $pgmClient->setPgmEnrolement($pgmKnox);
        $pgmClient->setCustomerId($customerId);
        $pgmClient->setEmail("");
        $pgmClient->setGestionSfr(false);
        $manager->persist($pgmClient);

        // CLIENT TA
        $clientTA = new Client();
        $clientTA->setRaisonSociale("TOURAINE ALARME");
        $clientTA->setReferent("Fred NEVEU");
        $clientTA->setActif(true);
        $manager->persist($clientTA);

        $enseigne->addClient($clientTA);
        $manager->persist($enseigne);

        $clientEstModifiePar = new ClientEstModifiePar();
        $clientEstModifiePar->setClient($clientTA);
        $clientEstModifiePar->setUtilisateur($utilisateur);
        $clientEstModifiePar->setDate($datetime);
        $manager->persist($clientEstModifiePar);

        // Knox
        $customerId = "454545454";
        $pgmClient = new PgmClient();
        $pgmClient->setActif(true);
        $pgmClient->setClient($clientTA);
        $pgmClient->setPgmEnrolement($pgmKnox);
        $pgmClient->setCustomerId($customerId);
        $pgmClient->setEmail("");
        $pgmClient->setGestionSfr(false);
        $manager->persist($pgmClient);

        $enseigneModifiePar = new EnseigneEstModifiePar();
        $enseigneModifiePar->setEnseigne($enseigne);
        $enseigneModifiePar->setUtilisateur($admin);
        $enseigneModifiePar->setDate($datetime);
        $manager->persist($enseigneModifiePar);

        $resellerId = "7854573063";
        $pgmEnseigne = new PgmEnseigne();
        $pgmEnseigne->setPgmEnrolement($pgmKnox);
        $pgmEnseigne->setResellerId($resellerId);
        $pgmEnseigne->setEnseignes($enseigne);
        $manager->persist($pgmEnseigne);

        $resellerId = "YYYYYYYYY";
        $pgmEnseigne = new PgmEnseigne();
        $pgmEnseigne->setPgmEnrolement($pgmApple);
        $pgmEnseigne->setResellerId($resellerId);
        $pgmEnseigne->setEnseignes($enseigne);
        $manager->persist($pgmEnseigne);

        $manager->persist($admin);
        $manager->persist($utilisateur);

        $fabricant = new Fabricant();
        $fabricant->setLibelle("Samsung");
        $manager->persist($fabricant);

        // On attache 2 IMEIS sur la date du jour au clients MV SOLUTIONS
        $terminal = new Terminal();
        $terminal->setFabricant($fabricant);
        $terminal->setNumeroIMEI("3532323232323");
        $terminal->setStatut(1);
        $terminal->setProgramme($pgmKnox);
        $manager->persist($terminal);

        $enrolement = new Enrolement();
        $enrolement->setTerminal($terminal);
        $enrolement->setClient($clientMV);
        $enrolement->setDate($datetime);
        $manager->persist($enrolement);

        $terminal = new Terminal();
        $terminal->setFabricant($fabricant);
        $terminal->setNumeroIMEI("888883232323");
        $terminal->setStatut(1);
        $terminal->setProgramme($pgmKnox);
        $manager->persist($terminal);

        $enrolement = new Enrolement();
        $enrolement->setTerminal($terminal);
        $enrolement->setClient($clientMV);
        $enrolement->setDate($datetime);
        $manager->persist($enrolement);


        $terminal = new Terminal();
        $terminal->setFabricant($fabricant);
        $terminal->setNumeroIMEI("6565653232323");
        $terminal->setStatut(1);
        $terminal->setProgramme($pgmApple);
        $manager->persist($terminal);

        $enrolement = new Enrolement();
        $enrolement->setTerminal($terminal);
        $enrolement->setClient($clientMV);
        $enrolement->setDate($datetime);
        $manager->persist($enrolement);

        // On attache 2 IMEIS sur la date du jour au clients TA
        $terminal = new Terminal();
        $terminal->setFabricant($fabricant);
        $terminal->setNumeroIMEI("111111111113232323");
        $terminal->setStatut(1);
        $terminal->setProgramme($pgmKnox);
        $manager->persist($terminal);

        $enrolement = new Enrolement();
        $enrolement->setTerminal($terminal);
        $enrolement->setClient($clientTA);
        $enrolement->setDate($datetime);
        $manager->persist($enrolement);

//        $terminal = new Terminal();
//        $terminal->setFabricant($fabricant);
//        $terminal->setNumeroIMEI("855555555553");
//        $terminal->setStatut(1);
//        $terminal->setProgramme($pgmKnox);
//        $manager->persist($terminal);
//
//        $enrolement = new Enrolement();
//        $enrolement->setTerminal($terminal);
//        $enrolement->setClient($clientTA);
//        $enrolement->setDate($datetime);
//        $manager->persist($enrolement);


        $terminal = new Terminal();
        $terminal->setFabricant($fabricant);
        $terminal->setNumeroIMEI("65656666666");
        $terminal->setStatut(1);
        $terminal->setProgramme($pgmApple);
        $manager->persist($terminal);

        $enrolement = new Enrolement();
        $enrolement->setTerminal($terminal);
        $enrolement->setClient($clientTA);
        $enrolement->setDate($datetime);
        $manager->persist($enrolement);

        $manager->flush();

        ///////////////////////////////////////////////////////////////////////////////////////
        // Chargement d'une commande
        ///////////////////////////////////////////////////////////////////////////////////////
//
//        // Création du commande dans Commande
//        $commande = new Orders();
//        $commande->setPgmEnrolement($pgmEnrolement);
//        $commande->setResellerId($resellerId);
//        $commande->setCustomerId($customerId);
//        $commande->setClient($client);
//        $commande->setEstCreePar($utilisateur);  //TODO : Gérer l'utilisateur courant
//        $commande->setDateCreation($datetime);
//        $commande->setDernierStatut(StatutEnrolementEnum::DemandeEnrolement->value);
//        $commande->setFabricant($fabricant);
//
//        $commandeTransaction = new OrderTransaction();
//        $commandeTransaction->setOrders($commande);
//        $commandeTransaction->setStatut(StatutEnrolementEnum::DemandeEnrolement->value);
//        $commandeTransaction->setDateCreation($datetime);
//        $commandeTransaction->setEstCreePar($utilisateur);  //TODO : Gérer l'utilisateur courant
//        $commandeTransaction->setTypeTransaction(TypeEnrolementEnum::Enrolement->value);
//        $commandeTransaction->setStatutMsgPgm("");
//        $commandeTransaction->setStatutTransacPgm("");
//        $manager->persist($commandeTransaction);
//
//
//        $imeis = array("356934894258722", "866394040369605", "861669047906003");
//        foreach ($imeis as $imei) {
//            $terminalEntity = new Terminal();
//            $terminalEntity->setFabricant($fabricant);
//            $terminalEntity->setNumeroIMEI($imei);
//            $terminalEntity->setStatut(StatutImeiEnum::EnCoursEnrolement->value);
//
//            $terminalSuivi = new TerminalSuivi();
//            $terminalSuivi->setTerminal($terminalEntity);
//            $terminalSuivi->setTransaction($commandeTransaction);
//            $terminalSuivi->setStatut(StatutImeiEnum::EnCoursEnrolement->name);
//
//            $manager->persist($terminalSuivi);
//            $manager->persist($terminalEntity);
//        }
//        $manager->persist($commande);
//
//        $manager->flush();

        // Lancer la commande puis vider et recharger la base de test : php bin/console --env=test --purge-with-truncate doctrine:fixtures:load
    }

    public static function getGroups(): array
    {
        return ['test'];
    }
}
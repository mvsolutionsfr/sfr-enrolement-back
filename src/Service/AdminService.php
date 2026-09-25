<?php

namespace App\Service;

use App\Entity\Enseigne;
use App\Entity\Client;
use App\Entity\ClientEstModifiePar;
use App\Entity\Orders;
use App\Entity\EnseigneEstModifiePar;
use App\Entity\Parametres;
use App\Entity\PgmEnrolement;
use App\Entity\PgmEnseigne;
use App\Entity\Utilisateur;
use App\Entity\UtilisateurModifiePar;
use App\Toolbox\LogLevelEnum;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Exception;
use Psr\Log\LoggerInterface;


class AdminService extends AbstractService
{

    private AUIService $auiService;

    public function __construct(ManagerRegistry $doctrine, LoggerESService $logger, SessionService $session, AUIService $auiService)
    {
        parent::__construct($doctrine, $logger, $session);
        $this->auiService = $auiService;
    }

    public function createEnseigne(string $raisonSociale, Utilisateur $userModif, array $utilisateurs): Enseigne
    {
        $enseigne = $this->doctrine->getRepository(Enseigne::class)->findBy(['raisonSociale' => $raisonSociale]);
        if ($enseigne) throw new \Exception("Enseigne déjà existante");

        $enseigne = new Enseigne();
        $this->commitEnseigne($enseigne, $raisonSociale, $userModif);
        $this->setUtilisateursToEnseigne($enseigne, $utilisateurs);
        $this->doctrine->getManager()->flush();

        return $enseigne;
    }

    public function rechercheEnseignes(string $critere, PgmEnrolement $pgm): array
    {
        $retour = array();
        $enseignes = $this->doctrine->getRepository(Enseigne::class)->findByNameByRS($critere);

        if (count($enseignes) == 0) {
            $pgmEnseignes = $this->doctrine->getRepository(PgmEnseigne::class)->findByPgmByResellerId($critere, $pgm);
            /** @var PgmEnseigne $pgmEnseigne */
            foreach ($pgmEnseignes as $pgmEnseigne) {
                $enseignes[] = $pgmEnseigne->getEnseignes();
            }
        }

        foreach ($enseignes as $enseigne) {
            $retour[] = $this->infosEnseigne($enseigne);
        }
        return $retour;
    }

    public function listEnseignes(): array
    {
        $retour = array();
        $enseignes = $this->doctrine->getRepository(Enseigne::class)->orderByName();

        /** @var Enseigne $enseigne */
        foreach ($enseignes as $enseigne) {
            $retour[] = ["id" => $enseigne->getId(), "rs" => $enseigne->getRaisonSociale()];
        }
        return $retour;
    }

    public function saveEnseigne(string $raisonSociale, Utilisateur $userModif, string $enseigneId, array $utilisateurs): Enseigne
    {
        $enseigne = $this->doctrine->getRepository(Enseigne::class)->find($enseigneId);
        if (!$enseigne) throw new \Exception("Enseigne non existante");

        $this->commitEnseigne($enseigne, $raisonSociale, $userModif);
        $this->setUtilisateursToEnseigne($enseigne, $utilisateurs);
        $this->doctrine->getManager()->flush();
        return $enseigne;
    }

    public function getEnseigne(string $enseigneId): array
    {
        /** @var Enseigne $enseigne */
        $enseigne = $this->doctrine->getRepository(Enseigne::class)->find($enseigneId);
        if (!$enseigne) throw new \Exception("Enseigne non existante");
        return $this->infosEnseigne($enseigne);
    }

    private function infosEnseigne(Enseigne $enseigne): array
    {
        $retour = ["rs" => $enseigne->getRaisonSociale(), "id" => $enseigne->getId()];
        $pgmEnrolement = $this->sessionService->getProgramme();   // Pgm d'enrolement en cours
        $utilisateur = $this->sessionService->getUtilisateur();   // Pgm d'enrolement en cours

        $pgmEnseigne = null;
        $pgmEnseignes = $this->doctrine->getRepository(PgmEnseigne::class)->findBy(['pgmEnrolement' => $pgmEnrolement, 'enseignes' => $enseigne]);
        if (count($pgmEnseignes) > 0) {
            /** @var PgmEnseigne $pgmEnseigne */
            $pgmEnseigne = $pgmEnseignes[0];
            $retour["resellerid"] = $pgmEnseigne->getResellerId();
        }

        $utilisateurs = $this->doctrine->getRepository(Utilisateur::class)->findBy(['enseigne' => $enseigne, 'actif' => true]);
        $uArray = array();
        /** @var Utilisateur $utilisateur */
        foreach ($utilisateurs as $utilisateur) {
            $uArray[] = ["uid" => $utilisateur->getIdAui(), "nom" => $utilisateur->getNom(), "prenom" => $utilisateur->getPrenom(), "id" => $utilisateur->getId()];
        }
        $retour["utilisateurs"] = $uArray;

        return $retour;
    }

    private function setUtilisateursToEnseigne(Enseigne $enseigne, array $utilisateurs)
    {
        $utilisateursEnseigne = $this->doctrine->getRepository(Utilisateur::class)->findBy(['enseigne' => $enseigne]);
        $utilisateursToDelete = array();
        $utilisateursToAdd = array();
        $manager = $this->doctrine->getManager();
        $datetime = date_create('now');

        /** @var string $auiId */
        foreach ($utilisateurs as $auiId) {
            $trouve = false;
            /** @var Utilisateur $uE */
            foreach ($utilisateursEnseigne as $uE) {
                if ($uE->getIdAui() == $auiId) {
                    if (!$uE->isActif()) {
                        $uE->setActif(true);
                        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "Passage actif de l'utilisateur" . $auiId);
                        $manager->persist($uE);
                    }
                    $trouve = true;
                    break;
                }
            }
            if (!$trouve) $utilisateursToAdd[] = $auiId;
        }

        foreach ($utilisateursEnseigne as $uE) {
            $uid = $uE->getIdAui();
            $trouve = false;
            if (!$uE->isActif()) continue;
            /** @var Utilisateur $uE */
            foreach ($utilisateurs as $auiId) {
                if ($uid == $auiId) {
                    $trouve = true;
                    break;
                }
            }
            if (!$trouve) {
                $uE->setActif(false);
                $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "Passage etat inactif de l'utilisateur" . $uid);
                $manager->persist($uE);
                $utilisateursToDelete[] = $uid;
            }
        }

        $this->usersToAddToEnseigne($utilisateursToAdd, $enseigne);
    }

    public function usersToAddToEnseigne($utilisateursToAdd, $enseigne, $elt = null)
    {
        $utilisateurCourant = $this->sessionService->getUtilisateur();
        if ($utilisateurCourant == null) $utilisateurCourant = $this->doctrine->getRepository(Utilisateur::class)->find(1);

        $manager = $this->doctrine->getManager();
        $compteur = 0;
        foreach ($utilisateursToAdd as $userToAdd) {
            /** @var Utilisateur $userExistant */
            $userExistant = $this->doctrine->getRepository(Utilisateur::class)->findBy(['idAui' => $userToAdd]);
            if ($userExistant && count($userExistant) > 0) {
                $userExistant = $userExistant[0];
                if ($userExistant->isActif()) {
                    $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "Utilisateur " . $userToAdd . " existant sur une autre agence");
                    throw new \Exception("Utilisateur " . $userToAdd . " existant sur une autre agence");
                } else {
                    $userExistant->setEnseigne($enseigne);
                    $userExistant->setActif(true);
                    $manager->persist($userExistant);
                }
            } else {
                if ($elt == null || count($elt[$compteur])==1) {
                    $this->logger->writeLog(LogLevelEnum::Debug, __METHOD__ . "[" . __LINE__ . "]", "Recherche AUI " . $userToAdd);
                    $info = $this->auiService->getUserData($userToAdd);
                    if ($info != null)
                    {
                        $nom=$info["nom"];
                        $prenom=$info["prenom"];
                    } else
                    {
                        $this->logger->writeLog(LogLevelEnum::Debug, __METHOD__ . "[" . __LINE__ . "]", "Recherche Centric " . $userToAdd);
                        $info = $this->auiService->getUserDataFromCentric($userToAdd);
                        if ($info != null)
                        {
                            $nom=$info["nom"];
                            $prenom=$info["prenom"];
                        } else {
                            throw new \Exception("Utilisateur " . $userToAdd . " inconnu de l'AUI et d'Arcadye");
                        }
                    }
                } else {
                    $nom = $elt[$compteur][2];
                    $prenom = $elt[$compteur][1];
                }
                $utilisateur = new Utilisateur();
                $utilisateur->setNom($nom);
                $utilisateur->setPrenom($prenom);
                $utilisateur->setIdAui($userToAdd);
                $utilisateur->setAdmin(false);
                $utilisateur->setEnseigne($enseigne);
                $utilisateur->setActif(true);
                $manager->persist($utilisateur);

                $utilModifierPar = new UtilisateurModifiePar();
                $utilModifierPar->setUtilModifie($utilisateur);
                $utilModifierPar->setUtilModifiePar($utilisateurCourant);
                $utilModifierPar->setDate(date_create('now'));
                $manager->persist($utilModifierPar);

                $compteur++;
            }
            $manager->flush();

        }
    }

    private function commitEnseigne(Enseigne $enseigne, string $raisonSociale, Utilisateur $userModif)
    {
        $datetime = date_create('now');
        $entityManager = $this->doctrine->getManager();
        $enseigne->setRaisonSociale($raisonSociale);
        $enseigneModifiePar = new EnseigneEstModifiePar();
        $enseigneModifiePar->setEnseigne($enseigne);
        $enseigneModifiePar->setUtilisateur($userModif);
        $enseigneModifiePar->setDate(date_create('now'));
        $entityManager->persist($enseigneModifiePar);

        // tell Doctrine you want to (eventually) save the Product (no queries yet)
        $entityManager->persist($enseigne);
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "Modification de l'enseigne " . $raisonSociale);
    }


    public function setResellerId(Enseigne $enseigne, PgmEnrolement $pgm, string $resellerId): PgmEnseigne
    {
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "Affection du resellerId  " . $resellerId . " à  " . $enseigne->getRaisonSociale() . " sur le programme " . $pgm->getLibelle());
        $entityManager = $this->doctrine->getManager();

        /** @var array $pgmEnseignes */
        $pgmEnseigne = null;
        $pgmEnseignes = $this->doctrine->getRepository(PgmEnseigne::class)->findBy(['pgmEnrolement' => $pgm, 'enseignes' => $enseigne]);
        if (count($pgmEnseignes) > 0) $pgmEnseigne = $pgmEnseignes[0];

        $pgmEnseigneExistante = null;
        $pgmEnseigneExistantes = $this->doctrine->getRepository(PgmEnseigne::class)->findBy(['pgmEnrolement' => $pgm, 'resellerId' => $resellerId]);
        if (count($pgmEnseigneExistantes) > 0) $pgmEnseigneExistante = $pgmEnseigneExistantes[0];

        if ($pgmEnseigne == null) {
            //if ($pgmEnseigneExistante != null && $pgm->getLibelle() != "Zerotouch") throw new \Exception("resellerId déjà attribué au revendeur " . $pgmEnseigneExistante->getEnseignes()->getRaisonSociale());
            $pgmEnseigne = new PgmEnseigne();
            $pgmEnseigne->setPgmEnrolement($pgm);
            $pgmEnseigne->setEnseignes($enseigne);
        } else {
            if ($pgmEnseigneExistante != null && $pgmEnseigne->getEnseignes()->getId() != $pgmEnseigneExistante->getEnseignes()->getId() && $pgm->getLibelle() != "Zerotouch")
                throw new \Exception("resellerId déjà attribué au revendeur " . $pgmEnseigneExistante->getEnseignes()->getRaisonSociale());
        }
        $pgmEnseigne->setResellerId($resellerId);
        $entityManager->persist($pgmEnseigne);
        $entityManager->flush();
        return $pgmEnseigne;
    }

    public function saveParametres(int $maxRetry, bool $doTasksDep, bool $doTasksKnox, bool $doTasksZT, Utilisateur $utilisateur)
    {
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "Affection de MaxRetry à  " . $maxRetry . " pour l'utilisateur " . $utilisateur->getId());
        $entityManager = $this->doctrine->getManager();
        /**
         * @var Parametres $maxRetryEntity
         */
        $maxRetryEntity = $this->doctrine->getRepository(Parametres::class)->findOneBy(['name' => 'maxRetry']);
        $maxRetryEntity->setParamInt((int)trim($maxRetry));
        $entityManager->persist($maxRetryEntity);   

        $maxTasksDepEntity = $this->doctrine->getRepository(Parametres::class)->findOneBy(['name' => 'doTasksDep']);
        $maxTasksDepEntity->setParamBool($doTasksDep);
        $entityManager->persist($maxTasksDepEntity);   

        $maxTasksKnoxEntity = $this->doctrine->getRepository(Parametres::class)->findOneBy(['name' => 'doTasksKnox']);
        $maxTasksKnoxEntity->setParamBool($doTasksKnox);
        $entityManager->persist($maxTasksKnoxEntity);   

        $maxTasksZTEntity = $this->doctrine->getRepository(Parametres::class)->findOneBy(['name' => 'doTasksZT']);
        $maxTasksZTEntity->setParamBool($doTasksZT);
        $entityManager->persist($maxTasksZTEntity);   


        $entityManager->flush();
    }

        public function getParametres(): array
    {
        $parametres = [];
        $parametres['maxRetry'] = $this->doctrine->getRepository(Parametres::class)->findOneBy(['name' => 'maxRetry'])->getParamInt();
        $parametres['doTasksDep'] = $this->doctrine->getRepository(Parametres::class)->findOneBy(['name' => 'doTasksDep'])->isParamBool();
        $parametres['doTasksKnox'] = $this->doctrine->getRepository(Parametres::class)->findOneBy(['name' => 'doTasksKnox'])->isParamBool();
        $parametres['doTasksZT'] = $this->doctrine->getRepository(Parametres::class)->findOneBy(['name' => 'doTasksZT'])->isParamBool();
        return $parametres;
    }

    public function getUtilisateur(string $utilisateurId): Utilisateur
    {
        $utilisateur = $this->doctrine->getRepository(Utilisateur::class)->find($utilisateurId);
        if (!$utilisateur) throw new \Exception("Utilisateur non existant");
        return $utilisateur;
    }

    public function getUtilisateurActivites(Utilisateur $utilisateur): array
    {

        $activites = [];

        $commandes = $this->doctrine->getRepository(Orders::class)->getLastOrdersByUtilisateur($utilisateur);
        foreach ($commandes as $commande) {
            $activites[] = ["date" => $commande->getDateCreation()->format('Y-m-d H:i:s'), "action" => "commande " . $commande->getId()];
        }

        $clients = $this->doctrine->getRepository(ClientEstModifiePar::class)->findLastByUtilisateur($utilisateur);
        foreach ($clients as $client) {
            $activites[] = ["date" => $client->getDate()->format('Y-m-d H:i:s'), "action" => "client " . $client->getClient()->getId()];
        }

        return $activites;
    }
}
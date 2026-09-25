<?php

namespace App\Service;

use Doctrine\ORM\Tools\Pagination\Paginator;
use App\Entity\Client;
use App\Entity\Enseigne;
use App\Entity\ClientEstModifiePar;
use App\Entity\Orders;
use App\Entity\OrderTransaction;
use App\Entity\PgmClient;
use App\Entity\PgmEnrolement;
use App\Entity\PgmEnseigne;
use App\Entity\Utilisateur;
use App\Repository\ClientRepository;
use App\Repository\PgmClientRepository;
use App\Toolbox\LogLevelEnum;
use App\Toolbox\SourceEnum;
use App\Toolbox\StatutEnrolementEnum;
use App\Toolbox\StatutImeiEnum;
use App\Toolbox\TypeEnrolementEnum;
use Doctrine\Common\Collections\Collection;
use Doctrine\Persistence\ManagerRegistry;
use Exception;
use Psr\Log\LoggerInterface;

//use Symfony\Contracts\HttpClient\HttpClientInterface;

class ClientService extends AbstractService
{
    private ZTRestService $ztRestService;

    public function __construct(ManagerRegistry $doctrine, LoggerESService $logger, SessionService $sessionService, ZTRestService $ztService)
    {
        parent::__construct($doctrine, $logger, $sessionService);
        $this->ztRestService = $ztService;
    }

    public function saveClient(CLient $client, string $raisonSociale, Utilisateur $userModif, Enseigne $enseigne, string $siren, bool $actif, ?array $customerIds): Client
    {
        $entityManager = $this->doctrine->getManager();
        $datetime = date_create('now');

        $programme = $this->sessionService->getProgramme();

        $clientEstModifiePar = new ClientEstModifiePar();
        $clientEstModifiePar->setDate($datetime);
        $clientEstModifiePar->setUtilisateur($userModif);
        $entityManager->persist($clientEstModifiePar);

        $client->setRaisonSociale($raisonSociale);
        $client->addClientEstModifiePar($clientEstModifiePar);
        $client->setEnseigne($enseigne);
        $client->setRaisonSociale($raisonSociale);
        $client->setSiren($siren);
        $client->setActif($actif);
        $entityManager->persist($client);

        $currentPgmClients = $this->getPgmClient($client, $programme);
        // Gest des customerId
        //$customerIds[] = array(['customerId' => $pgmClient->getCustomerId(), "actif" => $pgmClient->isActif(), "email" => $pgmClient->getEmail(), "gestionSFR" => $pgmClient->isGestionSfr()]);
        foreach ($customerIds as $customerIdTab) {
            $customerId = trim($customerIdTab["customerId"] ?? "");
            $email = $customerIdTab["email"] ?? "";
            $actif = boolval($customerIdTab["actif"] ?? false);

            // Un meme CustomerId peut être affecté à plusieurs client Tache ENROL-231
//            // On regarde si le customerId est déj) affecté à un client
//            $clientExistant = $this->getClientByCustomerId($customerId, $programme);
//            if ($clientExistant) {
//                if ($clientExistant->getId() != $client->getId()) {
//                    throw new \Exception("Le customerId " . $customerId . " est déjà rattaché au client " . $clientExistant->getRaisonSociale());
//                }
//            }

            $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "Ajout du customerId " . $customerId . " pour le client " . $client->getRaisonSociale());
            $this->setProgrammeEnrolement($client, $programme, $customerId, $actif, $email);
        }

        // On parcourt les customerIds existant pour supprimer ceux qui n'existent plus
        foreach ($currentPgmClients as $pgmClient) {
            $trouve = false;
            $customerId = $pgmClient->getCustomerId();
            foreach ($customerIds as $customerIdTab2) {
                if ($customerId == $customerIdTab2["customerId"] ?? "") {
                    $this->logger->writeLog(LogLevelEnum::Debug, __METHOD__ . "[" . __LINE__ . "]", "CustomerId " . $customerId . " trouvé pour le client " . $client->getRaisonSociale());
                    $trouve = true;
                    break;
                }
            }
            if (!$trouve) {
                $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "Suppresion du customerId " . $customerId . " pour le client " . $client->getRaisonSociale());
                $this->doctrine->getManager()->remove($pgmClient);
            }

        }

        // actually executes the queries (i.e. the INSERT query)
        $entityManager->flush();

        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "Modification client " . $raisonSociale);
        return $client;
    }

    public function createClient(string $raisonSociale, string $referent, string $commentaire, Utilisateur $userModif, Enseigne $enseigne, string $siren): Client
    {
        $entityManager = $this->doctrine->getManager();
        $datetime = date_create('now');

        $clientEstModifiePar = new ClientEstModifiePar();
        $clientEstModifiePar->setDate($datetime);
        $clientEstModifiePar->setUtilisateur($userModif);
        $entityManager->persist($clientEstModifiePar);

        $client = new Client();
        $client->setRaisonSociale($raisonSociale);
        $client->addClientEstModifiePar($clientEstModifiePar);
        $client->setEnseigne($enseigne);
        $client->setReferent($referent);
        $client->setActif(true);
        $client->setSiren($siren);
        $entityManager->persist($client);

        // actually executes the queries (i.e. the INSERT query)
        $entityManager->flush();

        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "Création client " . $raisonSociale);
        return $client;
    }

    public function getClientByName(string $raisonSociale): array
    {
        $this->logger->writeLog(LogLevelEnum::Debug, __METHOD__ . "[" . __LINE__ . "]", "Recherche client " . $raisonSociale);
        /** @var ClientRepository $repository */
        $repository = $this->doctrine->getRepository(Client::class);
        return $repository->findByName($raisonSociale);
    }

    public function getClientByNameByCustomerIdBySiren(string $critere, PgmEnrolement $pgmEnrolement, Enseigne $enseigne): array
    {
        $resultats = array();
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "Recherche client [" . $critere . "] pgm [" . $pgmEnrolement->getLibelle() . "] enseigne [" . $enseigne->getRaisonSociale() . "]");
        /** @var ClientRepository $repository */
        $repository = $this->doctrine->getRepository(Client::class);
        if ($critere == "")
            $resultats = $repository->findBy(['enseigne' => $enseigne]);
        else {
            $clients = $repository->findByNameByEnseigne($critere, $enseigne);
            $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "Resultats recherche : " . count($clients));
            if (!$clients || count($clients) == 0) {
                $clients = $repository->findBy(["siren" => $critere]);
                if (!$clients || count($clients) == 0) {
                    $repository = $this->doctrine->getRepository(PgmClient::class);
                    $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "Recherche par customerId " . $critere . " pgm [" . $pgmEnrolement->getId() . "]");
                    $pgmClients = $repository->findBy(["customerId" => $critere, 'pgmEnrolement' => $pgmEnrolement]);
                    /** @var PgmClient $pgmClient */
                    foreach ($pgmClients as $pgmClient) {
                        $client = $pgmClient->getClient();
                        if ($client->getEnseigne()->getId() == $enseigne->getId()) $resultats[] = $client;
                    }
                } else {
                    $resultats = $clients;
                }
            } else {
                $resultats = $clients;
            }
        }
        return $resultats;
    }

//    //TODO: retourner un tableau plutot qu'un client
//    public function getClientByCustomerId(string $critere, PgmEnrolement $pgmEnrolement): ?Client
//    {
//        $client = null;
//        $repository = $this->doctrine->getRepository(PgmClient::class);
//        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "Recherche par customerId " . $critere . " pgm [" . $pgmEnrolement->getId() . "]");
//        $pgmClients = $repository->findBy(["customerId" => $critere, 'pgmEnrolement' => $pgmEnrolement]);
//        if ($pgmClients && count($pgmClients) > 0) $client = $pgmClients[0]->getClient();
//        return $client;
//    }


    public function getClientBySiren(string $siren, Enseigne $enseigne): ?Client
    {
        $client = null;
        $repository = $this->doctrine->getRepository(Client::class);
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "Recherche par siren " . $siren);
        $clients = $repository->findBy(["siren" => $siren, "enseigne" => $enseigne]);
        if ($clients && count($clients) > 0) {
            if (count($clients) > 1) $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", count($clients) . " clients pour le siren " . $siren);
            $client = $clients[0];
        }
        return $client;
    }

    public function getClientById(string $id): ?Client
    {
        $this->logger->writeLog(LogLevelEnum::Debug, __METHOD__ . "[" . __LINE__ . "]", "Recherche client id " . $id);
        $repository = $this->doctrine->getRepository(Client::class);
        return $repository->find($id);
    }

    public function rosetProgrammeEnrolement(Client $client, PgmEnrolement $pgmEnrolement, string $customerId, bool $actif, string $email)
    {
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "Affection d'un programme d'enrolement pour [" . $pgmEnrolement->getLibelle() . "], client [" . $client->getRaisonSociale() . "], customerId [" . $customerId . "], email [" . $email . "]");
        $entityManager = $this->doctrine->getManager();
        $datetime = date_create('now');
        $message = "";
        $pgmClient = $this->doctrine->getRepository(PgmClient::class)->findOneBy(['client' => $client, 'customerId' => $customerId, 'pgmEnrolement' => $pgmEnrolement]);

        $isZeroTouch = $pgmEnrolement->getLibelle() == "Zerotouch";
        if ($isZeroTouch && $email === "") {
            throw new Exception("L'email est obligatoire pour zerotouch");
        }

        if ($pgmClient != null) {
            $pgmClient->setActif($actif);
        } else {
            $pgmClient = new PgmClient();
            $pgmClient->setClient($client);
            $pgmClient->setPgmEnrolement($pgmEnrolement);
            $pgmClient->setCustomerId(trim($customerId));
            $pgmClient->setActif($actif);
            $pgmClient->setEmail($email);
        }

        if ($isZeroTouch && $customerId === "") {
            $customerId = $this->createCustomerIdForZT($pgmClient, $pgmEnrolement, $client);
            $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "Creation du customerId [" . $customerId . "] pour ZT");
            if ($customerId === "") throw new Exception("Erreur lors de la création du customerId pour Zerotouch.");
            $pgmClient->setCustomerId(trim($customerId));
            $pgmClient->setGestionSFR(true);
        }

        $clientEstModifiePar = new ClientEstModifiePar();
        $clientEstModifiePar->setDate($datetime);
        $clientEstModifiePar->setUtilisateur($this->sessionService->getUtilisateur());
        $clientEstModifiePar->setClient($client);

        $entityManager->persist($pgmClient);
        $entityManager->persist($clientEstModifiePar);
        $entityManager->flush();
        $this->logger->writeLog(LogLevelEnum::Debug, __METHOD__ . "[" . __LINE__ . "]", "Affection pgm sur " . $client->getRaisonSociale() . " customerId [" . $customerId . "] pgm [" . $pgmEnrolement->getLibelle() . "]");
    }

    public function getClientByCustomerId(string $customerId): array
    {
        $clients = array();
        $clientsByCustomerId = $this->doctrine->getRepository(PgmClient::class)->findBy(["customerId" => $customerId]);
        /** @var PgmClient $clientByCustId */
        foreach ($clientsByCustomerId as $clientByCustId) {
            $clients[] = $clientByCustId->getClient();
        }
        return $clients;
    }

    public function getCustomerIds(Client $client, PgmEnrolement $pgm): ?array
    {
        $pgmClients = $this->getPgmClient($client, $pgm);
        $customerIds = array();
        /** @var PgmClient $pgmClient */
        foreach ($pgmClients as $pgmClient) {
            $customerIds[] = array('customerId' => $pgmClient->getCustomerId(), "actif" => $pgmClient->isActif(), "email" => $pgmClient->getEmail(), "gestionSFR" => $pgmClient->isGestionSfr());
        }
        return $customerIds;
    }

    public function getPgmClient(Client $client, PgmEnrolement $pgm): ?array
    {
        $pgmClient = null;
        $pgmClients = $this->doctrine->getRepository(PgmClient::class)->findByClientByPgm($client, $pgm);
        return $pgmClients;
    }

    public function getLastOrdersTransaction(Client $client, ?PgmEnrolement $pgm): array
    {
        /** @var array $res */
        $res = $this->doctrine->getRepository(OrderTransaction::class)->findLastTransactionsByClient($client, $pgm);
        $nbt = count($res);
        for ($i = 0; $i < $nbt; $i++) {
            $res[$i]["statut"] = StatutEnrolementEnum::from($res[$i]["statut"])->label();
            $res[$i]["typeTransaction"] = TypeEnrolementEnum::from($res[$i]["typeTransaction"])->name;
        }
        return $res;
    }

    public function getLastOrdersTransactionPaginator(Client $client, $page,$maxResult): Paginator
    {
        return $this->doctrine->getRepository(OrderTransaction::class)->findLastTransactionsByClientPaginator($client, $page, $maxResult);
    }

    public function createCustomerIdForZT(PgmClient $pgmClient, PgmEnrolement $pgmEnrolement, Client $client): string
    {

        $enseigne = $this->sessionService->getEnseigne();

        /** @var PgmEnseigne $pgmEnseigne */
        $pgmEnseigne = $this->doctrine->getRepository(PgmEnseigne::class)->findOneBy(['enseignes' => $enseigne, 'pgmEnrolement' => $pgmEnrolement]);
        if ($pgmEnseigne == null) {
            $message = "Enseigne " . $enseigne->getRaisonSociale() . " non attaché au programme " . $pgmEnrolement->getLibelle();
            $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", $message, SourceEnum::BackService);
            return $message;
        }

        return $this->ztRestService->createCustomerId($pgmEnseigne->getResellerId(), $client->getRaisonSociale(), $pgmClient->getEmail());
    }
}
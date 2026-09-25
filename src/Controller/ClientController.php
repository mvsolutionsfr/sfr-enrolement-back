<?php

namespace App\Controller;

use App\Entity\Client;
use App\Entity\Orders;
use App\Entity\PgmClient;
use App\Entity\PgmEnrolement;
use App\Service\AUIService;
use App\Service\ClientService;
use App\Service\LoggerESService;
use App\Service\SessionService;
use App\Toolbox\CodeErreurEnum;
use App\Toolbox\LogLevelEnum;
use App\Toolbox\SourceEnum;
//use ContainerMlFRhAV\getApiPlatform_ErrorListenerService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Attribute\Route;

class ClientController extends AngularController
{

    private int $critereMin = 3;

    public function __construct(LoggerESService $logger, RequestStack $requestStack, SessionService $sessionService)
    {
        parent::__construct($logger, $requestStack, $sessionService);
        $this->logger = $logger;
        $this->requestStack = $requestStack;
    }

    // WS permettant de lister les clients
    // Champs en entréé
    // - critere : cirtère de recherche (raison sociale ou customerId)
    // Champs en retour
    // tableaux de clients
    // - id : Identifiant client
    // - rs : Raison sociale
    // - customerId : Customer id (en fonction du programme)
    // - actif : true / false
    // Il sera possible d'effectuer une recherche à l'aide d'une barre de recherche. Les critères de recherche seront la raison sociale et le customer id.

    #[Route('/back/client/recherche', name: 'app_client_recherche')]
    public function recherche(Request $request, AUIService $auiService, ClientService $clientService): JsonResponse
    {
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", $request->getContent(), SourceEnum::ClientJson);

        $this->setSession($request);
        $pgmEnrolement = $this->sessionService->getProgramme();   // Pgm d'enrolement en cours

        $enseigne = $this->sessionService->getEnseigne();
        $data = json_decode($request->getContent(), true);
        $error = CodeErreurEnum::ok;
        $message = "";
        $resultats = array();
        $clients = null;

        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "[CLIENT][RECHERCHE][JSON]".$request->getContent(), SourceEnum::ClientJson);

        if (isset($data['critere'])) {
            $critere = $data['critere'];
            $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "recherche [" . $critere . "]", SourceEnum::BackController);
            if ($critere == "" || strlen($critere) >= $this->critereMin)
                $clients = $clientService->getClientByNameByCustomerIdBySiren($critere, $pgmEnrolement, $enseigne);
            else {
                $error = CodeErreurEnum::nok;
                $message = "La recherche doit se faire sur " . $this->critereMin . " caractères minimum";
            }
        } else {
            $error = CodeErreurEnum::nok;
            $message = "Paramètres d'appel incorrectes";
        }

        if ($clients) {
            /** @var Client $client */
            foreach ($clients as $client) {
                // IMEI
                $customerIds = $clientService->getCustomerIds($client, $pgmEnrolement);
                $rs = $client->getRaisonSociale();
                $clientId = $client->getId();
                $actif = $client->isActif();
                $siren = $client->getSiren();
                $resultats[] = array("customerId" => $customerIds, "rs" => $rs, "siren" => $siren, "id" => $clientId, "actif" => $actif);
            }
        }

        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "resultats recherche clients trouvé  [" . count($resultats) . "]", SourceEnum::BackController);
        if ($message != "") $this->logger->writeLog(LogLevelEnum::Erreur, __METHOD__ . "[" . __LINE__ . "]", $message, SourceEnum::BackController);

        return $this->json([
            'error' => $error,
            'message' => $message,
            'clients' => $resultats,
        ]);
    }

    // WS permettant de lister les clients
    // Champs en entréé
    // rs : raison sociale
    // referent : nom du référent dans la societe (optionnel);
    // commentaire : observations (optionnel)
    // email : email de contact (optionnel pour Knox et Apple)
    // Champs en retour:
    // - clientid : Identifiant client

    #[Route('/back/client/create', name: 'app_client_create')]
    public function createClient(Request $request, AUIService $auiService, ClientService $clientService): JsonResponse
    {

        $error = CodeErreurEnum::nok;
        $message = "";
        $clientId = "";
        $customerId = "";
        $rs = "";
        $siren = "";

        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", $request->getContent(), SourceEnum::ClientJson);

        try {
            $this->setSession($request);
            $pgmEnrolement = $this->sessionService->getProgramme();   // Pgm d'enrolement en cours
            $utilisateur = $this->sessionService->getUtilisateur();   // Pgm d'enrolement en cours
            $enseigne = $this->sessionService->getEnseigne();   // Pgm d'enrolement en cours

            $data = json_decode($request->getContent(), true);
            $isZeroTouch = $pgmEnrolement->getLibelle() == "Zerotouch";

            if (isset($data['rs'])) $rs = $data['rs']; else $message = "Raison sociale obligatoire";
            if (isset($data['customerid']))
                $customerId = $data['customerid'];
            else {
                if (!$isZeroTouch) $message = "customerid obligatoire";
            }
            if (isset($data['siren'])) {
                $siren = $data['siren'];
                $client = $clientService->getClientBySiren($siren,$enseigne);
                if ($client) {
                    $message = "Le client existe déjà (" . $client->getRaisonSociale().") pour le siren " . $siren . ". Veuillez accéder à la fiche du client et ajouter un customerId pour le programme d'enrôlement.";
                }
            }
            else $message = "siren obligatoire";
            $email = $data['email'] ?? "";

            $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "création client rs [" . $rs . "] customerId [" . $customerId . "] siren [". $siren. "] email [". $email . "]", SourceEnum::BackController);
            $this->logger->writeLog(LogLevelEnum::Debug, __METHOD__ . "[" . __LINE__ . "]", "[JSON]" .$request->getContent(), SourceEnum::BackController);

            if ($message == "") {
                $this->logger->writeLog(LogLevelEnum::Debug, __METHOD__ . "[" . __LINE__ . "]", "recherche customerId [" . $customerId . "]", SourceEnum::BackController);
                $rs = $data['rs'] ?? "";
                $referent = $data['referent'] ?? "";
                $commentaire = $data['commentaire'] ?? "";
                $client = $clientService->createClient($rs, $referent, $commentaire, $utilisateur, $enseigne, $siren);
                $clientService->setProgrammeEnrolement($client, $pgmEnrolement, $customerId, true, $email);
                $error = CodeErreurEnum::ok;
                $clientId = $client->getId();
            }
        } catch (\Exception $exception) {
            $error = CodeErreurEnum::ex;
            $message = $exception->getMessage();
        }

        if ($message != "") $this->logger->writeLog(LogLevelEnum::Erreur,__METHOD__ . "[".__LINE__."]",$message, SourceEnum::BackController);

        return $this->json([
            'error' => $error,
            'message' => $message,
            'clientid' => $clientId,
        ]);
    }


    // WS permettant de charger un clent
    // Champs en entréé
    // clientid : identifiant client
    // Champs en retour:
    // - clientid : Identifiant client
    // - customerid : customer Id du programme en cours
    // - actif : true/fasle sur le programme en cours
    // - rs : raison sociale
    // - transactions : tableaux des 10 dernières transactions avec :
    //       - id
    //       - date
    //       - type : type de transaction
    //       - statut : Statut de transaction
    //       - nb : nombre d'appareils


    #[Route('/back/client/read', name: 'app_client_read')]
    public function readClient(Request $request, AUIService $auiService, ClientService $clientService): JsonResponse
    {
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", $request->getContent(), SourceEnum::ClientJson);

        $error = CodeErreurEnum::nok;
        $message = "";
        $retour = null;
        $clientId = "";
        try {
            $this->setSession($request);
            $pgmEnrolement = $this->sessionService->getProgramme();
            $utilisateur = $this->sessionService->getUtilisateur();
            $enseigne = $this->sessionService->getEnseigne();

            $data = json_decode($request->getContent(), true);
            if (isset($data['clientid'])) {
                $clientId = $data['clientid'];
                if ($message != "") $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "Lecture client [" . $clientId . "]", SourceEnum::BackController);
                $client = $clientService->getClientById($clientId);
                if ($client) {
                    /** @var PgmClient $pgmClient */
                    //                   $pgmClients = $clientService->getPgmClient($client,$pgmEnrolement);
                    $customerIds = $clientService->getCustomerIds($client, $pgmEnrolement);
                    $lastOrders = $clientService->getLastOrdersTransaction($client, $pgmEnrolement);
                    $error = CodeErreurEnum::ok;
                    $retour = array("rs" => $client->getRaisonSociale(), "actif" => $client->isActif(), "siren" => $client->getSiren(), "clientid" => $client->getId(), "customerids" => $customerIds, "transactions" => $lastOrders);
                } else {
                    $message = "client inexistant";
                }
            } else {
                $message = "clientid obligatoire";
            }
        } catch (\Exception $exception) {
            $error = CodeErreurEnum::ex;
            $message = $exception->getMessage();
        }

        if ($message != "") $this->logger->writeLog(LogLevelEnum::Erreur, __METHOD__ . "[" . __LINE__ . "]", $message, SourceEnum::BackController);

        return $this->json([
            'error' => $error,
            'message' => $message,
            'client' => $retour,
        ]);
    }

    #[Route('/back/client/save', name: 'app_client_save')]
    public function saveClient(Request $request, AUIService $auiService, ClientService $clientService): JsonResponse
    {

        $error = CodeErreurEnum::nok;
        $message = "";
        $clientId = "";
        $customerIds = null;
        $rs = "";
        $siren = "";
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", $request->getContent(), SourceEnum::ClientJson);

        try {
            $this->setSession($request);
            $utilisateur = $this->sessionService->getUtilisateur();   // Pgm d'enrolement en cours
            $enseigne = $this->sessionService->getEnseigne();   // Pgm d'enrolement en cours

            $data = json_decode($request->getContent(), true);

            $this->logger->writeLog(LogLevelEnum::Debug, __METHOD__ . "[".__LINE__."]", "[JSON]".$request->getContent(), SourceEnum::ClientJson);

            $actif = boolval($data["actif"] ?? false);
            if (isset($data['rs'])) $rs = $data['rs']; else $message = "[Raison sociale obligatoire]";
            if (isset($data['clientid'])) $clientId = $data['clientid']; else $message .= "[clientid obligatoire]";
            if (!is_int($clientId)) $message .= "[Identifiant client invalide]";
            $client = $clientService->getClientById($clientId);
            if (!$client) $message = "[client ".$clientId." introuvable]";
            if (isset($data['customerids'])) $customerIds = $data['customerids'];
            $siren = $data['siren'] ?? "";
            if ($siren == "")  $message .= "[siren obligatoire]";

            $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[".__LINE__."]", "sauvegarde client id [".$clientId."] rs [" . $rs . "] siren [".$siren."]", SourceEnum::BackController);

            if ($message === "") {
                $client = $clientService->saveClient($client, $rs, $utilisateur, $enseigne, $siren, $actif, $customerIds);
                $error = CodeErreurEnum::ok;
                $clientId = $client->getId();
            }
        } catch (\Exception $exception) {
            $error = CodeErreurEnum::ex;
            $message = $exception->getMessage();
        }

        if ($message != "") $this->logger->writeLog(LogLevelEnum::Erreur,__METHOD__ . "[".__LINE__."]",$message, SourceEnum::BackController);

        return $this->json([
            'error' => $error,
            'message' => $message,
            'clientid' => $clientId,
        ]);
    }

    #[Route('/back/client/set_pgm', name: 'set_pgm')]
    public function setPgmClient(Request $request, ClientService $clientService): JsonResponse
    {
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", $request->getContent(), SourceEnum::ClientJson);

        $retour = array();
        try {
            $this->setSession($request);

            $utilisateur = $this->sessionService->getUtilisateur();
            $pgmEnrolement = $this->sessionService->getProgramme();
            $utilisateur = $this->sessionService->getUtilisateur();
            $enseigne = $this->sessionService->getEnseigne();

            $data = json_decode($request->getContent(), true);
            $error = CodeErreurEnum::ok;
            $message =  "";

            if (isset($data['customerid'])) $customerId = trim($data['customerid']); else $message = "customerid obligatoire";
            $email = $data['email'] ?? "";

            if (isset($data['clientid'])) {
                $clientId = $data['clientid'];
                if ($message != "") $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "Lecture client [" . $clientId . "]", SourceEnum::BackController);
                $client = $clientService->getClientById($clientId);
                if (!$client) $message = "client inexistant";
            } else {
                $message = "clientid obligatoire";
            }

            if (isset($data['clientid'])) {
                $clientId = $data['clientid'];
            }

            $clientService->setProgrammeEnrolement($client, $pgmEnrolement,$customerId,true,$email);

        } catch (\Exception $exception) {
            $error = CodeErreurEnum::ex;
            $message = $exception->getMessage();
        }

        if ($message != "") $this->logger->writeLog(LogLevelEnum::Erreur, __METHOD__ . "[" . __LINE__ . "]", $message, SourceEnum::BackController);

        $json = $this->json(['error' => $error,
            'message' => $message]);
        return $json;
    }

}
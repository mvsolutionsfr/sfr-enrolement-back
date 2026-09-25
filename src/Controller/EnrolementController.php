<?php

namespace App\Controller;

use App\Entity\Client;
use App\Entity\PgmClient;
use App\Entity\PgmEnrolement;
use App\Service\AUIService;
use App\Service\ClientService;
use App\Service\EnrolementService;
use App\Service\SessionService;
use App\Service\TerminalService;
use App\Toolbox\CodeErreurEnum;
use App\Toolbox\LogLevelEnum;
use App\Toolbox\SourceEnum;
use App\Toolbox\TerminalIdentifierEnum;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;

class EnrolementController extends AngularController
{

    private string $pgmToSet;
    private PgmEnrolement $pgmEnrolement;

    #[Route('/back/customerIds_client', name: 'customerids_client')]
    public function customerIdClient(Request $request, TerminalService $terminalService, AUIService $auiService, ClientService $clientService): JsonResponse
    {
//  { Exemple d'appel
//    "siren" : "16546464",
//    "programme" : "depApple","knox","Zerotouch"
//   }
        $cmdId = "";
        $message = "";
        $error = CodeErreurEnum::ok;
        $erreur = "";

        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", $request->getContent(), SourceEnum::ClientJson);

        $customerId = array();
        try {
            $data = json_decode($request->getContent(), true);
            $siren = $data["siren"] ?? "";
            $pgmName = $data["pgm"] ?? "";
            $this->pgmToSet = "";

            switch ($pgmName) {
                case "depApple":
                    $this->pgmToSet = "Apple";
                    $fabricantId = "Apple";
                    break;
                case "knox":
                    $this->pgmToSet = "Knox";
                    $fabricantId = "Samsung";
                    break;
                case "ZeroTouch":
                    $this->pgmToSet = "Zerotouch";
                    break;
                default:
                    $error = CodeErreurEnum::nok;
                    $message = "Programme " . $pgmName . " inconnu";

            }

            if ($this->pgmToSet != "") {
                $utilisateur = $auiService->automate();
                $enseigne = $utilisateur->getEnseigne();

                $this->sessionService->setUtilisateur($utilisateur);

                $pgmEnrol = $enseigne->getPgmEnrolements();

                $pgmFound = $pgmEnrol->filter(function ($value) {
                    return $value->getPgmEnrolement()->getLibelle() == $this->pgmToSet;
                });
                if ($pgmFound && $pgmFound->first()) {
                    $pgmEnrol = $pgmFound->first()->getPgmEnrolement();
                    $this->sessionService->setProgramme($pgmEnrol);
                } else {
                    $error = CodeErreurEnum::nok;
                    $message = "impossible d'affecter le programme " . $this->pgmToSet;
                }

                $this->sessionService->setUtilisateur($utilisateur);
                $client = $clientService->getClientBySiren($siren,$enseigne);
                if ($client == null) {
                    $error = CodeErreurEnum::nok;
                    $message = "impossible de trouver le client";
                } else {
                    $customerId = $clientService->getCustomerIds($client, $pgmEnrol);
                }
            }

        } catch (\Exception $exception) {
            $error = CodeErreurEnum::ex;
            $message = $exception->getMessage();
        }

        if ($message != "") $this->logger->writeLog(LogLevelEnum::Erreur, __METHOD__ . "[" . __LINE__ . "]", $message, SourceEnum::BackController);

        return $this->json(['error' => $error,
            'message' => $message, 'siren' => $siren, 'customerId' => $customerId]);

    }

    #[Route('/back/desenrolement_demande', name: 'desenrolement_terminaux_demande')]
    public function desenrolementDemande(Request $request, TerminalService $terminalService, AUIService $auiService, EnrolementService $enrolementService): JsonResponse
    {
        return $this->desenrolementEmgc($request, $terminalService, $auiService, $enrolementService);
    }

    #[Route('/back/desenrolement_emgc', name: 'desenrolement_terminaux_emgc')]
    public function desenrolementEmgc(Request $request, TerminalService $terminalService, AUIService $auiService, EnrolementService $enrolementService): JsonResponse
    {
//  { Exemple d'appel
//    "terminaux": [ "866228059717256", "868074057439230", "356557082621540" ],
//   }
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", $request->getContent(), SourceEnum::ClientJson);

        try {
            $error = CodeErreurEnum::ok;
            $message = "";
            $data = json_decode($request->getContent(), true);
            $this->logger->writeLog(LogLevelEnum::Debug, __METHOD__ . "[" . __LINE__ . "]", "[Desenrolement EMGC][JSON] " . $this->json($data), SourceEnum::BackController);
            $terminaux = $data["terminaux"] ?? null;

            $utilisateur = $auiService->automate();
            $enseigne = $utilisateur->getEnseigne();

            $this->sessionService->setUtilisateur($utilisateur);
            $this->sessionService->setEnseigne($enseigne);

            if ($terminaux == null || count($terminaux) == 0) {
                $error = CodeErreurEnum::nok;
                $message = "Terminaux non renseignés";
            }

            if ($error == CodeErreurEnum::ok) {
                $retour = $enrolementService->desenrolement($utilisateur, $terminaux);
                if (count($retour) != 0) {
                    $error = CodeErreurEnum::nok;
                    $message = "Terminaux à désenroler en erreur";
                }
            }
        } catch (\Exception $exception) {
            $error = CodeErreurEnum::ex;
            $message = $exception->getMessage();
        }


        if ($message != "") $this->logger->writeLog(LogLevelEnum::Erreur, __METHOD__ . "[" . __LINE__ . "]", $message, SourceEnum::BackController);

        $json = $this->json(['error' => $error, 'message' => $message, 'terminaux' => $retour]);
        $this->logger->writeLog(LogLevelEnum::Debug, __METHOD__ . "[" . __LINE__ . "]", "[Desenrolement][RETOUR] " . $json, SourceEnum::BackController);
        return $json;
    }

    #[Route('/back/enrolement_demande', name: 'enrolement_terminaux_demande')]
    public function enrolementDemande(Request $request, TerminalService $terminalService, AUIService $auiService, EnrolementService $enrolementService): JsonResponse
    {
        return $this->enrolementEmgc($request, $terminalService, $auiService, $enrolementService);
    }

    #[Route('/back/enrolement_emgc', name: 'enrolement_terminaux_emgc')]
    public function enrolementEmgc(Request $request, TerminalService $terminalService, AUIService $auiService, EnrolementService $enrolementService): JsonResponse
    {
//  { Exemple d'appel
//    "siren" : "16546464",
//    "terminaux": [ "866228059717256", "868074057439230", "356557082621540" ],
//    "reference" : Numero commande
//    "programme" : "Apple","Knox","ZT"
//   }
        $cmdId = "";
        $message = "";
        $error = CodeErreurEnum::ok;
        $erreur = "";

        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", $request->getContent(), SourceEnum::ClientJson);

        try {
            $data = json_decode($request->getContent(), true);
            $siren = $data["siren"] ?? "";
            $customerId = $data["customerId"] ?? "";
            $reference = $data["reference"] ?? "";
            $terminaux = $data["terminaux"] ?? null;
            $fabricantId = $data["fabricant"] ?? "";
            $pgmName = $data["pgm"] ?? "";
            $terminauxIdType = $data["type_id_terminal"] ?? "";
            $this->pgmToSet = $pgmName;


            $utilisateur = $auiService->automate();
            $enseigne = $utilisateur->getEnseigne();

            $this->sessionService->setUtilisateur($utilisateur);

            switch ($pgmName) {
                case "depApple":
                    $this->pgmToSet = "Apple";
                    $fabricantId = "Apple";
                    break;
                case "knox":
                    $this->pgmToSet = "Knox";
                    $fabricantId = "Samsung";
                    break;
                case "ZeroTouch":
                    $this->pgmToSet = "Zerotouch";
                    break;
                default:
                    $error = CodeErreurEnum::nok;
                    $message = "Programme " . $pgmName . " inconnu";
            }

            $fabricant = $terminalService->getFabricantByCode($fabricantId);

            $pgmEnrol = $enseigne->getPgmEnrolements();

            $pgmFound = $pgmEnrol->filter(function ($value) {
                return $value->getPgmEnrolement()->getLibelle() == $this->pgmToSet;
            });
            $resellerId = "";
            if ($pgmFound && $pgmFound->first()) {
                $resellerId = $pgmFound->first()->getResellerId();
                $pgmEnrol = $pgmFound->first()->getPgmEnrolement();
                $this->sessionService->setProgramme($pgmEnrol);
            } else {
                $error = CodeErreurEnum::nok;
                $message = "impossible d'affecter le programme " . $this->pgmToSet;
            }

            if ($error != CodeErreurEnum::nok) {
                $tif = TerminalIdentifierEnum::IMEI;

                if ($customerId === "") {
                    $error = CodeErreurEnum::nok;
                    $message = "CustomerId non renseigné";
                }

                $typeIdentifier = TerminalIdentifierEnum::IMEI;
                if ($terminauxIdType != "") {
                    $typeIdentifier = TerminalIdentifierEnum::tryFrom($terminauxIdType);
                    if ($typeIdentifier == null) {
                        $error = CodeErreurEnum::nok;
                        $message = "Type d'identifiant de terminal incorrect (IMEI ou SN)";
                    }
                }

                if ($fabricantId === "") {
                    $error = CodeErreurEnum::nok;
                    $message = "Fabricant non renseigné";
                }
                if ($terminaux == null || count($terminaux) == 0) {
                    $error = CodeErreurEnum::nok;
                    $message = "Terminaux non renseignés";
                }

                if ($error == CodeErreurEnum::ok) {
                    $fabricant = $terminalService->getFabricantByCode($fabricantId);
                    if ($fabricant == null) {
                        $error = CodeErreurEnum::nok;
                        $message = "Fabricant $fabricantId inconnu";
                    } else {
                        $cmdId = "";
                        $retour = $enrolementService->enrolementBySiren($utilisateur, $pgmEnrol, $siren, $customerId, $enseigne, $reference, $terminaux, $fabricant, $tif, $typeIdentifier);
                        $cmd = $retour["cmd"] ?? false;
                        if ($cmd) $cmdId = $cmd->getId();
                        $erreur = $retour["erreur"] ?? "";
                        $nbEnrole = $retour["nbEnrole"] ?? 0;
                        if (count($erreur) != 0) {
                            $error = CodeErreurEnum::nok;
                            array_splice($erreur, 0, 0, "Terminaux à inscrire en erreur : ");
                            if ($nbEnrole == 0) {
                                $message = "Aucun terminal inscrit.";
                            } else {
                                $message = "$nbEnrole terminal(aux) inscrit(s).";
                            }
                        }
                    }
                }
            }
        } catch (\Exception $exception) {
            $error = CodeErreurEnum::ex;
            $message = $exception->getMessage();
        }

        if ($message != "") $this->logger->writeLog(LogLevelEnum::Erreur, __METHOD__ . "[" . __LINE__ . "]", $message, SourceEnum::BackController);

        return $this->json(['error' => $error,
            'message' => $message, 'cmd' => $cmdId, 'terminaux' => $erreur]);
    }

    #[Route('/back/enrolement', name: 'enrolement_terminaux')]
    public function enrolement(Request $request, TerminalService $terminalService, ClientService $clientService, EnrolementService $enrolementService): JsonResponse
    {
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "[ENROLEMENT][JSON]" . $request->getContent(), SourceEnum::ClientJson);
        $data = json_decode($request->getContent(), true);
        $retour = array();
        $erreur = "";
        $error = CodeErreurEnum::ok;
        $message = "";
        $cmdId = "";
        try {
            $this->setSession($request);

            $utilisateur = $this->sessionService->getUtilisateur();
            $this->pgmEnrolement = $this->sessionService->getProgramme();
            $enseigne = $this->sessionService->getEnseigne();

            $data = json_decode($request->getContent(), true);

            $clientId = $data["client"] ?? "";
            $customerId = $data["customerid"] ?? "";
            $reference = $data["reference"] ?? "";
            $terminaux = $data["terminaux"] ?? null;
            $fabricantId = $data["fabricant"] ?? "";
            $typeIdTerminal = $data["type_id_terminal"] ?? "";

            // test les doublons
            $terminauxNb = count($terminaux);
            $erreur = false;
            for ($terminauxInd = 0; $terminauxInd < $terminauxNb - 1; $terminauxInd++) {
                $terminalId = $terminaux[$terminauxInd];
                for ($terminauxSuivantInd = $terminauxInd + 1; $terminauxSuivantInd < $terminauxNb; $terminauxSuivantInd++) {
                    if ($terminalId == $terminaux[$terminauxSuivantInd]) {
                        $erreur = $terminalId;
                        break;
                    }
                }
                if ($erreur != false) break;
            }
            if ($erreur != false) {
                return $this->json(['error' => CodeErreurEnum::nok,
                    'message' => 'terminal ' . $erreur . ' doublonné', 'cmd' => '', 'terminaux' => $erreur]);
            }

            /** @var Client $customer */
            $customer = null;

            if ($clientId != "") {
                $customer = $clientService->getClientById($clientId);
                if ($customer == null) {
                    $error = CodeErreurEnum::nok;
                    $message = "Client $clientId introuvable";
                }
            } else {
                $error = CodeErreurEnum::nok;
                $message = "Client non renseigné";
            }

            if ($this->pgmEnrolement->getLibelle() == "Apple") $fabricantId = "Apple";
            if ($this->pgmEnrolement->getLibelle() == "Knox") $fabricantId = "Samsung";

            if ($fabricantId === "") {
                $error = CodeErreurEnum::nok;
                $message = "Fabricant non renseigné";
            }
            if ($terminaux == null || $terminaux == "" || count($terminaux) == 0) {
                $error = CodeErreurEnum::nok;
                $message = "Terminaux non renseignés";
            }

            if ($typeIdTerminal === "") {
                $error = CodeErreurEnum::nok;
                $message = "Type d'identifiant des terminaux non renseignés";
            }

            if ($customerId === "") {
                if ($customer) {
                    /** @var Collection $pgmsClient */
                    $pgmsClient = $customer->getPgmEnrolements()->filter(function ($value) {
                        return $value->getPgmEnrolement()->getId() == $this->pgmEnrolement->getId();
                    });
                    if ($pgmsClient->count() != 1) {
                        $error = CodeErreurEnum::nok;
                        $message = "customerid non unique sur le programme";
                    } else {
                        /** @var PgmClient $pgmfirst */
                        $customerId = $pgmsClient->first()->getCustomerId();
                    }
                } else {
                    $error = CodeErreurEnum::nok;
                    $message = "customerid non renseigné";
                }
            } else {
                $clientsC = $clientService->getClientByCustomerId($customerId);
                $trouve = false;
                foreach ($clientsC as $clientC) {
                    if ($clientC->getId() == $customer->getId()) {
                        $trouve = true;
                        break;
                    }
                }
                if (!$trouve) {
                    $error = CodeErreurEnum::nok;
                    $message = "Le customerid fourni n'est pas attache au client " . $customer->getRaisonSociale();
                }
            }

            $tif = TerminalIdentifierEnum::from($typeIdTerminal);

            if ($error == CodeErreurEnum::ok) {
                $fabricant = $terminalService->getFabricantByCode($fabricantId);
                if ($fabricant == null) {
                    $error = CodeErreurEnum::nok;
                    $message = "Fabricant $fabricantId inconnu";
                } else {
                    $retour = $enrolementService->enrolement($utilisateur, $this->pgmEnrolement, $customer, $customerId, $enseigne, $reference, $terminaux, $fabricant, $tif);
                    $cmd = $retour["cmd"] ?? false;
                    if ($cmd) $cmdId = $cmd->getId();
                    $erreur = $retour["erreur"] ?? "";
                    $nbEnrole = $retour["nbEnrole"] ?? 0;
                    if (count($erreur) != 0) {
                        $error = CodeErreurEnum::nok;
                        array_splice($erreur, 0, 0, "Terminaux à inscrire en erreur : ");
                        if ($nbEnrole == 0) {
                            $message = "Aucun terminal inscrit.";
                        } else {
                            $message = "$nbEnrole terminal(aux) inscrit(s).";
                        }
                    }
                }
            }
        } catch (\Exception $exception) {
            $error = CodeErreurEnum::ex;
            $message = $exception->getMessage();
        }

        if ($message != "") $this->logger->writeLog(LogLevelEnum::Erreur, __METHOD__ . "[" . __LINE__ . "]", $message, SourceEnum::BackController);

        $json = $this->json(['error' => $error,
            'message' => $message, 'cmd' => $cmdId, 'terminaux' => $erreur]);
        $this->logger->writeLog(LogLevelEnum::Debug, __METHOD__ . "[" . __LINE__ . "]", "[Enrolement][RETOUR] " . $json, SourceEnum::BackController);

        return $json;
    }

    #[Route('/enrolement/dotasks', name: 'dotasks')]
    public function doTasks(Request $request, EnrolementService $enrolementService): JsonResponse
    {
        //       $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "DoTasks", SourceEnum::BackController);
        $error = CodeErreurEnum::ok;
        $message = "";

        $enrolementService->processCommandesByStatus();

        if ($message != "") $this->logger->writeLog(LogLevelEnum::Erreur, __METHOD__ . "[" . __LINE__ . "]", $message, SourceEnum::BackController);
        return $this->json(['error' => $error, 'message' => $message,]);
    }

    #[Route('/back/desenrolement', name: 'desenrolement_terminaux')]
    public function desenrolement(Request $request, TerminalService $terminalService, ClientService $clientService, EnrolementService $enrolementService): JsonResponse
    {
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", $request->getContent(), SourceEnum::ClientJson);
        $retour = array();
        try {
            $this->setSession($request);

            $utilisateur = $this->sessionService->getUtilisateur();

            $data = json_decode($request->getContent(), true);
            $error = CodeErreurEnum::ok;
            $message = "";

            $terminaux = $data["terminaux"] ?? null;

            $clientId = $data["client"] ?? "";
//            if ($clientId == "") {
//                $error = CodeErreurEnum::nok;
//                $message = "Client non renseigné";
//            }

            if ($terminaux == null || count($terminaux) == 0) {
                $error = CodeErreurEnum::nok;
                $message = "Terminaux non renseignés";
            }

            $retour = $enrolementService->desenrolement($utilisateur, $terminaux);

            $erreur = $retour["erreur"] ?? "";
            $nbEnrole = $retour["nbDesenrole"] ?? 0;
            if (count($erreur) != 0) {
                $error = CodeErreurEnum::nok;
                array_splice($erreur, 0, 0, "Terminaux à désinscrire en erreur : ");
                if ($nbEnrole == 0) {
                    $message = "Aucun terminal désinscrit.";
                } else {
                    $message = "$nbEnrole terminal(aux) désinscrit(s).";
                }
            }


        } catch (\Exception $exception) {
            $error = CodeErreurEnum::ex;
            $message = $exception->getMessage();
        }


        if ($message != "") $this->logger->writeLog(LogLevelEnum::Erreur, __METHOD__ . "[" . __LINE__ . "]", $message, SourceEnum::BackController);

        $json = $this->json(['error' => $error, 'message' => $message, 'terminaux' => $erreur]);
        $this->logger->writeLog(LogLevelEnum::Debug, __METHOD__ . "[" . __LINE__ . "]", "[Desenrolement][RETOUR] " . $json, SourceEnum::BackController);
        return $json;
    }

    #[Route('/back/synchro', name: 'synchro')]
    public function synchroApple(Request $request, EnrolementService $enrolementService): JsonResponse
    {
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "[SYNCHRO][JSON]" . $request->getContent(), SourceEnum::ClientJson);
        $retour = array();
        try {
            $this->setSession($request);

            $utilisateur = $this->sessionService->getUtilisateur();

            $data = json_decode($request->getContent(), true);
            $error = CodeErreurEnum::ok;
            $message = "";

            $orderId = $data["orderId"] ?? "";

            if ($orderId === "") {
                $error = CodeErreurEnum::nok;
                $message = "Numero de commande (orderId) non renseigné";
            }

            $enrolementService->synchro($utilisateur, $orderId);

        } catch (\Exception $exception) {
            $error = CodeErreurEnum::ex;
            $message = $exception->getMessage();
        }

        if ($message != "") $this->logger->writeLog(LogLevelEnum::Erreur, __METHOD__ . "[" . __LINE__ . "]", $message, SourceEnum::BackController);

        return $this->json(['error' => $error, 'message' => $message]);
    }

}


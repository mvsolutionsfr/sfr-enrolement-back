<?php

namespace App\Controller;

use App\Entity\TerminalSuivi;
use App\Service\ClientService;
use App\Service\DashboardService;
use App\Service\EnrolementService;
use App\Service\SessionService;
use App\Service\TerminalService;
use App\Toolbox\CodeErreurEnum;
use App\Toolbox\LogLevelEnum;
use App\Toolbox\SourceEnum;
use App\Toolbox\StatutEnrolementEnum;
use App\Toolbox\StatutRequeteEnum;
use App\Toolbox\TypeEnrolementEnum;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AngularController
{
    #[Route('/back/dashboard/totalclients', name: 'app_dashboard')]
    public function totalClients(DashboardService $dashboardService, Request $request): Response
    {
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", $request->getContent(), SourceEnum::ClientJson);

        $this->setSession($request);
        $nbClients = 0;
        $error = 0;
        $message = "";

        try {
            $nbClients = $dashboardService->getTotalClients();
        } catch (\Exception $exception) {
            $error = $exception->getCode();
            $message = $exception->getMessage();
        }

        if ($message != "") $this->logger->writeLog(LogLevelEnum::Erreur, __METHOD__ . "[" . __LINE__ . "]", $message, SourceEnum::BackController);

        return $this->json([
            'error' => $error,
            'message' => $message,
            'nbClients' => $nbClients
        ]);
    }

    #[Route('/back/dashboard/nbEnrolementJour', name: 'app_dashboard_nb_jour')]
    public function nbEnrolementDuJour(DashboardService $dashboardService, Request $request): Response
    {
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", $request->getContent(), SourceEnum::ClientJson);

        $this->setSession($request);
        $nbClients = 0;
        $error = 0;
        $message = "";

        try {
            $nbClients = $dashboardService->getNbEnrolementDuJour();
        } catch (\Exception $exception) {
            $error = $exception->getCode();
            $message = $exception->getMessage();
        }

        if ($message != "") $this->logger->writeLog(LogLevelEnum::Erreur, __METHOD__ . "[" . __LINE__ . "]", $message, SourceEnum::BackController);

        return $this->json([
            'error' => $error,
            'message' => $message,
            'nbClients' => $nbClients
        ]);
    }

    #[Route('/back/dashboard/topClientsAnnee', name: 'app_dashboard_top_annee')]
    public function topClientsAnnee(DashboardService $dashboardService, Request $request): Response
    {

        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", $request->getContent(), SourceEnum::ClientJson);

        $this->setSession($request);
        $util = $this->sessionService->getUtilisateur();
        $nbClients = 0;
        $error = 0;
        $message = "";
        $topclients = array();
        try {
            $topclients = $dashboardService->topClientsAnnee();

        } catch (\Exception $exception) {
            $error = $exception->getCode();
            $message = $exception->getMessage();
        }

        if ($message != "") $this->logger->writeLog(LogLevelEnum::Erreur, __METHOD__ . "[" . __LINE__ . "]", $message, SourceEnum::BackController);

        return $this->json([
            'error' => $error,
            'message' => $message,
            'nbClients' => $topclients
        ]);
    }

    #[Route('/back/dashboard/version', name: 'app_dashboard_version')]
    public function version(Request $request): Response
    {
        return $this->json([
            'error' => '0',
            'message' => '',
            'version' => $_SERVER['VERSION']
        ]);

    }

//Le journal sera un tableau contenant :
//la raison sociale du client
//le customerId
//la date de la transaction
//la transaction
//le type de transaction
//l'utilisateur
//le statut de la transaction
//l'id order (commande)
//le nombre d'appareils

    #[Route('/back/dashboard/activites', name: 'app_dashboard_activites')]
    public function activites(EnrolementService $enrolementService, Request $request): Response
    {
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", $request->getContent(), SourceEnum::ClientJson);

        $this->setSession($request);
        $data = json_decode($request->getContent(), true);
        $clientId = $data["clientId"] ?? "";
        $page = $data['page'] ?? 1;
        $maxResult = $data['maxResult'] ?? 10;
        $maxPage = 0;

        $transactions = $enrolementService->getTransactions($clientId, $page, $maxResult);
        $maxPage = count($transactions);

        $retour = array();
        foreach ($transactions as $transaction) {
            try {
                $transaction["typeTransaction"] = TypeEnrolementEnum::from($transaction["typeTransaction"])->name;
            } catch (\Exception) {
                $transaction["typeTransaction"] = "?";
            }

            try {
                $transaction["statut"] = StatutEnrolementEnum::from($transaction["statut"])->label();
            } catch (\Exception) {
                $transaction["statut"] = "?";
            }
            $retour[] = $transaction;
        }

        return $this->json([
            'error' => '0',
            'message' => '',
            'transactions' => $retour,
            'maxPage' => $maxPage
        ]);
    }

    #[Route('/back/dashboard/transacs_erreur', name: 'app_dashboard_trasac_erreur')]
    public function transacsEnErreur(EnrolementService $enrolementService, Request $request): Response
    {
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", $request->getContent(), SourceEnum::ClientJson);

        $this->setSession($request);
        $data = json_decode($request->getContent(), true);
        $clientId = $data["clientId"] ?? "";
        $page = $data['page'] ?? 1;
        $maxResult = $data['maxResult'] ?? 10;
        $maxPage = 0;

        $transactions = $enrolementService->getTransactionsEnErreur($clientId, $page, $maxResult);
        $maxPage = count($transactions);

        $retour = array();
        foreach ($transactions as $transaction) {
            try {
                $transaction["typeTransaction"] = TypeEnrolementEnum::from($transaction["typeTransaction"])->name;
            } catch (\Exception) {
                $transaction["typeTransaction"] = "?";
            }

            try {
                $transaction["statut"] = StatutEnrolementEnum::from($transaction["statut"])->label();
            } catch (\Exception) {
                $transaction["statut"] = "?";
            }
            $retour[] = $transaction;
        }

        return $this->json([
            'error' => '0',
            'message' => '',
            'transactions' => $retour,
            'maxPage' => $maxPage
        ]);
    }

    #[Route('/back/transaction/imeis', name: 'getImeisTransac')]
    public function readImeisTransaction(Request $request, TerminalService $terminalService, ClientService $clientService, EnrolementService $enrolementService): JsonResponse
    {
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", $request->getContent(), SourceEnum::ClientJson);
        $error = CodeErreurEnum::ok;
        $message = "";
        $retour = array();
        try {
            $this->setSession($request);

            $utilisateur = $this->sessionService->getUtilisateur();

            $data = json_decode($request->getContent(), true);

            $page = $data['page'] ?? 1;
            $maxResult = $data['maxResult'] ?? 10;
            $maxPage = 0;

            if (isset($data['transactionid'])) {
                $transactionId = $data['transactionid'];
                $suivis = $enrolementService->getImeisTransaction($transactionId, $page, $maxResult);
                $maxPage = count($suivis);
                /** @var TerminalSuivi $suivi */
                foreach ($suivis as $suivi) {
                    $terminal = [
                        "imei" => $suivi["numeroIMEI"] ?? "",
                        "serie" => $suivi["numeroSerie"] ?? "",
                        "statut" => StatutRequeteEnum::from($suivi["statut"] ?? "0")->label(),
                        "message" => $suivi["message"] ?? ""
                    ];
                    $retour[] = $terminal;
                }
            } else {
                $error = CodeErreurEnum::nok;
                $message = "transactionid obligatoire";
            }

        } catch (\Exception $exception) {
            $error = CodeErreurEnum::ex;
            $message = $exception->getMessage();
        }

        return $this->json(['error' => $error, 'message' => $message, 'imeis' => $retour, 'maxPage' => $maxPage]);

    }

    #[Route('/back/transaction/get', name: 'getTransac')]
    public function readTransaction(Request $request, TerminalService $terminalService, ClientService $clientService, EnrolementService $enrolementService): JsonResponse
    {
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", $request->getContent(), SourceEnum::ClientJson);

        $error = CodeErreurEnum::ok;
        $message = "";
        $retour = array();
        try {
            $this->setSession($request);

            $utilisateur = $this->sessionService->getUtilisateur();

            $data = json_decode($request->getContent(), true);

            if (isset($data['transactionid'])) {
                $transactionId = $data['transactionid'];
                $retour = $enrolementService->getTransaction($transactionId);
            } else {
                $error = CodeErreurEnum::nok;
                $message = "transactionid obligatoire";
            }

        } catch (\Exception $exception) {
            $error = CodeErreurEnum::ex;
            $message = $exception->getMessage();
        }

        return $this->json(['error' => $error, 'message' => $message, 'transaction' => $retour]);

    }

    #[Route('/back/transaction/ack', name: 'ackTransac')]
    public function ackTransaction(Request $request, TerminalService $terminalService, ClientService $clientService, EnrolementService $enrolementService): JsonResponse
    {
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", $request->getContent(), SourceEnum::ClientJson);
        $error = CodeErreurEnum::ok;
        $message = "";
        $retour = array();
        try {
            $this->setSession($request);

            $utilisateur = $this->sessionService->getUtilisateur();

            $data = json_decode($request->getContent(), true);

            if (isset($data['transactionid'])) {
                $transactionId = $data['transactionid'];
                $enrolementService->ackTransaction($transactionId);

            } else {
                $error = CodeErreurEnum::nok;
                $message = "transactionid obligatoire";
            }

        } catch (\Exception $exception) {
            $error = CodeErreurEnum::ex;
            $message = $exception->getMessage();
        }

        return $this->json(['error' => $error, 'message' => $message, 'transaction' => $retour]);
    }

}

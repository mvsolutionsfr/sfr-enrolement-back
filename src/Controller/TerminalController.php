<?php

namespace App\Controller;

use App\Entity\Orders;
use App\Entity\Terminal;
use App\Service\ClientService;
use App\Service\OrdersService;
use App\Service\SessionService;
use App\Service\TerminalService;
use App\Toolbox\CodeErreurEnum;
use App\Toolbox\LogLevelEnum;
use App\Toolbox\SourceEnum;
use App\Toolbox\StatutImeiEnum;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\PersistentCollection;
use App\Entity\PgmEnrolement;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;

class TerminalController extends AngularController
{
    private PersistentCollection $enrolements;
    private Collection $terminalSuivis;
    private PgmEnrolement $pgmEnrolement;
    private int $critereMin = 3;

    #[Route('/back/terminal/recherche', name: 'app_recherche_terminal')]
    public function getTerminaux(Request $request, TerminalService $terminalService, ClientService $clientService, OrdersService $ordersService): JsonResponse
    {

        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", $request->getContent(), SourceEnum::ClientJson);

        $this->setSession($request);
        $this->pgmEnrolement = $this->sessionService->getProgramme();   // Pgm d'enrolement en cours
        $data = json_decode($request->getContent(), true);
        $error = CodeErreurEnum::ok;
        $message = "";
        $resultats = array();
        $terminaux = null;
        $critere = "";
        $page = $data['page'] ?? 1;
        $maxResult = $data['maxResult'] ?? 10;
        $maxPage = 0;

        if (isset($data['critere']) && $data['critere'] != "") {
            $critere = $data['critere'];
            $this->logger->writeLog(LogLevelEnum::Debug, __METHOD__ . "[" . __LINE__ . "]", "Recherche Terminaux [" . $critere . "]", SourceEnum::BackController);
            if (strlen($critere) >= $this->critereMin) {
                $terminaux = $terminalService->getTerminaux($critere);
                $maxPage = count($terminaux);
            } else {
                $error = CodeErreurEnum::nok;
                $message = "La recherche doit se faire sur " . $this->critereMin . " caractères minimum";
            }
        } elseif (isset($data['clientId'])) {
            $clientId = $data['clientId'];
            $this->logger->writeLog(LogLevelEnum::Debug, __METHOD__ . "[" . __LINE__ . "]", "Recherche Terminaux par client [" . $clientId . "]", SourceEnum::BackController);
            $enrolements = $terminalService->getEnrolementsByClient($clientId, $this->pgmEnrolement,$page, $maxResult);
            $maxPage = count($enrolements);
            $terminaux = array();
            foreach ($enrolements as $enrolement) {
                $terminaux[] = $terminalService->getTerminal($enrolement['terminal']['id']);
            }
        } else {
            $error = CodeErreurEnum::nok;
            $message = "Paramètres d'appel incorrectes";
        }


        if ($terminaux) {
            /** @var Terminal $terminal */
            foreach ($terminaux as $terminal) {
                // IMEI
                $imei = $terminal->getNumeroIMEI();
                $rs = "";
                $customerId = "";
                $dateTransaction = null;
                $clientId = "";
                $ref = "";
                $orderTransactionId = "";
// Statut
                $statutLibelle = StatutImeiEnum::from($terminal->getStatut())->label();
                $statutCode = StatutImeiEnum::from($terminal->getStatut())->value;
                $order = $terminal->getOrders();
                if ($order) {
                    /** @var Orders $order */
                    $orderTransaction = $ordersService->getLastOrderTransaction($order);
                    $client = $order->getClient();
                    $rs = $client->getRaisonSociale();
                    $ref = $order->getRef();
                    $clientId = $client->getId();
                    $customerId = $order->getCustomerId();
                    $orderTransactionId = $orderTransaction->getId();
                }

                //
                $this->enrolements = $terminal->getEnrolements();
//                if ($this->enrolements->count() > 0 ) {
//                    $enrolement = $this->enrolements->last();
//                    if ($enrolement) {
//                        $client = $enrolement->getClient();
//                        $rs = $client->getRaisonSociale();
//                        $clientId = $client->getId();
//                        $customerId = $enrolement->;
//                        $this->logger->writeLog(LogLevelEnum::Debug, __METHOD__ . "[".__LINE__."]", "CustomerId :" . json_encode($customerIds) . " du client " . $rs);
//                    }
//                }
                $suivis = $terminal->getTerminalSuivis();
                if ($suivis->count() > 0) {
                    $suivi = $suivis->last();
                    $transaction = $suivi->getTransaction();
                    $dateTransaction = $transaction->getDateCreation();
                }
                $fabricant = $terminal->getFabricant()->getLibelle() ?? "";
                $resultats[] = array("imei" => $imei, "date" => $dateTransaction, "statut" => $statutCode, "statutLibelle" => $statutLibelle, "fabricant" => $fabricant, "clientId" => $clientId, "rs" => $rs, "customerId" => $customerId, "ordersId" => $orderTransactionId,"ref" => $ref);
            }
        }

        if ($message != "") $this->logger->writeLog(LogLevelEnum::Erreur, __METHOD__ . "[" . __LINE__ . "]", $message, SourceEnum::BackController);

        return $this->json([
            'error' => $error,
            'message' => $message,
            'terminaux' => $resultats,
            'maxPage' => $maxPage
        ]);

    }
}

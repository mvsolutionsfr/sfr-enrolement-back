<?php

namespace App\Controller;

use App\Entity\Client;
use App\Entity\ClientEstModifiePar;
use App\Entity\Enrolement;
use App\Entity\Enseigne;
use App\Entity\Orders;
use App\Entity\OrderTransaction;
use App\Entity\PgmClient;
use App\Entity\PgmEnrolement;
use App\Entity\Terminal;
use App\Entity\TerminalSuivi;
use App\Entity\Utilisateur;
use App\Service\AdminService;
use App\Service\AUIService;
use App\Service\ClientService;
use App\Service\EnrolementService;
use App\Service\LoggerESService;
use App\Service\SessionService;
use App\Service\TerminalService;
use App\Toolbox\CodeErreurEnum;
use App\Toolbox\LogLevelEnum;
use App\Toolbox\ProgrammeEnrolementEnum;
use App\Toolbox\SourceEnum;
use App\Toolbox\StatutEnrolementEnum;
use App\Toolbox\StatutImeiEnum;
use App\Toolbox\TypeEnrolementEnum;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use function PHPUnit\Framework\isNull;

class AdminController extends AngularController
{
    private AdminService $adminService;
    private EnrolementService $enrolementService;
    private TerminalService $terminalService;

    public function __construct(LoggerESService $logger, RequestStack $requestStack, SessionService $sessionService, AdminService $adminService, EnrolementService $enrService, TerminalService $terminalService)
    {
        parent::__construct($logger, $requestStack, $sessionService);
        $this->logger = $logger;
        $this->requestStack = $requestStack;
        $this->adminService = $adminService;
        $this->enrolementService = $enrService;
        $this->terminalService = $terminalService;
    }

    #[Route('/back/admin/json/client/orders', name: 'app_json_client_histo')]
    public function clientHistoJson(Request $request, ManagerRegistry $doctrine, ClientService $clientService): JsonResponse
    {
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", $request->getContent(), SourceEnum::ClientJson);

        $this->setSession($request);
        /** @var Utilisateur $utilisateur */
        $utilisateur = $this->sessionService->getUtilisateur();
        if ($utilisateur == null || !$utilisateur->isAdmin()) return new JsonResponse("");

        $data = json_decode($request->getContent(), true);
        $id = $data["id"] ?? "";
        $page = $data['page'] ?? 1;
        $maxResult = $data['maxResult'] ?? 10;

        $nbt = 0;
        $retour = "";
        if ($id != "") {
            /** @var Client $client */
            $client = $doctrine->getRepository(Client::class)->find($id);
            if ($client != null) {
                //                $res = $clientService->getLastOrdersTransaction($client, null);
                $res = $clientService->getLastOrdersTransactionPaginator($client, $page, $maxResult);
                $nbt = count($res);
                $retour = array();
                foreach ($res as $t) {
                    $t["statut"] = StatutEnrolementEnum::from($t["statut"])->label();
                    $t["typeTransaction"] = TypeEnrolementEnum::from($t["typeTransaction"])->name;
                    $retour[] = $t;
                }
            }
        }
        return $this->json([
            'error' => '0',
            'message' => '',
            'transactions' => $retour,
            'maxPage' => $nbt
        ]);
    }

    #[Route('/back/admin/json/user/add', name: 'app_json_user_add')]
    public function addUserJson(Request $request, ManagerRegistry $doctrine): JsonResponse
    {
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", $request->getContent(), SourceEnum::ClientJson);

        $this->setSession($request);
        /** @var Utilisateur $utilisateur */
        $utilisateur = $this->sessionService->getUtilisateur();
        if ($utilisateur == null || !$utilisateur->isAdmin()) return new JsonResponse("");

        $data = json_decode($request->getContent(), true);
        $id = $data["uid"] ?? "";
        $prenom = $data["prenom"] ?? "";
        $nom = $data["nom"] ?? "";
        $esbId = $data["esbId"] ?? "";


        $error = "";
        $message = "";

        if ($id == "") {
            $error = "1";
            $message = "Identifiant Aui ou Centric (id) absent";
        }
        if ($prenom == "") {
            $error = "1";
            $message = "Prenom (prenom)  absent";
        }
        if ($nom == "") {
            $error = "1";
            $message = "Nom (nom) absent";
        }
        if ($esbId == "") {
            $error = "1";
            $message = "Identifiant esb (esbId) absent";
        }


        if ($error != "1") {
            $enseigne = $doctrine->getRepository(Enseigne::class)->find($esbId);
            if ($enseigne) {
                $utilisateurIds[] = $id;
                $utilisateurs2[] = [$id, $prenom, $nom];
                $this->adminService->usersToAddToEnseigne($utilisateurIds, $enseigne, $utilisateurs2);
            } else {
                $error = "1";
                $message = "Enseigne " . $esbId . " introuvable";
            }
        }


        return $this->json([
            'error' => $error,
            'message' => $message
        ]);
    }

    #[Route('/back/admin/json/order/todo', name: 'app_json_orders_todo')]
    public function lastOrdersTodoJson(Request $request, ManagerRegistry $doctrine): JsonResponse
    {
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", $request->getContent(), SourceEnum::ClientJson);
        $this->setSession($request);
        /** @var Utilisateur $utilisateur */
        $utilisateur = $this->sessionService->getUtilisateur();
        if ($utilisateur == null || !$utilisateur->isAdmin()) return new JsonResponse("");

        $orders = $doctrine->getRepository(Orders::class)->findByCheckStatus();
        $resultat = array();
        /** @var Orders $order */
        foreach ($orders as $order) {
            $resultat[] = [
                'id' => $order->getId(),
                "dateCreation" => $order->getDateCreation()->format('d-M-Y H:i:s'),
                "pgm" => $order->getPgmEnrolement()->getLibelle(),
                "statut" => StatutEnrolementEnum::tryFrom($order->getDernierStatut())->label(),
                "clientId" => $order->getClient()->getId(),
                "client" => $order->getClient()->getRaisonSociale(),
                "enseigneId" => $order->getEnseigne()->getId(),
                "enseigne" => $order->getEnseigne()->getRaisonSociale(),
                "transaction" => $order->getLastTypeTransaction()
            ];
        }

        return new JsonResponse($resultat);
    }

    #[Route('/back/admin/order/todo', name: 'app_orders_todo')]
    public function lastCommandesToDo(Request $request, ManagerRegistry $doctrine): Response
    {
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", $request->getContent(), SourceEnum::ClientJson);

        $resultat = "<style> table,th,td { padding: 5px; border: 1px solid black; border-collapse: collapse; }</style>";

        $datetime = date_create('now');
        //       $this->logger->writeLog(LogLevelEnum::Debug, __METHOD__ . "[" . __LINE__ . "]", "Procède commandeByStatus", SourceEnum::BackService);

        $commandesToCheck = $doctrine->getRepository(Orders::class)->findByCheckStatus();

        $resultat .= '<table><tr><th>Id</th><th>Date</th><th>Programme</th><th>Statut</th><th>Client</th><th>Enseigne</th><th>Transaction</th></tr>';
        /** @var Orders $order */
        foreach ($commandesToCheck as $order) {
            $resultat .=
                '<tr><td><a href="../order/detail?id=' . $order->getId() . '">' . $order->getId() . '</a></td><td>' . $order->getDateCreation()->format('d-M-Y H:i:s') . '</td><td>' . $order->getPgmEnrolement()->getLibelle() . '</td><td>' . StatutEnrolementEnum::tryFrom($order->getDernierStatut())->label() . '</td><td>' . $order->getClient()->getRaisonSociale() . '</td><td><a href="../esb/detail?id=' . $order->getEnseigne()->getId() . '">' . $order->getEnseigne()->getRaisonSociale() . '</a></td><td>' . $order->getLastTypeTransaction() . '</td></tr>';
        }
        $resultat .= '</table>';

        return new Response(
            <<<EOF
            <html>
                <body>
                    $resultat;
                </body>
            </html>
            EOF
        );
    }

    #[Route('/back/admin/json/order/last', name: 'app_json_orders_last')]
    public function lastOrdersJson(Request $request, ManagerRegistry $doctrine): JsonResponse
    {
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", $request->getContent(), SourceEnum::ClientJson);
        $this->setSession($request);
        /** @var Utilisateur $utilisateur */
        $utilisateur = $this->sessionService->getUtilisateur();
        if ($utilisateur == null || !$utilisateur->isAdmin()) return new JsonResponse("");

        $orders = $doctrine->getRepository(Orders::class)->getTopOrders(100);
        $resultat = array();
        /** @var Orders $order */
        foreach ($orders as $order) {
            $resultat[] = [
                'id' => $order->getId(),
                "dateCreation" => $order->getDateCreation()->format('d-M-Y H:i:s'),
                "pgm" => $order->getPgmEnrolement()->getLibelle(),
                "statut" => StatutEnrolementEnum::tryFrom($order->getDernierStatut())->label(),
                "clientId" => $order->getClient()->getId(),
                "client" => $order->getClient()->getRaisonSociale(),
                "enseigneId" => $order->getEnseigne()->getId(),
                "enseigne" => $order->getEnseigne()->getRaisonSociale(),
                "transaction" => $order->getLastTypeTransaction()
            ];
        }

        return new JsonResponse($resultat);
    }

    #[Route('/back/admin/order/last', name: 'app_orders_last')]
    public function lastOrdersHtml(Request $request, ManagerRegistry $doctrine): Response
    {
        $resultat = "<style> table,th,td { padding: 5px; border: 1px solid black; border-collapse: collapse; }</style>";
        $orders = $doctrine->getRepository(Orders::class)->getTopOrders(100);
        $resultat .= '<table><tr><th>Id</th><th>Date</th><th>Programme</th><th>Statut</th><th>Client</th><th>Enseigne</th><th>Transaction</th></tr>';
        /** @var Orders $order */
        foreach ($orders as $order) {
            $resultat .=
                '<tr><td><a href="../order/detail?id=' . $order->getId() . '">' . $order->getId() . '</a></td><td>' . $order->getDateCreation()->format('d-M-Y H:i:s') . '</td><td>' . $order->getPgmEnrolement()->getLibelle() . '</td><td>' . StatutEnrolementEnum::tryFrom($order->getDernierStatut())->label() . '</td><td>' . $order->getClient()->getRaisonSociale() . '</td><td><a href="../esb/detail?id=' . $order->getEnseigne()->getId() . '">' . $order->getEnseigne()->getRaisonSociale() . '</a></td><td>' . $order->getLastTypeTransaction() . '</td></tr>';
        }
        $resultat .= '</table>';

        return new Response(
            <<<EOF
            <html>
                <body>
                    $resultat;
                </body>
            </html>
            EOF
        );
    }

    #[Route('/back/admin/json/client/detail', name: 'app_json_client_detail')]
    public function clientDetailJson(Request $request, ManagerRegistry $doctrine): Response
    {
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", $request->getContent(), SourceEnum::ClientJson);
        $this->setSession($request);
        /** @var Utilisateur $utilisateur */
        $utilisateur = $this->sessionService->getUtilisateur();
        if ($utilisateur == null || !$utilisateur->isAdmin()) return new JsonResponse("");

        $data = json_decode($request->getContent(), true);
        $id = $data["id"] ?? "";

        $resultat = "";
        if ($id != "") {
            /** @var Client $client */
            $client = $doctrine->getRepository(Client::class)->find($id);
            if ($client != null) {
                $resultat = [
                    'id' => $id,
                    'raisonSociale' => $client->getRaisonSociale(),
                    'siren' => $client->getSiren()
                ];

                $pgmenrolement = $client->getPgmEnrolements();
                /** @var PgmClient $pgmClient */
                foreach ($pgmenrolement as $pgmClient) {
                    $resultat['pgm'][] = ['pgm' => $pgmClient->getPgmEnrolement()->getLibelle(), 'customerId' => $pgmClient->getCustomerId()];
                }

                $orders = $client->getOrders();
                /** @var Orders $order */
                foreach ($orders as $order) {
                    $resultat['orders'][] = [
                        'id' => $order->getId(),
                        'date' => $order->getDateCreation()->format('d-M-Y H:i:s'),
                        'pgm' => $order->getPgmEnrolement()->getLibelle(),
                        'statut' => StatutEnrolementEnum::tryFrom($order->getDernierStatut())->label(),
                        'esbId' => $order->getEnseigne()->getId(),
                        'esb' => $order->getEnseigne()->getRaisonSociale(),
                        'typeTransac' => $order->getLastTypeTransaction()
                    ];
                }
            }
        }
        return new JsonResponse($resultat);
    }

    #[Route('/back/admin/client/detail', name: 'app_client_detail')]
    public function clientDetail(Request $request, ManagerRegistry $doctrine): Response
    {
        $resultat = "<style> table,th,td { padding: 5px; border: 1px solid black; border-collapse: collapse; }</style>";

        $id = $request->query->get('id');
        if ($id == null) {
            $resultat = <<<EOF
            <form id="frm1">
              client id: <input type="text" name="id" value=""><br>
            </form> 
            <button onclick="myFunction()">Recherche</button>
            <script>
            function myFunction() {
              var x = document.getElementById("frm1");
              location.replace(window.location.href+"?id="+x.elements[0].value);
            }
            </script>
            EOF;
        } else {
            /** @var Client $client */
            $client = $doctrine->getRepository(Client::class)->find($id);
            if ($client != null) {
                $resultat .= '<h3>Client Id : ' . $id . '</h3>';
                $resultat .= '<h3>Raison sociale : ' . $client->getRaisonSociale() . '</h3>';
                $resultat .= '<h3>Siren : ' . $client->getSiren() . '</h3>';
                $resultat .= '<h3>Programmes d\'inscription: </h3>';

                $resultat .= '<h3>Modifié par: </h3>';
                $modifiePars = $client->getClientEstModifiePar();
                /** @var PgmClient $pgmClient */
                $resultat .= '<table><tr><th>Nom</th><th>Prenom</th><th>Date modif</th></tr>';
                /** @var ClientEstModifiePar $modifiePar */
                foreach ($modifiePars as $modifiePar) {
                    $resultat .= '<tr><td>' . $modifiePar->getUtilisateur()->getNom() . '</td><td>' . $modifiePar->getUtilisateur()->getPrenom() . '</td><td>' . $modifiePar->getDate()->format('d-M-Y H:i:s') . '</td></tr>';
                }
                $resultat .= '</table>';

                $resultat .= '<h3>Programmes d\'inscription: </h3>';
                $pgmenrolement = $client->getPgmEnrolements();
                /** @var PgmClient $pgmClient */
                $resultat .= '<table><tr><th>Programme</th><th>Customer Id</th></tr>';
                foreach ($pgmenrolement as $pgmClient) {
                    $resultat .= '<tr><td>' . $pgmClient->getPgmEnrolement()->getLibelle() . '</td><td>' . $pgmClient->getCustomerId() . '</td></tr>';
                }
                $resultat .= '</table>';


                $resultat .= '<h3>Commandes d\'inscription: </h3>';
                $orders = $client->getOrders();
                $resultat .= '<table><tr><th>Id</th><th>Date</th><th>Programme</th><th>Statut</th><th>Enseigne</th><th>Transaction</th></tr>';
                /** @var Orders $order */
                foreach ($orders as $order) {
                    $resultat .=
                        '<tr><td><a href="../order/detail?id=' . $order->getId() . '">' . $order->getId() . '</a></td><td>' . $order->getDateCreation()->format('d-M-Y H:i:s') . '</td><td>' . $order->getPgmEnrolement()->getLibelle() . '</td><td>' . StatutEnrolementEnum::tryFrom($order->getDernierStatut())->label() . '</td><td><a href="../esb/detail?id=' . $order->getEnseigne()->getId() . '">' . $order->getEnseigne()->getRaisonSociale() . '</a></td><td>' . $order->getLastTypeTransaction() . '</td></tr>';
                }
                $resultat .= '</table>';

                //$utilisateur = $client->
                //                /** @var Enrolement $enrolement */
                //                $resultat .= '<h3>Clients</h3><table>';
                //
                //                $resultat .= '<tr><th>Raison sociale</th><th>Date enrolement</th><th>Date desenrolement</th></tr>';
                //                foreach ($enrolements as $enrolement) {
                //                    $datefin = '';
                //                    if ($enrolement->getDateFin() != null) $datefin = $enrolement->getDateFin()->format('d-M-Y H:i:s');
                //                    $resultat .= '<tr></tr><td>' . $enrolement->getClient()->getRaisonSociale() . '</td><td>' . $enrolement->getDate()->format('d-m-Y H:i:s') . '</td><td>' . $datefin . '</td></tr>';
                //                }
                //
                //                $resultat .= '</table>';
                //                $resultat .= '<h3>Commandes</h3><table>';
                //                $suivis = $terminal->getTerminalSuivis();
                //                $resultat .= '<tr><th>Id Commande</th></tr>';
                //
                //                /** @var TerminalSuivi $suivi */
                //                $ordersCollection = new ArrayCollection();
                //                foreach ($suivis as $suivi) {
                //                    $orderId = $suivi->getTransaction()->getOrders()->getId();
                //                    if (!$ordersCollection->contains($orderId)) {
                //                        $ordersCollection->add($orderId);
                //                        $datefin = '';
                //                        $resultat .= '<tr><td><a href="../order/detail?id=' . $orderId . '">' . $orderId . '</a></td></tr>';
                //                    }
                //                }
                //                $resultat .= '</table>';

            } else {
                $resultat = '<h1>Client introuvable!</h1>';
            }
        }
        return new Response(
            <<<EOF
            <html>
                <body>
                    $resultat;
                </body>
            </html>
            EOF
        );
    }

    #[Route('/back/admin/json/terminal/detail', name: 'app_json_terminal_detail')]
    public function terminalDetailJson(Request $request, ManagerRegistry $doctrine): Response
    {
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", $request->getContent(), SourceEnum::ClientJson);
        $this->setSession($request);
        /** @var Utilisateur $utilisateur */
        $utilisateur = $this->sessionService->getUtilisateur();
        if ($utilisateur == null || !$utilisateur->isAdmin()) return new JsonResponse("");

        $data = json_decode($request->getContent(), true);
        $id = $data["id"] ?? "";

        $resultat = "";
        if ($id != "") {
            /** @var Terminal $terminal */
            $terminal = $doctrine->getRepository(Terminal::class)->findByIMEIOrSerie($id);
            if ($terminal != null) {
                $resultat = [
                    "imei" => $terminal->getNumeroIMEI(),
                    "serie" => $terminal->getNumeroSerie(),
                    "programme" => ProgrammeEnrolementEnum::tryFrom($terminal->getProgramme()->getId())->name,
                    "statut" => StatutImeiEnum::tryFrom($terminal->getStatut())->label(),
                    "enrolements" => array(),
                    "commandes" => array()
                ];
                $enrolements = $terminal->getEnrolements();
                /** @var Enrolement $enrolement */
                foreach ($enrolements as $enrolement) {
                    $datefin = "";
                    if ($enrolement->getDateFin() != null) $datefin = $enrolement->getDateFin()->format('d-M-Y H:i:s');
                    $resultat["enrolements"][] = [
                        "id" => $enrolement->getClient()->getId(),
                        "raisonSociale" => $enrolement->getClient()->getRaisonSociale(),
                        "dateDebut" => $enrolement->getDate()->format('d-m-Y H:i:s'),
                        "dateFin" => $datefin
                    ];
                }

                $suivis = $terminal->getTerminalSuivis();
                /** @var TerminalSuivi $suivi */
                $ordersCollection = new ArrayCollection();
                foreach ($suivis as $suivi) {
                    $orderId = $suivi->getTransaction()->getOrders()->getId();
                    if (!$ordersCollection->contains($orderId)) {
                        $ordersCollection->add($orderId);
                        $resultat['commandes'][] = ["id" => $orderId];
                    }
                }
            }
        }
        return new JsonResponse($resultat);
    }

    #[Route('/back/admin/terminal/libere', name: 'app_terminal_libere')]
    public function terminalLibere(Request $request, ManagerRegistry $doctrine): Response
    {
        $resultat = "<style> table,th,td { padding: 5px; border: 1px solid black; border-collapse: collapse; }</style>";
        $id = $request->query->get('id');
        $tid = $request->query->get('tid');
        if ($id == null && $tid == null) {
            $resultat = <<<EOF
            <form id="frm1" action="/action_page.php">
              imei à libérer: <input type="text" name="id" value=""><br>
            </form> 
            <button onclick="myFunction()">Libérer</button>
            <script>
            function myFunction() {
              var x = document.getElementById("frm1");
              location.replace(window.location.href+"?id="+x.elements[0].value);
            }
            </script>
            EOF;
        } else {
            /** @var Terminal $terminal */
            if ($tid != null) {
                $terminal = $doctrine->getRepository(Terminal::class)->find($tid);
            } else {
                $terminal = $doctrine->getRepository(Terminal::class)->findByIMEIOrSerie($id);
            }
            if ($terminal != null) {
                $this->terminalService->libereTerminal($terminal);
                $resultat = 'Terminal <a href="../terminal/detail?tid=' . $terminal->getId() . '">' . $terminal->getId() . '</a>' . " (" . $terminal->getNumeroIMEI() . ") libéré";
            } else {
                $resultat = "Terminal introuvable";
            }
        }
        return new Response(
            <<<EOF
            <html>
                <body>
                    $resultat
                </body>
            </html>
            EOF
        );
    }

    #[Route('/back/admin/terminal/detail', name: 'app_terminal_detail')]
    public function terminalDetail(Request $request, ManagerRegistry $doctrine): Response
    {
        $resultat = "<style> table,th,td { padding: 5px; border: 1px solid black; border-collapse: collapse; }</style>";
        $id = $request->query->get('id');
        $tid = $request->query->get('tid');
        if ($id == null && $tid == null) {
            $resultat = <<<EOF
            <form id="frm1" action="/action_page.php">
              imei: <input type="text" name="id" value=""><br>
            </form> 
            <button onclick="myFunction()">Recherche</button>
            <script>
            function myFunction() {
              var x = document.getElementById("frm1");
              location.replace(window.location.href+"?id="+x.elements[0].value);
            }
            </script>
            EOF;
        } else {

            /** @var Terminal $terminal */
            if ($tid != null) {
                $terminal = $doctrine->getRepository(Terminal::class)->find($tid);
            } else {
                $terminal = $doctrine->getRepository(Terminal::class)->findByIMEIOrSerie($id);
            }
            if ($terminal != null) {
                //                $transactions = $doctrine->getRepository(OrderTransaction::class)->findByOrderSortByDate($terminal);
                //                $transactions = $terminal->getTransactions();
                $resultat .= '<h3>Id : ' . $terminal->getId() . '</h3>';
                $resultat .= '<h3>IMEI : <p id="imei">' . $terminal->getNumeroIMEI() . '</p></h3>';
                $resultat .= '<h3>SN : ' . $terminal->getNumeroSerie() . '</h3>';
                $resultat .= '<h3>Programme : ' . ProgrammeEnrolementEnum::tryFrom($terminal->getProgramme()->getId())->name . '</h3>';
                $resultat .= '<h3>Statut : ' . StatutImeiEnum::tryFrom($terminal->getStatut())->label() . '</h3>';
                $enrolements = $terminal->getEnrolements();
                /** @var Enrolement $enrolement */
                $resultat .= '<h3>Clients</h3><table>';

                $resultat .= '<tr><th>Raison sociale</th><th>Date enrolement</th><th>Date desenrolement</th></tr>';
                foreach ($enrolements as $enrolement) {
                    $datefin = '';
                    if ($enrolement->getDateFin() != null) $datefin = $enrolement->getDateFin()->format('d-M-Y H:i:s');
                    $resultat .= '<tr></tr><td>' . $enrolement->getClient()->getRaisonSociale() . '</td><td>' . $enrolement->getDate()->format('d-m-Y H:i:s') . '</td><td>' . $datefin . '</td></tr>';
                }

                if ($terminal->getStatut() == StatutImeiEnum::Enrole->value) {
                    $resultat .= <<<EOF
                        <button onclick="LiberationFunction()">Forcage libération</button>
                        <script>
                        function LiberationFunction() {
                          var x = document.getElementById("imei");
                          location.replace("../terminal/libere?id="+x.value);
                        }
                        </script>
                        EOF;
                }

                $resultat .= '</table>';

                $resultat .= '<h3>Commandes</h3><table>';

                $suivis = $terminal->getTerminalSuivis();

                $resultat .= '<tr><th>Id Commande</th></tr>';
                /** @var TerminalSuivi $suivi */
                $ordersCollection = new ArrayCollection();
                foreach ($suivis as $suivi) {
                    $orderId = $suivi->getTransaction()->getOrders()->getId();
                    if (!$ordersCollection->contains($orderId)) {
                        $ordersCollection->add($orderId);
                        $datefin = '';
                        $resultat .= '<tr><td><a href="../order/detail?id=' . $orderId . '">' . $orderId . '</a></td></tr>';
                    }
                }
                $resultat .= '</table>';
            } else {
                $resultat = '<h1>IMEI introuvable!</h1>';
            }
        }
        return new Response(
            <<<EOF
            <html>
                <body>
                    $resultat
                </body>
            </html>
            EOF
        );
    }

    #[Route('/back/admin/json/user/list', name: 'app_json_user_list')]
    public function userListJson(Request $request, ManagerRegistry $doctrine): Response
    {
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", $request->getContent(), SourceEnum::ClientJson);
        $this->setSession($request);
        /** @var Utilisateur $utilisateur */
        $utilisateur = $this->sessionService->getUtilisateur();
        if ($utilisateur == null || !$utilisateur->isAdmin()) return new JsonResponse("");

        $resultat = array();

        /** @var Enseigne $esb */
        $users = $doctrine->getRepository(Utilisateur::class)->findAll();
        foreach ($users as $utilisateur) {
            $resultat[] = [
                "id" => $utilisateur->getId(),
                "aui" => $utilisateur->getIdAui(),
                "prenom" => $utilisateur->getPrenom(),
                "nom" => $utilisateur->getNom(),
                "esbId" => $utilisateur->getEnseigne()->getId(),
                "esv" => $utilisateur->getEnseigne()->getRaisonSociale()
            ];
        }

        return new JsonResponse($resultat);
    }

    #[Route('/back/admin/user/list', name: 'app_user_list')]
    public function userList(Request $request, ManagerRegistry $doctrine): Response
    {
        $resultat = "<style> table,th,td { padding: 5px; border: 1px solid black; border-collapse: collapse; }</style>";
        /** @var Enseigne $esb */
        $users = $doctrine->getRepository(Utilisateur::class)->findAll();
        $resultat .= '<h3>List Utilisateurs</h3>';

        /** @var Utilisateur $utilisateur */
        $resultat .= '<table><tr><th>Id</th><th>id Aui</th><th>Prenom Nom</th><th>Enseigne</th></tr>';
        foreach ($users as $utilisateur) {
            $resultat .= '<tr><td>' . $utilisateur->getId() . '</td><td>' . $utilisateur->getIdAui() . '</td><td>' . $utilisateur->getPrenom() . ' ' . $utilisateur->getNom() . '</td><td><a href="../esb/detail?id=' . $utilisateur->getEnseigne()->getId() . '">' . $utilisateur->getEnseigne()->getRaisonSociale() . '</a></td></tr>';
        }
        $resultat .= '</table>';

        return new Response(
            <<<EOF
            <html>
                <body>
                    $resultat;
                </body>
            </html>
            EOF
        );
    }

    #[Route('/back/admin/json/esb/list', name: 'app_json_esb_list')]
    public function esbListJson(Request $request, ManagerRegistry $doctrine): Response
    {
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", $request->getContent(), SourceEnum::ClientJson);
        $this->setSession($request);
        /** @var Utilisateur $utilisateur */
        $utilisateur = $this->sessionService->getUtilisateur();
        if ($utilisateur == null || !$utilisateur->isAdmin()) return new JsonResponse("");

        $resultat = array();
        /** @var Enseigne $esb */
        $esbs = $doctrine->getRepository(Enseigne::class)->findAll();
        /** @var Enseigne $esb */
        foreach ($esbs as $esb) {
            $resultat[] = [
                'id' => $esb->getId(),
                "raisonSociale" => $esb->getRaisonSociale(),
            ];
        }

        return new JsonResponse($resultat);
    }

    #[Route('/back/admin/esb/list', name: 'app_esb_list')]
    public function esbList(Request $request, ManagerRegistry $doctrine): Response
    {
        /** @var Enseigne $esb */
        $esbs = $doctrine->getRepository(Enseigne::class)->findAll();
        $resultat = '<h3>List Enseignes</h3>';

        /** @var Enseigne $esb */
        $resultat .= '<table><tr><th>Id</th><th>Enseigne</th></tr>';
        foreach ($esbs as $esb) {
            $resultat .= '<tr><td>' . $esb->getId() . '</td><td><a href="../esb/detail?id=' . $esb->getId() . '">' . $esb->getRaisonSociale() . "</a></td></tr>";
        }
        $resultat .= '</table>';

        return new Response(
            <<<EOF
            <html>
                <body>
                    $resultat;
                </body>
            </html>
            EOF
        );
    }

    #[Route('/back/admin/json/esb/detail', name: 'app_json_esb_detail')]
    public function esbDetailJson(Request $request, ManagerRegistry $doctrine): Response
    {
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", $request->getContent(), SourceEnum::ClientJson);
        $this->setSession($request);
        /** @var Utilisateur $utilisateur */
        $utilisateur = $this->sessionService->getUtilisateur();
        if ($utilisateur == null || !$utilisateur->isAdmin()) return new JsonResponse("");

        $data = json_decode($request->getContent(), true);
        $id = $data["id"] ?? "";

        $resultat = "";
        if ($id != "") {
            /** @var Enseigne $esb */
            $esb = $doctrine->getRepository(Enseigne::class)->find($id);

            /** @var Orders $order */
            if ($esb != null) {
                $resultat = ["id" => $esb->getId(), "raisonSociale" => $esb->getRaisonSociale(), "programmes" => array(), "utilisateurs" => array(), "util_mod" => array()];
                foreach ($esb->getPgmEnrolements() as $pgm) {
                    $resultat["programmes"][] = ["libelle" => $pgm->getPgmEnrolement()->getLibelle(), "resellerId" => $pgm->getResellerId()];
                }
                /** @var Utilisateur $utilisateur */
                foreach ($esb->getUtilisateurs() as $utilisateur) {
                    $resultat["utilisateurs"][] = ["id" => $utilisateur->getId(), "idAui" => $utilisateur->getIdAui(), "prenom" => $utilisateur->getPrenom(), "nom" => $utilisateur->getNom()];
                }
                $resultat["util_mod"][] = array();
                //                foreach ($esb->getEnseigneEstModifiePars() as $utilisateur) {
                //                    $resultat["util_mod"][]  = [ "id" => $utilisateur->getId() , "idAui" => $utilisateur->getIdAui(), "prenom" => $utilisateur->getPrenom(), "nom" => $utilisateur->getNom() ];
                //                }
            }
        }
        return new JsonResponse($resultat);
    }

    #[Route('/back/admin/esb/detail', name: 'app_esb_detail')]
    public function esbDetail(Request $request, ManagerRegistry $doctrine): Response
    {
        $resultat = "<style> table,th,td { padding: 5px; border: 1px solid black; border-collapse: collapse; }</style>";
        $id = $request->query->get('id');
        if ($id == null) {
            $resultat = <<<EOF
            <form id="frm1" action="/action_page.php">
              Esb id: <input type="text" name="id" value=""><br>
            </form> 
            <button onclick="myFunction()">Recherche</button>
            <script>
            function myFunction() {
              var x = document.getElementById("frm1");
              location.replace(window.location.href+"?id="+x.elements[0].value);
            }
            </script>
            EOF;
        } else {
            /** @var Enseigne $esb */
            $esb = $doctrine->getRepository(Enseigne::class)->find($id);


            /** @var Orders $order */
            if ($esb != null) {
                $resultat .= '<h3>Enseigne : ' . $id . '</h3>';
                $resultat .= '<h3>Raison sociale : ' . $esb->getRaisonSociale() . '</a></h3>';
                $resultat .= '<h3>Programmes :</h3>';
                $resultat .= '<table><tr><th>Programme</th><th>ResellerId</th></tr>';
                /** @var PgmEnseigne $pgm */
                foreach ($esb->getPgmEnrolements() as $pgm) {
                    $resultat .= '<tr><td>' . $pgm->getPgmEnrolement()->getLibelle() . '</td><td>' . $pgm->getResellerId() . '</td></tr>';
                }
                $resultat .= '</table>';

                $resultat .= '<h3>Utilisateurs :</h3>';

                $resultat .= '<table><tr><th>Id</th><th>Id AUI</th><th>Prenom Nom</th></tr>';
                /** @var Utilisateur $utilisateur */
                foreach ($esb->getUtilisateurs() as $utilisateur) {
                    $resultat .= '<tr><td>' . $utilisateur->getId() . '</td><td>' . $utilisateur->getIdAui() . '</td><td>' . $utilisateur->getPrenom() . ' ' . $utilisateur->getNom() . "</td></tr>";
                }
                $resultat .= '</table>';

                $resultat .= '<h3>Modification Enseigne par utilisateurs :</h3>';

                $resultat .= '<table><tr><th>Id</th><th>Id AUI</th><th>Prenom Nom</th></tr>';
                /** @var modifiePar EnseigneEstModifiePar */
                foreach ($esb->getEnseigneEstModifiePars() as $modifiePar) {
                    $utilisateur = $modifiePar->getUtilisateur();
                    $resultat .= '<tr><td>' . $utilisateur->getId() . '</td><td>' . $utilisateur->getIdAui() . '</td><td>' . $utilisateur->getPrenom() . ' ' . $utilisateur->getNom() . "</td></tr>";
                }
                $resultat .= '</table>';
            } else {
                $resultat = '<h1>Commande introuvable!</h1>';
            }
        }
        return new Response(
            <<<EOF
            <html>
                <body>
                    $resultat;
                </body>
            </html>
            EOF
        );
    }

    #[Route('/back/admin/json/order/detail', name: 'app_json_order_detail')]
    public function orderDetailJson(Request $request, ManagerRegistry $doctrine): Response
    {
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", $request->getContent(), SourceEnum::ClientJson);
        $resultat = "";

        $this->setSession($request);
        /** @var Utilisateur $utilisateur */
        $utilisateur = $this->sessionService->getUtilisateur();
        if ($utilisateur == null || !$utilisateur->isAdmin()) return new JsonResponse("");

        $data = json_decode($request->getContent(), true);
        $id = $data["id"] ?? "";

        if ($id != "") {
            /** @var Orders $order */
            $order = $doctrine->getRepository(Orders::class)->find($id);
            if ($order != null) {
                $resultat = [
                    "id" => $id,
                    "clientId" => $order->getClient()->getId(),
                    "client" => $order->getClient()->getRaisonSociale(),
                    "utilisateur" => $order->getEstCreePar()->getIdAui() . ' - ' . $order->getEstCreePar()->getPrenom() . ' ' . $order->getEstCreePar()->getNom(),
                    "programme" => ProgrammeEnrolementEnum::tryFrom($order->getPgmEnrolement()->getId())->name,
                    "esbId" => $order->getEnseigne()->getId(),
                    "esb" => $order->getEnseigne()->getRaisonSociale(),
                    "statut" => StatutEnrolementEnum::tryFrom($order->getDernierStatut())->label(),
                    "transactions" => array()
                ];

                /** @var Orders $order */
                $transactions = $doctrine->getRepository(OrderTransaction::class)->findByOrderSortByDate($order);
                foreach ($transactions as $transaction) {
                    /** @var OrderTransaction $transaction */
                    $transaction = $doctrine->getRepository(OrderTransaction::class)->find($transaction->getId());
                    if ($transaction) {

                        $terminalSuivis = $transaction->getTerminalSuivis();
                        $suivi = array();
                        foreach ($terminalSuivis as $terminalSuivi) {
                            $terminal = $terminalSuivi->getTerminal();
                            $suivi[] = [
                                "imei" => $terminal->getNumeroIMEI(),
                                "serie" => $terminal->getNumeroSerie(),
                                "statut" => StatutImeiEnum::tryFrom($terminal->getStatut())->label(),
                                "message" => $terminalSuivi->getMessage()
                            ];
                        }

                        $resultat['transactions'][] = [
                            "id" => $transaction->getId(),
                            "type" => TypeEnrolementEnum::tryFrom($transaction->getTypeTransaction())->name,
                            "statut" => StatutEnrolementEnum::tryFrom($transaction->getStatut())->label(),
                            "date" => $transaction->getDateCreation()->format('d-M-Y H:i:s'),
                            "message" => $transaction->getStatutMsgPgm(),
                            "suivis" => $suivi
                        ];
                    }
                }
            }
        }
        return new JsonResponse($resultat);
    }


    #[Route('/back/admin/order/detail', name: 'app_order_detail')]
    public function orderDetail(Request $request, ManagerRegistry $doctrine): Response
    {
        $resultat = "<style> table,th,td { padding: 5px; border: 1px solid black; border-collapse: collapse; }</style>";
        $id = $request->query->get('id');
        if ($id == null) {
            $resultat = <<<EOF
            <form id="frm1" action="/action_page.php">
              Commande id: <input type="text" name="id" value=""><br>
            </form> 
            <button onclick="myFunction()">Recherche</button>
            <script>
            function myFunction() {
              var x = document.getElementById("frm1");
              location.replace(window.location.href+"?id="+x.elements[0].value);
            }
            </script>
            EOF;
        } else {
            /** @var Orders $order */
            $order = $doctrine->getRepository(Orders::class)->find($id);

            $resultat .= '<h3>Commande : ' . $id . '</h3>';
            $resultat .= '<h3>Client : <a href="../client/detail?id=' . $order->getClient()->getId() . '">' . $order->getClient()->getRaisonSociale() . '</a></h3>';
            $resultat .= '<h3>CustomerId : ' . $order->getCustomerId() . '</h3>';
            $resultat .= '<h3>ResellerId : ' . $order->getResellerId() . '</h3>';
            $resultat .= '<h3>Fabricant : ' . $order->getFabricant()->getLibelle() . '</h3>';
            $resultat .= '<h3>Ref : ' . $order->getRef() . '</h3>';
            $resultat .= '<h3>Utilisateur : ' . $order->getEstCreePar()->getIdAui() . ' - ' . $order->getEstCreePar()->getPrenom() . ' ' . $order->getEstCreePar()->getNom() . '</h3>';
            $resultat .= '<h3>Programme : ' . ProgrammeEnrolementEnum::tryFrom($order->getPgmEnrolement()->getId())->name . '</h3>';
            $resultat .= '<h3>Enseigne / ESB : ' . '<a href="../esb/detail?id=' . $order->getEnseigne()->getId() . '">' . $order->getEnseigne()->getRaisonSociale() . '</a></h3>';
            $resultat .= '<h3>Statut : ' . StatutEnrolementEnum::tryFrom($order->getDernierStatut())->label() . '</h3>';

            /** @var Orders $order */
            if ($order != null) {
                $transactions = $doctrine->getRepository(OrderTransaction::class)->findByOrderSortByDate($order);
                //                $transactions = $order->getTransactions();
                $resultat .= '<p><ul>';
                foreach ($transactions as $transaction) {
                    /** @var OrderTransaction $transaction */
                    $transaction = $doctrine->getRepository(OrderTransaction::class)->find($transaction->getId());
                    if ($transaction) {
                        $resultat .= '<li>Transaction: ' . $transaction->getId() . " [" . TypeEnrolementEnum::tryFrom($transaction->getTypeTransaction())->name . "][" . StatutEnrolementEnum::tryFrom($transaction->getStatut())->label() . "] </br>Date : " . $transaction->getDateCreation()->format('d-M-Y H:i:s') . "</br>Utilisateur : " . $transaction->getEstCreePar()->getIdAui() . ' - ' . $transaction->getEstCreePar()->getPrenom() . ' ' . $order->getEstCreePar()->getNom() . "</br>Message : " . $transaction->getStatutMsgPgm() . "</li>";
                        $terminalSuivis = $transaction->getTerminalSuivis();
                        $resultat .= '<p><table><tr><th>Id</th><th>Imei</th><th>Serie</th><th>Statut</th><th>Message</th></tr>';
                        foreach ($terminalSuivis as $terminalSuivi) {
                            $terminal = $terminalSuivi->getTerminal();
                            $resultat .= '<tr><td><a href="../terminal/detail?tid=' . $terminal->getId() . '">' . $terminal->getId() . '</a></td><td><a href="../terminal/detail?id=' . $terminal->getNumeroIMEI() . '">' . $terminal->getNumeroIMEI() . "</a></td><td>" . $terminal->getNumeroSerie() . "</td><td>" . StatutImeiEnum::tryFrom($terminal->getStatut())->label() . "</td><td>" . $terminalSuivi->getMessage() . "</td></tr>";
                        }
                        $resultat .= '</table></p>';
                    }
                }
                $resultat .= '</ul></p>';
            } else {
                $resultat = '<h1>Commande introuvable!</h1>';
            }
        }
        return new Response(
            <<<EOF
            <html>
                <body>
                    $resultat;
                </body>
            </html>
            EOF
        );
    }


    #[Route('/back/admin/enseigne/create', name: 'app_enseigne_create')]
    public function createEnseigne(Request $request, AUIService $auiService, AdminService $adminService): JsonResponse
    {
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", $request->getContent(), SourceEnum::ClientJson);
        $this->setSession($request);
        $pgmEnrolement = $this->sessionService->getProgramme();   // Pgm d'enrolement en cours
        $utilisateur = $this->sessionService->getUtilisateur();   // Pgm d'enrolement en cours

        $error = CodeErreurEnum::ok;
        $message = "";
        $data = json_decode($request->getContent(), true);

        $rs = "";
        $resellerId = "";
        $enseigne = false;
        if (isset($data['rs'])) $rs = $data['rs'];
        else $message = "Raison sociale obligatoire";
        if (isset($data['resellerid'])) $resellerId = $data['resellerid'];
        else $message = "resellerid obligatoire";
        $utilisateurs = $data['utilisateurs'] ?? array();

        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "création enseigne rs [" . $rs . "] customerId [" . $resellerId . "]", SourceEnum::BackController);

        if ($message !== '')
            $error = CodeErreurEnum::nok;
        else {
            try {
                $enseigne = $adminService->createEnseigne($rs, $utilisateur, $utilisateurs);
                if ($resellerId !== '') $adminService->setResellerId($enseigne, $pgmEnrolement, $resellerId);
            } catch (\Exception $exception) {
                $error = CodeErreurEnum::ex;
                $message = $exception->getMessage();
            }
        }
        $enseigneId = "";
        if ($enseigne) $enseigneId = $enseigne->getId();
        return $this->json([
            'error' => $error,
            'message' => $message,
            'enseigneid' => $enseigneId
        ]);
    }

    #[Route('/back/admin/enseigne/save', name: 'app_enseigne_save')]
    public function saveEnseigne(Request $request, AUIService $auiService, AdminService $adminService): JsonResponse
    {
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", $request->getContent(), SourceEnum::ClientJson);
        $this->setSession($request);
        $pgmEnrolement = $this->sessionService->getProgramme();   // Pgm d'enrolement en cours
        $utilisateur = $this->sessionService->getUtilisateur();   // Pgm d'enrolement en cours

        $error = CodeErreurEnum::ok;
        $message = "";
        $data = json_decode($request->getContent(), true);

        $rs = "";
        $resellerId = "";
        $enseigne = false;
        if (isset($data['rs'])) $rs = $data['rs'];
        else $message = "Raison sociale obligatoire";
        if (isset($data['enseigneid'])) $enseigneId = $data['enseigneid'];
        else $message = "Identifiant d'enseigne obligatoire";
        if (isset($data['resellerid'])) $resellerId = $data['resellerid'];
        else $message = "resellerid obligatoire";
        $utilisateurs = $data['utilisateurs'] ?? array();

        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "création enseigne rs [" . $rs . "] customerId [" . $resellerId . "]", SourceEnum::BackController);

        if ($message !== '')
            $error = CodeErreurEnum::nok;
        else {
            try {
                $enseigne = $adminService->saveEnseigne($rs, $utilisateur, $enseigneId, $utilisateurs);
                if ($resellerId !== '') $adminService->setResellerId($enseigne, $pgmEnrolement, $resellerId);
            } catch (\Exception $exception) {
                $error = CodeErreurEnum::ex;
                $message = $exception->getMessage();
            }
        }
        $enseigneId = "";
        if ($enseigne) $enseigneId = $enseigne->getId();
        return $this->json([
            'error' => $error,
            'message' => $message,
            'enseigneid' => $enseigneId
        ]);
    }

    #[Route('/back/admin/enseigne/get', name: 'app_enseigne_get')]
    public function getEnseigne(Request $request, AUIService $auiService, AdminService $adminService): JsonResponse
    {
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", $request->getContent(), SourceEnum::ClientJson);
        $this->setSession($request);
        $pgmEnrolement = $this->sessionService->getProgramme();   // Pgm d'enrolement en cours
        $utilisateur = $this->sessionService->getUtilisateur();   // Pgm d'enrolement en cours

        $error = CodeErreurEnum::ok;
        $message = "";
        $data = json_decode($request->getContent(), true);

        $rs = "";
        $resellerId = "";
        $enseigneArray = array();
        if (isset($data['enseigneid'])) $enseigneId = $data['enseigneid'];
        else $message = "Identifiant d'enseigne obligatoire";

        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "Lecture enseigne rs [" . $rs . "] customerId [" . $resellerId . "]", SourceEnum::BackController);

        if ($message !== '')
            $error = CodeErreurEnum::nok;
        else {
            try {
                $enseigneArray = $adminService->getEnseigne($enseigneId);
            } catch (\Exception $exception) {
                $error = CodeErreurEnum::ex;
                $message = $exception->getMessage();
            }
        }
        return $this->json([
            'error' => $error,
            'message' => $message,
            'enseigne' => $enseigneArray
        ]);
    }

    #[Route('/back/admin/enseigne/recherche', name: 'app_enseigne_recherche')]
    public function rechercheEnseignes(Request $request, AUIService $auiService, AdminService $adminService): JsonResponse
    {
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", $request->getContent(), SourceEnum::ClientJson);
        $this->setSession($request);
        $pgmEnrolement = $this->sessionService->getProgramme();   // Pgm d'enrolement en cours
        $utilisateur = $this->sessionService->getUtilisateur();   // Pgm d'enrolement en cours

        $error = CodeErreurEnum::ok;
        $message = "";
        $data = json_decode($request->getContent(), true);

        $critere = "";
        if (isset($data['critere'])) $critere = $data['critere'];
        else $message = "critere de recherche d'enseigne obligatoire";

        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "recherche enseignes critere [" . $critere . "]", SourceEnum::BackController);

        $enseignesArray = array();
        if ($message !== '')
            $error = CodeErreurEnum::nok;
        else {
            //           try {
            $enseignesArray = $adminService->rechercheEnseignes($critere, $pgmEnrolement);
            //            } catch (\Exception $exception) {
            //                $error = CodeErreurEnum::ex;
            //                $message = $exception->getMessage();
            //            }
        }
        return $this->json([
            'error' => $error,
            'message' => $message,
            'enseigne' => $enseignesArray
        ]);
    }

    #[Route('/back/admin/enseigne/list', name: 'app_enseigne_list')]
    public function listEnseignes(Request $request, AUIService $auiService, AdminService $adminService): JsonResponse
    {
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", $request->getContent(), SourceEnum::ClientJson);
        $this->setSession($request);
        $pgmEnrolement = $this->sessionService->getProgramme();   // Pgm d'enrolement en cours
        $utilisateur = $this->sessionService->getUtilisateur();   // Pgm d'enrolement en cours

        $error = CodeErreurEnum::ok;
        $message = "";
        $data = json_decode($request->getContent(), true);


        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "list enseignes", SourceEnum::BackController);

        $enseignesArray = array();
        if ($message !== '')
            $error = CodeErreurEnum::nok;
        else {
            try {
                $enseignesArray = $adminService->listEnseignes();
            } catch (\Exception $exception) {
                $error = CodeErreurEnum::ex;
                $message = $exception->getMessage();
            }
        }
        return $this->json([
            'error' => $error,
            'message' => $message,
            'enseigne' => $enseignesArray
        ]);
    }


    #[Route('/back/admin/parametres/set', name: 'app_parametres_set')]
    public function setParametres(Request $request, AUIService $auiService, AdminService $adminService): JsonResponse
    {
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", $request->getContent(), SourceEnum::ClientJson);
        $this->setSession($request);
        $utilisateur = $this->sessionService->getUtilisateur();   // Pgm d'enrolement en cours

        $error = CodeErreurEnum::ok;
        $message = "";
        $data = json_decode($request->getContent(), true);

        if (isset($data['maxRetry'])) $maxRetry = $data['maxRetry'];
        else $message = "maxRetry obligatoire";
        if (isset($data['doTasksDep'])) $doTasksDep = $data['doTasksDep'];
        else $message = "doTasksDep obligatoire";
        if (isset($data['doTasksKnox'])) $doTasksKnox = $data['doTasksKnox'];
        else $message = "doTasksKnox obligatoire";
        if (isset($data['doTasksZT'])) $doTasksZT = $data['doTasksZT'];
        else $message = "doTasksZT obligatoire";

        try {
            $adminService->saveParametres($maxRetry, $doTasksDep, $doTasksKnox, $doTasksZT, $utilisateur);
        } catch (\Exception $exception) {
            $error = CodeErreurEnum::ex;
            $message = $exception->getMessage();
        }

        return $this->json([
            'error' => $error,
            'message' => $message,
            'maxRetry' => $maxRetry,
            'doTasksDep' => $doTasksDep,
            'doTasksKnox' => $doTasksKnox,
            'doTasksZT' => $doTasksZT
        ]);
    }

    #[Route('/back/admin/parametres/get', name: 'app_parametres_get')]
    public function getParametres(Request $request, AUIService $auiService, AdminService $adminService): JsonResponse
    {
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", $request->getContent(), SourceEnum::ClientJson);
        $this->setSession($request);

        $error = CodeErreurEnum::ok;
        $message = "";
        $data = json_decode($request->getContent(), true);


        try {
            $parametres = $adminService->getParametres();
            return $this->json([
                'error' => $error,
                'message' => $message,
                'maxRetry' => $parametres['maxRetry'],
                'doTasksDep' => $parametres['doTasksDep'],
                'doTasksKnox' => $parametres['doTasksKnox'],
                'doTasksZT' => $parametres['doTasksZT']
            ]);
        } catch (\Exception $exception) {
            $error = CodeErreurEnum::ex;
            $message = $exception->getMessage();
            return $this->json([
                'error' => $error,
                'message' => $message,
            ]);
        }
    }

    #[Route('/back/admin/utilisateur/activites', name: 'app_utilisateur_activites')]
    public function getUtilisateurActivites(Request $request, AUIService $auiService, AdminService $adminService): JsonResponse
    {
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", $request->getContent(), SourceEnum::ClientJson);
        $this->setSession($request);

        $error = CodeErreurEnum::ok;
        $message = "";
        $data = json_decode($request->getContent(), true);

        $utilisateurId = "";
        if (isset($data['utilisateurId'])) $utilisateurId = $data['utilisateurId'];
        else $message = "Identifiant d'utilisateur obligatoire";

        $activitesArray = [];
        try {
            $utilisateur = $adminService->getUtilisateur($utilisateurId);
            $activitesArray = $adminService->getUtilisateurActivites($utilisateur);
            return $this->json([
                'error' => $error,
                'message' => $message,
                'utilisateur' => $utilisateur->getPrenom() . ' ' . $utilisateur->getNom(),
                'enseigne' => $utilisateur->getEnseigne()->getRaisonSociale(),
                'activites' => $activitesArray
            ]);
        } catch (\Exception $exception) {
            $error = CodeErreurEnum::ex;
            $message = $exception->getMessage();
            return $this->json([
                'error' => $error,
                'message' => $message,
            ]);
        }
    }
}

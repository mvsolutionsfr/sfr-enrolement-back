<?php

namespace App\Controller;

use App\Service\DepAppleRestService;
use App\Service\EnrolementService;
use App\Service\KnoxRestService;
use App\Service\LoggerESService;
use App\Service\MetricsService;
use App\Service\ParamService;
use App\Service\SessionService;
use App\Service\ZTRestService;
use App\Toolbox\CodeErreurEnum;
use App\Toolbox\LogLevelEnum;
use App\Toolbox\MetricIndicatorEnum;
use App\Toolbox\SourceEnum;
use Artprima\PrometheusMetricsBundle\Controller\MetricsController;
use Artprima\PrometheusMetricsBundle\Metrics\Renderer;
use Doctrine\Persistence\ManagerRegistry;
use Elasticsearch\ClientBuilder;
use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;
use Psr\Log\LoggerInterface;
use Redis;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Attribute\Route;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class ToolsController extends AngularController
{
    private string $cryptKey;
    private string $cryptIv;
    private MetricsService $metricsService;

    public function __construct(LoggerESService $logger, RequestStack $requestStack, SessionService $sessionService, MetricsService $metricsService)
    {
        parent::__construct($logger, $requestStack, $sessionService);
        $this->logger = $logger;
        $this->requestStack = $requestStack;
        $this->cryptKey = $_SERVER['CRYPT_KEY'] ?? "";
        $this->cryptIv = $_SERVER['CRYPT_IV'] ?? "";
        $this->metricsService = $metricsService;
    }


    #[Route('/tools/param/{id}', name: 'tools_params')]
    public function parametres(string $id, Request $request, ParamService $paramService): JsonResponse
    {
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", $request->getContent(), SourceEnum::ClientJson);

        $data = json_decode($request->getContent(), true);
        $this->setSession($request);
        if ($id == "set") {
            return $this->json($paramService->setParametres($data));
        } else {
            return $this->json($paramService->getParametres());
        }
    }

    #[Route('/tools/generesession', name: 'tools_generatesession')]
    public function genereSession(Request $request, KnoxRestService $knoxRestService): JsonResponse
    {
 // enseigneId
 // userId
 // pgmId
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", $request->getContent(), SourceEnum::ClientJson);
        $data = json_decode($request->getContent(), true);
        $enseigneid = $data["enseigneid"] ?? "";
        $userid = $data["userid"] ?? "";
        $pgmid = $data["pgmid"] ?? "";
        $param = ["enseigneId" => $enseigneid, "userId" => $userid,"pgmId"=> $pgmid];
 //       dd(json_encode(["hex" => $this->cryptKey]));

        $result = openssl_encrypt(json_encode($param), 'AES-128-CBC', $this->cryptKey, OPENSSL_ZERO_PADDING, substr($this->cryptIv, 0, 16));
        return $this->json(["session" => $result]);
    }
    #[Route('/tools/callknox', name: 'tools_callknox')]
    public function callKnox(Request $request, KnoxRestService $knoxRestService): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $methode = $data["type"] ?? "PUT";  // POST ou GET
        $url = $data["url"] ?? "";
        $body = $data["body"] ?? "";
        $retour = $knoxRestService->callServeur($methode, $url, $body);
        return $this->json(['status' => $retour[0], 'content' => $retour[1]]);
    }

    #[Route('/tools/testServeurKnox', name: 'test_callknox')]
    public function testServeurKnox(Request $request, KnoxRestService $knoxRestService): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $vendorId = $data["vendorId"] ?? "";
        $transactionId = $data["transactionId"] ?? "";
        $retour = $knoxRestService->testServeur($vendorId,$transactionId);
        return $this->json(['status' => $retour[0], 'content' => $retour[1]]);
    }

    #[Route('/tools/checkstatusknox', name: 'tools_checkstatusknox')]
    public function checkStatusKnox(Request $request, KnoxRestService $knoxRestService): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $depResellerId = $data["depResellerId"] ?? "";
        $tid = $data["transactionId"] ?? "";
        $retour = $knoxRestService->checkTransactionStatus($depResellerId, $tid);
        /** @var JsonResponse $jr */
        $jr = $this->json($retour);
        $jr->headers->add(['Access-Control-Allow-Origin' => '*']);
        return $jr;
    }

    #[Route('/tools/checkstatusapple', name: 'tools_checkstatusapple')]
    public function checkStatusApple(Request $request, DepAppleRestService $depRestService): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $depResellerId = $data["depResellerId"] ?? "";
        $tid = $data["transactionId"] ?? "";
        $retour = $depRestService->checkTransactionStatus($depResellerId, $tid);
        /** @var JsonResponse $jr */
        $jr = $this->json($retour);
        $jr->headers->add(['Access-Control-Allow-Origin' => '*']);
        return $jr;
    }

//    #[Route('/tools/checkorderapple', name: 'tools_checkorderapple')]
//    public function checkOrderApple(Request $request, DepAppleRestService $depRestService): JsonResponse
//    {
//        $data = json_decode($request->getContent(), true);
//        $depResellerId = $data["depResellerId"] ?? "";
//        $tid = $data["orderId"] ?? "";
//        $retour = $depRestService->synchronizeTransaction($depResellerId, $tid);
//        /** @var JsonResponse $jr */
//        $jr = $this->json($retour);
//        $jr->headers->add(['Access-Control-Allow-Origin' => '*']);
//        return $jr;
//    }

     #[Route('/back/tools/addlog', name: 'tools_addlog')]
    public function addLog(Request $request): JsonResponse
    {
        try {
            $this->setSession($request);
        } catch (\Exception) {
        };

        $data = json_decode($request->getContent(), true);

        $message = $data["message"] ?? "";
        $level = $data["level"] ?? "";
        $functionName = $data["function"] ?? "";

        $logLevel = LogLevelEnum::tryFrom($level);
        if (!$logLevel) {
            return $this->json(['error' => CodeErreurEnum::nok, 'message' => "paramètre level incorrecte"]);
        }

        try {
            $this->logger->writeLog($logLevel, $functionName, $message, SourceEnum::Front);
        } catch (\Exception $exception) {
            return $this->json(['error' => CodeErreurEnum::ex, 'message' => $exception->getMessage()]);
        }
        return $this->json(['error' => CodeErreurEnum::ok, 'message' => ""]);

    }

    /**
     * essai d'ajout de commentaire pour force l'update
     */
    #[Route('/tools/alive', name: 'tools_alive')]
    public function alive(Request $request): JsonResponse
    {
//        $this->metricsService->createMetric(MetricIndicatorEnum::erreurFonctionnel,"Samsung","test","65464");
        return $this->json("alive!" ) ;
    }

    #[Route('/', name: 'tools_welcome')]
    public function welcome(Request $request): JsonResponse
    {
        return $this->json("Enrolement Back!");
    }

    #[Route('/back/tools/infophp', name: 'tools_infophp')]
    public function infophp(Request $request): Response
    {

        date_default_timezone_set('UTC');
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "Infophp", SourceEnum::BackService);
        return new Response(phpinfo());
    }

     #[Route('/tools/testdb', name: 'tools_testdb')]
    public function testdb(Request $request, ManagerRegistry $doctrine): JsonResponse
    {
        $em = $doctrine->getManager();
        $em->getConnection()->connect();
        $connected = $em->getConnection()->isConnected();
        return $this->json(["db_ok" => $connected]);
    }

    #[Route('/tools/gensession', name: 'tools_gensession')]
    public function gensession(Request $request, ManagerRegistry $doctrine): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $userid = $data["userId"] ?? "";
        $enseigneid = $data["enseigneId"] ?? "";
        $pgmid = $data["pgmId"] ?? "";
        $content = json_encode(['userId' => $userid, 'enseigneId' => $enseigneid, 'pgmId' => $pgmid]);

        $retour = ["session" => openssl_encrypt($content, 'AES-128-CBC', hex2bin($this->cryptKey), OPENSSL_ZERO_PADDING, hex2bin($this->cryptIv))];


        return $this->json($retour);

    }

    #[Route('/tools/testzt', name: 'tools_testzt')]
    public function testzt(Request $request, ManagerRegistry $doctrine): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $deviceEnrollmentTransactionId = $data["tid"] ?? "";

        $tid = explode("-", $deviceEnrollmentTransactionId);
        $nbDevice = count($tid) - 1;
        $deb = $tid[0];
        array_splice($tid, 0, 1);

        $resultat = [
            'done' => true,
            'metadata' => [
                '@type' => 'type.googleapis.com/google.android.device.provisioning.v1.DevicesLongRunningOperationMetadata',
                "processingStatus" => "BATCH_PROCESS_PROCESSED",
                "progress" => 100,
                "devicesCount" => $nbDevice
            ],
            "name" => "transactionId",
            "response" => [
                "@type" => "type.googleapis.com/google.android.device.provisioning.v1.DevicesLongRunningOperationResponse",
                "successCount" => $nbDevice,
                "perDeviceStatus" => []
            ]
        ];


        if ($deb == "RE" || $deb = "AN") {
            $statusCode = 200;
            foreach ($tid as $imei) {
                if ($imei == "AN" || $imei == "RE" || $imei == "OR" || $imei == "") continue;
                $device = [
                    "result" => [
                        "status" => "SINGLE_DEVICE_STATUS_SUCCESS",
                        'deviceId' => $imei
                    ],
                    "unclaim" => [
                        "deviceIdentifier" => [
                            "imei" => $imei,
                            "manufacturer" => "CROSSCALL"
                        ],
                        "sectionType" => "SECTION_TYPE_ZERO_TOUCH"
                    ]
                ];
                $resultat["response"]["perDeviceStatus"][] = $device;
            }
//                    $content_succes = '{"done":true,"metadata":{"@type":"type.googleapis.com/google.android.device.provisioning.v1.DevicesLongRunningOperationMetadata","processingStatus":"BATCH_PROCESS_PROCESSED","progress":100,"devicesCount":2},"name":"operations/apibatchoperation/2676181951105813942","response":{"@type":"type.googleapis.com/google.android.device.provisioning.v1.DevicesLongRunningOperationResponse","successCount":2,"perDeviceStatus":[{"result":{"status":"SINGLE_DEVICE_STATUS_SUCCESS"},"unclaim":{"deviceIdentifier":{"imei":"868074057439230","manufacturer":"CROSSCALL"},"sectionType":"SECTION_TYPE_ZERO_TOUCH"}},{"result":{"status":"SINGLE_DEVICE_STATUS_SUCCESS"},"unclaim":{"deviceIdentifier":{"imei":"356557082621540","manufacturer":"CROSSCALL"},"sectionType":"SECTION_TYPE_ZERO_TOUCH"}}]}}';
            $content = json_encode($resultat);
        } else {
            $statusCode = 200;
            foreach ($tid as $imei) {
                if ($imei == "AN" || $imei == "RE" || $imei == "OR" || $imei == "") continue;
                $device = [
                    "result" => [
                        "status" => "SINGLE_DEVICE_STATUS_SUCCESS",
                        'deviceId' => $imei
                    ],
                    "claim" => [
                        "deviceIdentifier" => [
                            "imei" => $imei,
                            "manufacturer" => "CROSSCALL"
                        ],
                        "sectionType" => "SECTION_TYPE_ZERO_TOUCH"
                    ]
                ];
            }
            $resultat["response"]["perDeviceStatus"][] = $device;
        }
        return $this->json($resultat);
    }

    #[Route('/tools/testlog', name: 'tools_testlog')]
    public function testlog(Request $request): JsonResponse
    {
        $clientBuilder = ClientBuilder::create()
            ->setHosts(["https://enrolement-sbd-elastic.private.sfr.com:443"])
            ->setBasicAuthentication("gesco", "GescO!PassW0rd")
            ->setSSLVerification(false)
            ->build();

        $logElastic = [
            "code" => ["code_id" => LogLevelEnum::Info->value, "code_lib" => LogLevelEnum::Info->name],
            "customer_id" => "",
            "date" => date_create("now"),
            "classe" => "ToolsController",
            "fonction" => "testlog",
            "numero_commande" => "",
            "programme_enrol" => "",
            "reseller_id" => "",
            "session_id" => "",
            "source" => "",
            "token" => "",
            "user_id" => "2",
            "message" => "Test log"
        ];
        try {
            $results = $clientBuilder->index(
                [
                    "index" => "logs_enrolement",
                    "refresh" => true,
                    "body" => $logElastic,
                ]
            );
        } catch (\Exception $ex) {
            dd($ex);
        }

        return $this->json("Done!");
    }

    #[Route('/tools/getlog', name: 'tools_getlog')]
    public function getlog(Request $request): ?Response
    {
        $response = new Response();
        $baseDirLog = $_SERVER['BASE_DIR_LOG'] ?? "";
        if ($baseDirLog == "") {
            $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "recup log - base_dir_log vide", SourceEnum::BackService);
            $response->setContent("environnement BASE_LOG_DIR inexistant");
            return $response;
        }

        $logfilename = $request->get("logfile");
        if ($logfilename != "") {
            try {
                $filename = $baseDirLog . $logfilename . ".log";
                $realfilename = realpath($filename);
                if ($realfilename) {
                    $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "recup log [" . $realfilename . "]", SourceEnum::BackService);

// Set headers
                    $response->headers->set('Cache-Control', 'private');
                    $response->headers->set('Content-type', mime_content_type($realfilename));
                    $response->headers->set('Content-Disposition', 'attachment; filename="' . basename($filename) . '";');
                    $response->headers->set('Content-length', filesize($realfilename));

// Send headers before outputting anything
                    $response->sendHeaders();
                    $response->setContent(file_get_contents($realfilename));
                } else {
                    $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "fichier log inexistant [" . $filename . "]", SourceEnum::BackService);
                    $response->setContent("Fichier de log inexistant [".$filename."]");
                }
            } catch (\Exception $ex) {
            }
        }
        return $response;
    }

    #[Route('/tools/getlastlog', name: 'tools_getlastlog')]
    public function getlastlog(Request $request): ?Response
    {
        $response = new Response();
        $baseDirLog = $_SERVER['BASE_DIR_LOG'] ?? "";

        $dt = date_create("now");
        $dt = date_format($dt,"Y-m-d");
        $logfilename = $baseDirLog.$_SERVER['APP_ENV']."-".$dt.".log";

        if ($logfilename != "") {
            try {
                $filename = $logfilename;
                $realfilename = realpath($filename);
                if ($realfilename) {
                    $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "recup log [" . $realfilename . "]", SourceEnum::BackService);

// Set headers
                    $response->headers->set('Cache-Control', 'private');
                    $response->headers->set('Content-type', mime_content_type($realfilename));
                    $response->headers->set('Content-Disposition', 'attachment; filename="' . basename($filename) . '";');
                    $response->headers->set('Content-length', filesize($realfilename));

// Send headers before outputting anything
                    $response->sendHeaders();
                    $response->setContent(file_get_contents($realfilename));
                } else {
                    $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "fichier log inexistant [" . $filename . "]", SourceEnum::BackService);
                    $response->setContent("Fichier de log inexistant [".$filename."]");
                }
            } catch (\Exception $ex) {
            }
        }
        return $response;
    }

}
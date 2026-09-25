<?php

namespace App\Service;

use App\Entity\Orders;
use App\Entity\Terminal;
use App\Toolbox\LogLevelEnum;
use App\Toolbox\MetricIndicatorEnum;
use App\Toolbox\ProgrammeEnrolementEnum;
use App\Toolbox\SourceEnum;
use App\Toolbox\StatutRequeteEnum;
use App\Toolbox\TypeEnrolementEnum;
use DateInterval;
use DateTime;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

//use function Sodium\add;

class DepAppleRestService extends AbstractRestService
{
    private $client;
    private $utc;
    private string $bouchon;
    private string $transactionPrefix;
    private string $encodedAuth;


    public function __construct(HttpClientInterface $client, LoggerESService $logger, SessionService $session, MetricsService $metricsService)
    {
        parent::__construct(null, $logger, $session, $metricsService);
        $this->client = $client;
        $userauth = $_SERVER['DEP_PAWS_USER'] ?? "";
        $passwdauth = $_SERVER['DEP_PAWS_PASSWORD'] ?? "";
        $this->encodedAuth = base64_encode($userauth . ":" . $passwdauth);
        $this->bouchon = $_SERVER['DEP_BOUCHON_SERVER'] ?? "";
        $this->transactionPrefix = $_SERVER['DEP_PREFIX_ID'] ?? "";
    }

    protected function processAppel(TypeEnrolementEnum $orderType, string $depResellerId, string $customerId, Orders $order, string $removeTransactionId, array $terminaux, string $transactionId, ?\DateTimeInterface $dateCreation = null): ?array
    {
        $orderNumber = $order->getId();
        if ($dateCreation) {
            $dateTimeCreation = DateTime::createFromInterface($dateCreation);
        }
        else {
            $dateTimeCreation = date_create('now');
        }
        $dateTimeCreation->sub(DateInterval::createFromDateString('2 hours'));
        $this->utc = $dateTimeCreation->format("Y-m-d\TH:i:s\Z");

        $DEP_URL_ENROLL = $_SERVER['DEP_URL_ENROLL'];
        $SHIP_TO = $_SERVER['DEP_SHIP_TO'];
        $ENV = $_SERVER['APP_ENV'];

        $orderTypeReq = "OR";
        $orderNumber2 = $this->transactionPrefix . $orderNumber;
        $transactionId = sprintf("%s%s", $this->transactionPrefix,$orderNumber);
        if ($orderType == TypeEnrolementEnum::Desinscription) {
            if ($removeTransactionId != "")
                $orderNumber2 = "RE-".$removeTransactionId;
            else
                $orderNumber2 = "RE-".$orderNumber2;
            $orderTypeReq = "RE";
        } elseif ($orderType == TypeEnrolementEnum::Annulation) {
            if ($removeTransactionId != "") {
                $orderNumber2 = $removeTransactionId;
                //$transactionId = $removeTransactionId;
            }
            $orderTypeReq = "VD";
        }

        $formFields = [
            'requestContext' => [
                'langCode' => 'fr',
                'timeZone' => '-60',
                'shipTo' => $SHIP_TO
            ],
            'orders' => [
                [
                    "orderType" => $orderTypeReq,
                    'orderNumber' => $orderNumber2,
                    "customerId" => $customerId,
                    "orderDate" => $this->utc,
                    "poNumber" => '',
                    'deliveries' => [
                        [
                            'shipDate' => $this->utc,
                            'deliveryNumber' => '0',
                            'devices' => [
                            ],
                        ],
                    ],]
            ],
            'transactionId' => $transactionId,
            'depResellerId' => $depResellerId,
        ];
        $tt = "";
        if ($orderType != TypeEnrolementEnum::Annulation) {
            foreach ($terminaux as $terminal) {
                /** @var Terminal $terminal */
                if ($terminal->getNumeroSerie() != null && $terminal->getNumeroSerie() != "") {
                    $formFields["orders"][0]["deliveries"][0]["devices"][] = ["deviceId" => $terminal->getNumeroSerie(), "assetTag" => ''];
                } else {
                    $tt = $tt . $terminal->getNumeroIMEI() . "-";
                    $formFields["orders"][0]["deliveries"][0]["devices"][] = ["deviceId" => $terminal->getNumeroIMEI(), "assetTag" => ''];
                }
            }
        } else {
            $formFields["orders"][0]["deliveries"] = array();
        }

        $this->logger->writeLog(LogLevelEnum::Debug, __METHOD__ . "[" . __LINE__ . "]", "[REQUEST][DEP][URL]" . $DEP_URL_ENROLL, SourceEnum::ApiApple,$order);
        $json = json_encode($formFields);
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "[REQUEST][DEP][BODY]" . $json, SourceEnum::ApiApple,$order);
        $this->logger->writeLogToDatabase(ProgrammeEnrolementEnum::Apple,$order,"[".$orderTypeReq."][BODY]",$formFields);

        try {
            if ($this->bouchon != "1") {
                $response = $this->client->request(
                    'POST',
                    $DEP_URL_ENROLL, [
                    'headers' => ['Content-Type' => 'application/json', "Authorization" => "Basic " . $this->encodedAuth],
                    'json' => $formFields,
                ],
                );
                $statusCode = $response->getStatusCode();
                $content = $response->getContent(false);
            } else {
                $tid = "";
                foreach ($terminaux as $terminal) {
                    if ($terminal->getNumeroIMEI() != "")
                        $tid = $tid . $terminal->getNumeroIMEI() . "-";
                    else if ($terminal->getNumeroSerie() != "") {
                        $tid = $tid . $terminal->getNumeroSerie() . "-";
                    }
                }
                if ($tid == "") $tid = 'noTid';

                // TODO A COMMENTER LES LIGNES DE TEST
                if ($orderType == TypeEnrolementEnum::Desinscription) {
                    $tid = "RE-" . $tid;
                } elseif ($orderType == TypeEnrolementEnum::Annulation) {
                    $tid = "AN-" . $tid;
                }
                $content_ok = '{"deviceEnrollmentTransactionId":"' . $tid . '","enrollDevicesResponse":{"statusCode":"SUCCESS","statusMessage":"Transaction posted successfully in DEP"}}';
                $content_error1 = '{"errorCode":"GRX-50025","errorMessage":"Ship-To entered is not valid. Please enter a valid Ship-To","transactionId":"13836d68-7353-4acc-a813-0e217be8f04d-1424990915792"}';
                $content_error2 = '{"enrollDeviceErrorResponse":{"errorCode":"GRX-1056","errorMessage":"DEP Reseller ID missing. Enter a valid DEP Reseller ID and resubmit your request."}}';
                $content_error3 = '{"enrollDeviceErrorResponse":[{"errorCode":"GRX-1056","errorMessage":"DEP Reseller ID missing. Enter a valid DEP Reseller ID and resubmit your request."},{"errorCode":"DEP-ERR-3003","errorMessage":"Order information missing. The transaction needs to have one or more valid orders. Enter valid orders and resubmit your request."},{"errorCode":"DEP-ERR-3001","errorMessage":"Transaction ID missing. Enter a valid transaction ID and resubmit your request."}]}';
                $statusCode = 200;
                $content = $content_ok;
            }
            $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "[STATUS][DEP][RESPONSE][".$statusCode."]" . $content, SourceEnum::ApiApple, $order);

            if ($statusCode == 200) {
                $retour = json_decode($content, true);
                $this->logger->writeLogToDatabase(ProgrammeEnrolementEnum::Apple,$order,"[".$orderTypeReq."][RESPONSE][".$statusCode."]",$retour);
                $resultat['statusCode'] = $statusCode;

                if (array_key_exists('enrollDevicesResponse', $retour)) {
                    $enrollDevicesResponse = $retour["enrollDevicesResponse"];
                    $resultat['statut'] = StatutRequeteEnum::Succes->value;
                    $resultat["statusMessage"] = $enrollDevicesResponse["statusMessage"];
                    $resultat["transactionId"] = $retour["deviceEnrollmentTransactionId"];
                } elseif (array_key_exists('enrollDeviceErrorResponse', $retour)) {
                    $enrollDeviceErrorResponse = $retour["enrollDeviceErrorResponse"];
                    $resultat['statut'] = StatutRequeteEnum::Erreur->value;
                    if (array_key_exists("errorCode", $enrollDeviceErrorResponse)) {
//                    $resultat["statusCode"] = $enrollDeviceErrorResponse["errorCode"];
                        $resultat["statusMessage"] = "[" . $enrollDeviceErrorResponse["errorCode"] . "] " . $enrollDeviceErrorResponse["errorMessage"];
                    } else {
                        $errorMessage = "";
                        foreach ($enrollDeviceErrorResponse as $enrollResponse) {
                            if ($errorMessage == "") {
                                $errorMessage = "[" . $enrollResponse["errorCode"] . "] " . $enrollResponse["errorMessage"];
                            } else {
                                $errorMessage = $errorMessage . "|" . "[" . $enrollResponse["errorCode"] . "] " . $enrollResponse["errorMessage"];
                            }
                        }
                        $resultat["statusMessage"] = $errorMessage;
                    }
                } elseif (array_key_exists('transactionId', $retour)) {
                    $transactionId = $retour["transactionId"];
                    $resultat["statut"] = StatutRequeteEnum::Erreur->value;
                    $resultat["statusMessage"] = "[" . $retour["errorCode"] . "] " . $retour["errorMessage"];
                    $resultat["transactionId"] = $transactionId;
                }
            } else {
                if ((int)$statusCode >= 500) {
                    $retour = array("statut" => StatutRequeteEnum::ErreurARejouer->value, "statusCode" => $statusCode, "code" => "", "message" => $content);
                    $this->logger->writeLogToDatabase(ProgrammeEnrolementEnum::Apple, $order, "[".$orderTypeReq."][RESPONSE][AREJOUER]" . $content, null);
                } else {
                    $retour = array("statut" => StatutRequeteEnum::Erreur->value, "statusCode" => $statusCode, "code" => "", "message" => $content);
                    $this->logger->writeLogToDatabase(ProgrammeEnrolementEnum::Apple, $order, "[".$orderTypeReq."][RESPONSE][ERREUR][" . $statusCode . "]".$message,null);
                }
            }
        } catch (\Exception $exception) {
            $this->logger->writeLog(LogLevelEnum::Critique, __METHOD__ . "[" . __LINE__ . "]", "Exception " . $exception->getMessage(), SourceEnum::ApiApple,$order);
            $resultat = array("statut" => StatutRequeteEnum::Exception->value, "code" => $exception->getCode(), "statusMessage" => $exception->getMessage());
        }

        $this->logger->writeLog(LogLevelEnum::Debug, __METHOD__ . "[" . __LINE__ . "]", "[RETOUR] " . json_encode($resultat), SourceEnum::ApiApple,$order);
        return $resultat;

    }

    public function checkTransactionStatus(string $depResellerId, string $deviceEnrollmentTransactionId, ?Orders $order = null): ?array
    {
        /// retour["statut"], statut global de la requete (valeur de l'énumération StatutRequeteEnum)
        /// retour["code"], code d'exception eventuel
        /// retour["statusCode"], code de retour de la requete d'appel
        /// retour["message"], message d'erreur éventuel
        /// retour["devices"]["imei"], numéro IMEI du controle de status
        /// retour["devices"]["statut"], code statut de l'IMEI (valeur de l'énumération StatutRequeteEnum)
        /// retour["devices"]["message"], message d'erreur éventuel

        $SHIP_TO = $_SERVER['DEP_SHIP_TO'];
        $DEP_URL_STATUS = $_SERVER['DEP_URL_STATUS'];

        $formFields = [
            'deviceEnrollmentTransactionId' => $deviceEnrollmentTransactionId,
            'depResellerId' => $depResellerId,
            'requestContext' => [
                'langCode' => 'fr',
                'timeZone' => '-60',
                'shipTo' => $SHIP_TO
            ],
        ];

        $this->logger->writeLog(LogLevelEnum::Debug, __METHOD__ . "[" . __LINE__ . "]", "[REQUEST][DEP][URL]" . $DEP_URL_STATUS, SourceEnum::ApiApple,$order);
        $json = json_encode($formFields);
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "[REQUEST][DEP][BODY]" . $json, SourceEnum::ApiApple,$order);
        $this->logger->writeLogToDatabase(ProgrammeEnrolementEnum::Apple,$order,"[STATUS][BODY]",$formFields);

        try {

            if ($this->bouchon != "1") {
                $response = $this->client->request(
                    'POST',
                    $DEP_URL_STATUS, [
                    'headers' => ['Content-Type' => 'application/json', 'Accept-Encoding' => 'identity', "Authorization" => "Basic " . $this->encodedAuth],
                    'json' => $formFields,
                ],
                );
                $statusCode = $response->getStatusCode();
                $content = $response->getContent(false);
            } else {
                $statusCode = 200;
                $tid = explode("-", $deviceEnrollmentTransactionId);


                 $content_retour = [
                    "deviceEnrollmentTransactionID" => $deviceEnrollmentTransactionId,
                    "statusCode" => " COMPLETE",
                    "completedOn" => "2023-01-10T13:03:20Z",
                    "transactionId" => "TEST",
                    "orders" => [[
                        "orderNumber" => "D2300101",
                        "orderPostStatus" => "COMPLETE",
                        "deliveries" => [[
                            "deliveryNumber" => "0",
                            "deliveryPostStatus" => "COMPLETE",
                            "devices" => []
                        ]
                        ]
                    ]]
                ];

                foreach ($tid as $imei) {
                    if ($imei == "AN" || $imei == "RE" || $imei == "") continue;
                    $content_retour["orders"][0]["deliveries"][0]["devices"][] = array("devicePostStatus" => "COMPLETE", "deviceId" => $imei);
                }
                if ($tid[0] == "AN") $content_retour["orders"]["deliveries"]["devices"] = "";
                $content = json_encode($content_retour);

//                if ($tid[0] == "RE") {
//                   $content_ok = '{"deviceEnrollmentTransactionID":"'.$deviceEnrollmentTransactionId.'","statusCode":"COMPLETE","orders":[{"orderNumber":"D2300101","orderPostStatus":"COMPLETE","deliveries":[{"deliveryNumber":"0","deliveryPostStatus":"COMPLETE","devices":[{"devicePostStatus":"COMPLETE","deviceId":"868074057439230"},{"devicePostStatus":"COMPLETE","deviceId":"356557082621540"}]}]}],"completedOn":"2023-01-10T13:03:20Z","transactionId":"D2300101"}';
//                } elseif ($tid[0] == "AN") {
//                    $content = json_encode($content_retour);
//                    $content_ok = '{"deviceEnrollmentTransactionID":"'.$deviceEnrollmentTransactionId.'","statusCode":"COMPLETE","orders":[{"orderNumber":"1","orderPostStatus":"COMPLETE"}],"completedOn":"2023-04-14T13:16:23Z","transactionId":"1"}';
//                    $content_error_shipcode = '{"errorCode":"GRX-50025","errorMessage":"Ship-To entered is not valid. Please enter a valid Ship-To","transactionId":"13836d68-7353-4acc-a813-0e217be8f04d-1424990915792"}';
//                    $content_error_denied = '{"checkTransactionErrorResponse":[{"errorMessage":"Access denied. You do not have permission to act on behalf of the Device Enrollment Program reseller ID.","errorCode":"GRX-90004"}]}';
//                } else {
//                    $content_ok = '{"deviceEnrollmentTransactionID":"'.$deviceEnrollmentTransactionId.'","statusCode":"COMPLETE","orders":[{"orderNumber":"D2300101","orderPostStatus":"COMPLETE","deliveries":[{"deliveryNumber":"0","deliveryPostStatus":"COMPLETE","devices":[{"devicePostStatus":"COMPLETE","deviceId":"866228059717256"},{"devicePostStatus":"COMPLETE","deviceId":"868074057439230"},{"devicePostStatus":"COMPLETE","deviceId":"356557082621540"}]}]}],"completedOn":"2023-01-10T13:03:20Z","transactionId":"D2300101"}';
//                    $content_error1 = '{"checkTransactionErrorResponse":[{"errorCode":"GRX-1056","errorMessage":"DEP Reseller ID missing. Enter a valid DEP Reseller ID and resubmit your request."},{"errorCode":"DEP-ERR-4001","errorMessage":"Device Enrollment Program Transaction ID missing. Enter a valid transaction ID (for example,d813a291-996b-49c3-b09a-6906eced573e_1411422020272) and resubmit your request."}]}';
//                    $content_error2 = '{"checkTransactionErrorResponse":[{"errorMessage":"Access denied. You do not have permission to acton behalf of the Device Enrollment Program reseller ID.","errorCode":"GRX-90004"}]}';
//                    $content_error3 = '{"deviceEnrollmentTransactionID":"9acc1cf5-e41d-44d4-a066-78162a389da2_1413529391461","completedOn":"2014-10-17T07:03:15Z","orders":[{"orderNumber":"ORDER_900123","orderPostStatus":"ERROR","deliveries":[{"deliveryNumber":"D1.2","deliveryPostStatus":"ERROR","devices":[{"deviceId":"CQ115U05PKK","devicePostStatus":"DEP-ERR-DE-4305","devicePostStatusMessage":"Device unavailable. The device is assigned to another reseller for a different normal (OR) or override (OV) order. Try using a different device ID and resubmit your request."},{"deviceId":"CQ115U06PKK","devicePostStatus":"DEP-ERR-DE-4305","devicePostStatusMessage":"Device unavailable. The device is assigned to another reseller for a different normal (OR) or override (OV) order. Try using a different device ID and resubmit your request."}]}]}],"statusCode":"ERROR"}';
//                    $content_error_partiel = '{"deviceEnrollmentTransactionID" : "'.$deviceEnrollmentTransactionId.'","statusCode" : "COMPLETE_WITH_ERRORS","orders" : [ {"orderNumber" : "D2301787","orderPostStatus" : "POSTED_WITH_ERRORS","deliveries" : [ {"deliveryNumber" : "0","deliveryPostStatus" : "POSTED_WITH_ERRORS","devices" : [ {        "devicePostStatus" : "DEP-ERR-DE-4303",        "deviceId" : "353698109624175",        "devicePostStatusMessage" : "Device ID cannot be used for orders. The same device ID cannot be used for different normal (OR) or override (OV) orders. Try using a different device ID and resubmit your request."      }, {        "devicePostStatus" : "COMPLETE",        "deviceId" : "359137276492672"      }, {        "devicePostStatus" : "COMPLETE",        "deviceId" : "359137276589485"      }, {        "devicePostStatus" : "COMPLETE",        "deviceId" : "359137276886402"      }, {        "devicePostStatus" : "COMPLETE",        "deviceId" : "359710399577908"      } ]    } ]  } ],  "completedOn" : "2023-04-04T14:58:30Z",  "transactionId" : "D2301787"}';
//                    $content = $content_error_partiel;
//                }
            }

            $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "[STATUS]" . $statusCode, SourceEnum::ApiApple,$order);
            $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "[REQUEST][DEP][RESPONSE]" . $content, SourceEnum::ApiApple,$order);

            if ($statusCode == "200") {
                $resultat = json_decode($content, true);
                $this->logger->writeLogToDatabase(ProgrammeEnrolementEnum::Apple,$order,"[STATUS][RESPONSE][".$statusCode."]",$resultat);

                $statusCodeApple = $resultat["statusCode"] ?? null;
                $transactionIdApple = $resultat["transactionId"] ?? null;
                $checkTransactionErrorResponse = $resultat["checkTransactionErrorResponse"] ?? null;
                if ($checkTransactionErrorResponse) {
                    $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "[ERREUR checkTransactionErrorResponse", SourceEnum::ApiApple,$order);
                    $message = "";
                    foreach ($checkTransactionErrorResponse as $checkTransactionError) {
                        $errorCode = $checkTransactionError['errorCode'] ?? "";
                        $errorMessage = $checkTransactionError['errorMessage'] ?? "";
                        if ($message == "") $message = "[" . $errorCode . "] " . $errorMessage; else $message .= "|[" . $errorCode . "] " . $errorMessage;
                    }
                    $statut = StatutRequeteEnum::Erreur->value;
                    if ($errorCode == "DEP-ERR-4003" || $errorCode == "DEP-ERR-4005" || $errorCode == "DEP-ERR-5003" || $errorCode == "DEP-ERR-5004" || $errorCode == "GRX-50072")
                        $statut = StatutRequeteEnum::ErreurARejouer->value;
                    $retour = array("statut" => $statut, "statusCode" => $statusCode, "message" => $message);
                } elseif ($statusCodeApple) {
//                    $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "[SUCCES]", SourceEnum::ApiApple,$order);
                    $tok = false;
                    $tnok = false;
                    $devices = $resultat["orders"][0]["deliveries"][0]["devices"] ?? "";
                    if ($devices) {
                        $retour = array("statusCode" => $statusCode);
                        foreach ($devices as $device) {
                            $imei = $device['deviceId'] ?? "";
                            $code = $device['devicePostStatus'] ?? "";
                            if ($code == "COMPLETE") {
                                $code = StatutRequeteEnum::Succes->value;
                                $message = "";
                                $tok = true;
                            } else {
                                $code = StatutRequeteEnum::Erreur->value;
                                $message = "[" . $device['devicePostStatus'] . "] " . ($device['devicePostStatusMessage'] ?? "");
                                $tnok = true;
                            }
                            $retour["devices"][] = array("imei" => $imei, "statut" => $code, "message" => $message);
                        }
                        if ($tok && $tnok)
                            $retour["statut"] = StatutRequeteEnum::SuccesPartiel->value;
                        elseif ($tok)
                            $retour["statut"] = StatutRequeteEnum::Succes->value;
                        else
                            $retour["statut"] = StatutRequeteEnum::Erreur->value;

                    } else { // En annulation, pas de devices
                        $code = $resultat['statusCode'] ?? "";
                        $orderPostStatus =  $resultat["orders"][0]["orderPostStatus"] ?? "";
                        $orderPostMessage =  $resultat["orders"][0]["orderPostStatusMessage"] ?? "";
                        if ($code == "COMPLETE")
                            $code = StatutRequeteEnum::Succes->value;
                        else {
                            $code = StatutRequeteEnum::Erreur->value;
                        }
                        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "Aucun terminal dans le code retour", SourceEnum::ApiApple,$order);
                        $retour = array("statut" => $code, "statusCode" => $statusCode, "code" => $orderPostStatus, "message" => $orderPostMessage);
                    }
                } elseif ($transactionIdApple) {
                    $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "[ERREUR] " . $errorCode, SourceEnum::ApiApple,$order);
                    $statut = StatutRequeteEnum::Erreur->value;
                    $errorCode = $resultat["errorCode"] ?? null;
                    if ($errorCode == "DEP-ERR-4003" || $errorCode == "DEP-ERR-4005" || $errorCode == "DEP-ERR-5003" || $errorCode == "DEP-ERR-5004" || $errorCode == "GRX-50072")
                        $statut = StatutRequeteEnum::ErreurARejouer->value;
                    $errorMessage = $resultat['errorMessage'] ?? "";
                    $retour = array("statut" => $statut, "statusCode" => $statusCode, "message" => "[" . $errorCode . "] " . $errorMessage);
                }
            } else {
                if ((int)$statusCode >= 500) {
                    $retour = array("statut" => StatutRequeteEnum::ErreurARejouer->value, "statusCode" => $statusCode, "code" => "", "message" => $content);
                    $this->logger->writeLogToDatabase(ProgrammeEnrolementEnum::Apple, $order, "[DETAILS][RESPONSE][AREJOUER]" . $content, null);
                } else {
                    $retour = array("statut" => StatutRequeteEnum::Erreur->value, "statusCode" => $statusCode, "code" => "", "message" => $content);
                    $this->logger->writeLogToDatabase(ProgrammeEnrolementEnum::Apple, $order, "[DETAILS][RESPONSE][ERREUR][" . $statusCode . "]".$message,null);
                }
            }
        } catch (\Exception $exception) {
            $retour = array("statut" => StatutRequeteEnum::Exception->value, "code" => $exception->getCode(), "message" => $exception->getMessage());
        }
        $this->logger->writeLog(LogLevelEnum::Debug, __METHOD__ . "[" . __LINE__ . "]", "[RETOUR] " . json_encode($retour), SourceEnum::ApiApple,$order);

        return $retour;

    }

    public function synchronizeTransaction(string $depResellerId, Orders $order): ?array
    {

        $orderId = $order->getId();
        $retour = array();
        $SHIP_TO = $_SERVER['DEP_SHIP_TO'];
        $DEP_URL_DETAILS = $_SERVER['DEP_URL_DETAILS'];

        $transactionId = sprintf("%s%s", $this->transactionPrefix,$orderId);

        $formFields = [
            'orderNumbers' => [$transactionId],
            'depResellerId' => $depResellerId,
            'requestContext' => [
                'langCode' => 'fr',
                'timeZone' => '-60',
                'shipTo' => $SHIP_TO
            ],
        ];

        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "[DETAILS][DEP][URL]" . $DEP_URL_DETAILS, SourceEnum::ApiApple,$order);
        $json = json_encode($formFields);
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "[DETAILS][DEP][BODY]" . $json, SourceEnum::ApiApple,$order);
        $this->logger->writeLogToDatabase(ProgrammeEnrolementEnum::Apple,$order,"[DETAILS][BODY]",$formFields);

        try {

            if ($this->bouchon != "1") {
                $response = $this->client->request(
                    'POST',
                    $DEP_URL_DETAILS, [
                    'headers' => ['Content-Type' => 'application/json', 'Accept-Encoding' => 'identity', "Authorization" => "Basic " . $this->encodedAuth],
                    'json' => $formFields,
                ],
                );
                $statusCode = $response->getStatusCode();
                $content = $response->getContent(false);
            } else {
                throw new \Exception("Non pris en charge");
            }

            // Exemple de retour {
            //  "statusCode" : "COMPLETE",
            //  "orders" : [ {
            //    "orderNumber" : "D2302136",
            //    "deliveries" : [ {
            //      "deliveryNumber" : "0",
            //      "shipDate" : "2023-05-02T15:28:14Z",
            //      "devices" : [ {
            //        "deviceId" : "355193488071485"
            //      } ]
            //    } ],
            //    "orderDate" : "2023-05-02T15:28:14Z",
            //    "orderType" : "OR",
            //    "customerId" : "1501758"
            //  } ],
            //  "respondedOn" : "2023-05-03T12:18:16Z"
            //}


            $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "[DETAILS][DEP][STATUS]" . $statusCode, SourceEnum::ApiApple,$order);
            $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "[DETAILS][DEP][RESPONSE]" . $content, SourceEnum::ApiApple,$order);

            if ($statusCode == "200") {
                $resultat = json_decode($content, true);
                $this->logger->writeLogToDatabase(ProgrammeEnrolementEnum::Apple,$order,"[DETAILS][RESPONSE][".$statusCode."]",$resultat);

                $errorCode = $resultat["errorCode"] ?? null;
                $checkTransactionErrorResponse = $resultat["showOrderErrorResponse"] ?? null;
                if ($errorCode) {
                    $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "[ERREUR] " . $errorCode, SourceEnum::ApiApple,$order);
                    $statut = StatutRequeteEnum::Erreur->value;
                    if ($errorCode == "DEP-ERR-4003" || $errorCode == "DEP-ERR-4005" || $errorCode == "DEP-ERR-5003" || $errorCode == "DEP-ERR-5004" || $errorCode == "GRX-50072")
                        $statut = StatutRequeteEnum::ErreurARejouer->value;
                    $errorMessage = $resultat['errorMessage'] ?? "";
                    $retour = array("statut" => $statut, "statusCode" => $statusCode, "message" => "[" . $errorCode . "] " . $errorMessage);
                } elseif ($checkTransactionErrorResponse) {
                    $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "[ERREUR checkTransactionErrorResponse", SourceEnum::ApiApple,$order);
                    $message = "";
                    $statusCode = "";
                    foreach ($checkTransactionErrorResponse as $checkTransactionError) {
                        $errorCode = $checkTransactionError['errorCode'] ?? "";
                        $errorMessage = $checkTransactionError['errorMessage'] ?? "";
                        if ($message == "") $message = "[" . $errorCode . "] " . $errorMessage; else $message .= "|[" . $errorCode . "] " . $errorMessage;
                    }
                    $statut = StatutRequeteEnum::Erreur->value;
                    if ($errorCode == "DEP-ERR-4003" || $errorCode == "DEP-ERR-4005" || $errorCode == "DEP-ERR-5003" || $errorCode == "DEP-ERR-5004" || $errorCode == "GRX-50072")
                        $statut = StatutRequeteEnum::ErreurARejouer->value;
                    $retour = array("statut" => $statut, "statusCode" => $statusCode, "message" => $message);
                } else {
                    $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "[SUCCES]", SourceEnum::ApiApple,$order);
                    $tok = false;
                    $tnok = false;
                    $devices = $resultat["orders"][0]["deliveries"][0]["devices"] ?? "";
                    if ($devices) {
                        $retour = array("statusCode" => $statusCode);
                        foreach ($devices as $device) {
                            $imei = $device['deviceId'] ?? "";
                            $retour["devices"][] = $imei;
                        }
                        $retour["statut"] = StatutRequeteEnum::Succes->value;
                    }
                }
            } else {
                if ((int)$statusCode >= 500) {
                    $retour = array("statut" => StatutRequeteEnum::ErreurARejouer->value, "statusCode" => $statusCode, "code" => "", "message" => $content);
                    $this->logger->writeLogToDatabase(ProgrammeEnrolementEnum::Apple, $order, "[DETAILS][RESPONSE][AREJOUER]" . $content, null);
                } else {
                    $retour = array("statut" => StatutRequeteEnum::Erreur->value, "statusCode" => $statusCode, "code" => "", "message" => $content);
                    $this->logger->writeLogToDatabase(ProgrammeEnrolementEnum::Apple, $order, "[DETAILS][RESPONSE][ERREUR][" . $statusCode . "]".$message,null);
                }
            }
        } catch (\Exception $exception) {
            $retour = array("statut" => StatutRequeteEnum::Exception->value, "code" => $exception->getCode(), "message" => $exception->getMessage());
        }
        $this->logger->writeLog(LogLevelEnum::Debug, __METHOD__ . "[" . __LINE__ . "]", "[RETOUR] " . json_encode($retour), SourceEnum::ApiApple,$order);

        return $retour;
    }

    public function updateDateCommand(string $depResellerId, string $orderId): ?array
    {

        $retour = array();
        $SHIP_TO = $_SERVER['DEP_SHIP_TO'];
        $DEP_URL_DETAILS = $_SERVER['DEP_URL_DETAILS'];

        $transactionId = sprintf("%s%s", $this->transactionPrefix,$orderId);

        $formFields = [
            'orderNumbers' => [$transactionId],
            'depResellerId' => $depResellerId,
            'requestContext' => [
                'langCode' => 'fr',
                'timeZone' => '-60',
                'shipTo' => $SHIP_TO
            ],
        ];
        try {

            if ($this->bouchon != "1") {
                $response = $this->client->request(
                    'POST',
                    $DEP_URL_DETAILS, [
                    'headers' => ['Content-Type' => 'application/json', 'Accept-Encoding' => 'identity', "Authorization" => "Basic " . $this->encodedAuth],
                    'json' => $formFields,
                ],
                );
                $statusCode = $response->getStatusCode();
                $content = $response->getContent(false);

            } else {
                throw new \Exception("Non pris en charge");
            }

            // Exemple de retour {
            //  "statusCode" : "COMPLETE",
            //  "orders" : [ {
            //    "orderNumber" : "D2302136",
            //    "deliveries" : [ {
            //      "deliveryNumber" : "0",
            //      "shipDate" : "2023-05-02T15:28:14Z",
            //      "devices" : [ {
            //        "deviceId" : "355193488071485"
            //      } ]
            //    } ],
            //    "orderDate" : "2023-05-02T15:28:14Z",
            //    "orderType" : "OR",
            //    "customerId" : "1501758"
            //  } ],
            //  "respondedOn" : "2023-05-03T12:18:16Z"
            //}


            if ($statusCode == "200") {
                $resultat = json_decode($content, true);

                $errorCode = $resultat["errorCode"] ?? null;
                $checkTransactionErrorResponse = $resultat["showOrderErrorResponse"] ?? null;
                if ($errorCode) {
                    $statut = StatutRequeteEnum::Erreur->value;
                    if ($errorCode == "DEP-ERR-4003" || $errorCode == "DEP-ERR-4005" || $errorCode == "DEP-ERR-5003" || $errorCode == "DEP-ERR-5004" || $errorCode == "GRX-50072")
                        $statut = StatutRequeteEnum::ErreurARejouer->value;
                    $errorMessage = $resultat['errorMessage'] ?? "";
                    $retour = array("statut" => $statut, "statusCode" => $statusCode, "message" => "[" . $errorCode . "] " . $errorMessage);
                } elseif ($checkTransactionErrorResponse) {
                    $message = "";
                    $statusCode = "";
                    foreach ($checkTransactionErrorResponse as $checkTransactionError) {
                        $errorCode = $checkTransactionError['errorCode'] ?? "";
                        $errorMessage = $checkTransactionError['errorMessage'] ?? "";
                        if ($message == "") $message = "[" . $errorCode . "] " . $errorMessage; else $message .= "|[" . $errorCode . "] " . $errorMessage;
                    }
                    $statut = StatutRequeteEnum::Erreur->value;
                    if ($errorCode == "DEP-ERR-4003" || $errorCode == "DEP-ERR-4005" || $errorCode == "DEP-ERR-5003" || $errorCode == "DEP-ERR-5004" || $errorCode == "GRX-50072")
                        $statut = StatutRequeteEnum::ErreurARejouer->value;
                    $retour = array("statut" => $statut, "statusCode" => $statusCode, "message" => $message);
                } else {
                    $tok = false;
                    $tnok = false;
                    $dateC = $resultat["orders"][0]["orderDate"] ?? "";
                    $retour["statut"] = StatutRequeteEnum::Succes->value;
                    $retour["date"] = $dateC;
                }
            } else {
                if ((int)$statusCode >= 500)
                    $retour = array("statut" => StatutRequeteEnum::ErreurARejouer->value, "statusCode" => $statusCode, "code" => "", "message" => $content);
                else
                    $retour = array("statut" => StatutRequeteEnum::Erreur->value, "statusCode" => $statusCode, "code" => "", "message" => $content);
            }
        } catch (\Exception $exception) {
            $retour = array("statut" => StatutRequeteEnum::Exception->value, "code" => $exception->getCode(), "message" => $exception->getMessage());
        }

        return $retour;
    }

}
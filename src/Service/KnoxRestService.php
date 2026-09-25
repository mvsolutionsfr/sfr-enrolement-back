<?php

namespace App\Service;

use App\Entity\Orders;
use App\Entity\Terminal;
use App\Toolbox\LogLevelEnum;
use App\Toolbox\MetricIndicatorEnum;
use App\Toolbox\ProgrammeEnrolementEnum;
use App\Toolbox\SourceEnum;
use App\Toolbox\TypeEnrolementEnum;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use App\Toolbox\StatutRequeteEnum;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

//use function Sodium\add;

class KnoxRestService extends AbstractRestService
{
    private $client;
    private string $encodedAuth;
    private string $bouchon;
    private string $transactionPrefix;

    public function __construct(HttpClientInterface $client, LoggerESService $logger, SessionService $session, MetricsService $metricsService)
    {
        parent::__construct(null, $logger, $session, $metricsService);
        $this->client = $client;
        $userauth = $_SERVER['KNOX_PAWS_USER'] ?? "";
        $passwdauth = $_SERVER['KNOX_PAWS_PASSWORD'] ?? "";
        $this->encodedAuth = base64_encode($userauth . ":" . $passwdauth);
        $this->bouchon = $_SERVER['KNOX_BOUCHON_SERVER'] ?? "";
        $this->transactionPrefix = $_SERVER['KNOX_PREFIX_ID'] ?? "";
    }

    protected function processAppel(TypeEnrolementEnum $orderType, string $vendorId, string $customerId, Orders $order, string $removeTransactionId, array $terminaux, string $transactionId, ?\DateTimeInterface $dateCreation = null): ?array
    {
        $orderNumber = $order->getId();
        $KNOX_URL_ENROLL = $_SERVER['KNOX_URL_ENROLL'];
        $KNOX_URL_DELETE = $_SERVER['KNOX_URL_DELETE'];
        $KNOX_RESELLER_ID = $_SERVER['KNOX_RESELLER_ID'];
        $KNOX_API_TOKEN = $_SERVER['KNOX_API_TOKEN'];
        $KNOX_URL = "";

        $mode = "New";
        $ot = "OR";
        if ($orderType == TypeEnrolementEnum::Desinscription) {
            $KNOX_URL = $KNOX_URL_DELETE;
            $mode = 'Delete';
            $ot = "RE";
            $transactionId = sprintf("%sR%s", $this->transactionPrefix, $transactionId);
        } elseif ($orderType == TypeEnrolementEnum::Annulation) {
            $KNOX_URL = $KNOX_URL_DELETE;
            $mode = 'Delete';
            $ot = "VD";
            $transactionId = sprintf("%sA%s", $this->transactionPrefix, $orderNumber);
        } else {
            $KNOX_URL = $KNOX_URL_ENROLL;
            $transactionId = sprintf("%s%s", $this->transactionPrefix, $orderNumber);
        }

        $formFields = [
            'transactionId' => $transactionId,
            'resellerId' => $KNOX_RESELLER_ID,
            'vendorId' => $vendorId,
            'customerId' => $customerId,
            'transactionType' => $mode,
        ];

        if ($removeTransactionId === "") {
            $tt = $transactionId . "-";
            $formFields['devices'] = [
                'type' => 'IMEI',
                'list' => [],
            ];
            foreach ($terminaux as $terminal) {
                /** @var Terminal $terminal */
                if ($terminal->getNumeroSerie() != null && $terminal->getNumeroSerie() != "") {
                    $tt = $tt . $terminal->getNumeroSerie() . "-";
                    $formFields["devices"]["type"] = 'SN';
                    $formFields["devices"]["list"][] = $terminal->getNumeroSerie();
                } else {
                    $tt = $tt . $terminal->getNumeroIMEI() . "-";
                    $formFields["devices"]["type"] = 'IMEI';
                    $formFields["devices"]["list"][] = $terminal->getNumeroIMEI();
                }
            }
        } else {
            $formFields["removeTransactionId"] = $removeTransactionId;
        }

        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "[REQUEST][KNOX][URL]" . $KNOX_URL_ENROLL, SourceEnum::ApiSamsung, $order);
        $json = json_encode($formFields);
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "[REQUEST][KNOX][BODY]" . $json, SourceEnum::ApiSamsung, $order);
        $this->logger->writeLogToDatabase(ProgrammeEnrolementEnum::Knox,$order,"[".$ot."][BODY]",$formFields);

        try {
            // TODO Décommenter
            if ($this->bouchon != "1") {
                $response = $this->client->request(
                    'PUT',
                    $KNOX_URL, [
                    'headers' => ['Content-Type' => 'application/json', 'X-WSM-API-TOKEN' => $KNOX_API_TOKEN, "Authorization" => "Basic " . $this->encodedAuth],
                    'json' => $formFields,
                ],
                );

                $statusCode = $response->getStatusCode();
                $content = $response->getContent(false);
            } else {
                $statusCode = 200;
                $content = '{ "transactionId": "' . $tt . '","state": "Progress","code": 2000000,"message": "SUCCESS"}';
                //               $content = '{"transactionId":"' .$tt.'","state":"Rejected","code":4042104,"message":"CUSTOMER_NOT_FOUND","data":"customer not found. (customer id : [704304108])"}';
//                $statusCode = 400;
//                $content = '{ "transactionId": "' . $transactionId . '","state": "Rejected","code": 4002101,"message": "RESELLER_ID_MISSING","data": "reseller id is missing"}';
            }
            $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "[ENROL][KNOX][RESPONSE]" . $content, SourceEnum::ApiSamsung, $order);


            if ($statusCode < 500) {
                $retour = json_decode($content, true);
                $this->logger->writeLogToDatabase(ProgrammeEnrolementEnum::Knox,$order,"[".$ot."][RESPONSE][".$statusCode."]",$retour);
                $resultat = array();
                $code = $retour['code'] ?? "";
                $state = $retour['state'] ?? "";
                $message = $retour['message'] ?? "";
                $transactionId = $retour['transactionId'] ?? "";
                $data = $retour['data'] ?? "";

                if ($statusCode == 200 && $code == 2000000) {
                    $resultat['statut'] = StatutRequeteEnum::Succes->value;
                    $resultat["statusCode"] = $statusCode;
                    $resultat["statusMessage"] = "[" . $code . "] " . $message;
                    $resultat["transactionId"] = $transactionId;
                } else {
                    $resultat['statut'] = StatutRequeteEnum::Erreur->value;
                    $resultat["statusCode"] = $statusCode;
                    $resultat["statusMessage"] = "[" . $code . "] " . $data;
                    $resultat["transactionId"] = $transactionId;
                }
            } else {
                $resultat = array("statut" => StatutRequeteEnum::ErreurARejouer->value, "statusCode" => $statusCode, "code" => "", "statusMessage" => $content);
                $this->logger->writeLogToDatabase(ProgrammeEnrolementEnum::Knox, $order, "[".$ot."][RESPONSE][AREJOUER]" . $content, null);
            }
        } catch (\Exception $exception) {
            $this->logger->writeLog(LogLevelEnum::Critique, __METHOD__ . "[" . __LINE__ . "]", $exception->getMessage(), SourceEnum::ApiSamsung, $order);
            $resultat = array("statut" => StatutRequeteEnum::Exception->value, "code" => $exception->getCode(), "statusMessage" => $exception->getMessage());
        }

        return $resultat;
    }

    public function checkTransactionStatus(string $vendorId, string $deviceEnrollmentTransactionId, ?Orders $order = null): ?array
    {
        /// retour["statut"], statut global de la requete (valeur de l'énumération StatutRequeteEnum)
        /// retour["code"], code d'exception eventuel
        /// retour["statusCode"], code de retour de la requete d'appel
        /// retour["message"], message d'erreur éventuel
        /// retour["devices"]["imei"], numéro IMEI du controle de status
        /// retour["devices"]["statut"], code statut de l'IMEI (valeur de l'énumération StatutRequeteEnum)
        /// retour["devices"]["message"], message d'erreur éventuel

        $SHIP_TO = $_SERVER['DEP_SHIP_TO'];
        $KNOX_URL_STATUS = $_SERVER['KNOX_URL_STATUS'];
        $KNOX_API_TOKEN = $_SERVER['KNOX_API_TOKEN'];
        $KNOX_RESELLER_ID = $_SERVER['KNOX_RESELLER_ID'];

        if ($vendorId == "")
            $url = $KNOX_URL_STATUS . '?resellerId=' . $KNOX_RESELLER_ID . '&transactionId=' . $deviceEnrollmentTransactionId;
        else
            $url = $KNOX_URL_STATUS . '?resellerId=' . $KNOX_RESELLER_ID . '&vendorId=' . $vendorId . '&transactionId=' . $deviceEnrollmentTransactionId;

        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "[STATUS][GET]" . $url, SourceEnum::ApiSamsung, $order);
        $this->logger->writeLogToDatabase(ProgrammeEnrolementEnum::Knox,$order,"[STATUS][URL] ".$url,null);

        try {

            if ($this->bouchon != "1") {
                $response = $this->client->request(
                    'GET',
                    $url, [
                        'headers' => ['Content-Type' => 'application/json', 'X-WSM-API-TOKEN' => $KNOX_API_TOKEN, "Authorization" => "Basic " . $this->encodedAuth],
                        'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT]
                );
                $statusCode = $response->getStatusCode();
                $content = $response->getContent(false);
            } else {
                $statusCode = 200;

                $tid = explode("-", $deviceEnrollmentTransactionId);
                $deb = $tid[0];
                array_splice($tid, 0, 1);

                if ($deb == $this->transactionPrefix . "R") {
                    $content_ok = [
                        "totalCount" => count($tid),
                        "totalPage" => 1,
                        "pageNum" => 0,
                        "transactions" => [[
                            "transactionId" => $deviceEnrollmentTransactionId,
                            "state" => "Complete",
                            "type" => "Delete",
                            "devices" => []
                        ]]
                    ];
                    $content = $content_ok;
                } elseif ($deb == $this->transactionPrefix . "A") {
                    $content_ok = [
                        "totalCount" => count($tid),
                        "totalPage" => 1,
                        "pageNum" => 0,
                        "transactions" => [[
                            "transactionId" => $deviceEnrollmentTransactionId,
                            "state" => "Complete",
                            "type" => "Delete",
                            "devices" => []
                        ]]];
                    $content = $content_ok;
                } else {
                    $content_ok = [
                        "totalCount" => count($tid),
                        "totalPage" => 1,
                        "pageNum" => 0,
                        "transactions" => [[
                            "transactionId" => $deviceEnrollmentTransactionId,
                            "state" => "Complete",
                            "type" => "Put",
                            "devices" => [],
                        ]]
                    ];
//                    $content_erreur = '{"totalCount":2,"totalPage":1,"pageNum":0,"transactions":[{"transactionId":"7500657464","state":"Complete","type":"Put","devices":[{"imei":"807212627606673"},{"imei":"868074057439230"},{"imei":"356557082621540","code":4092100,"message":"RESELLER_DEVICE_ALREADY_EXISTS","data":"device imei[097436145498341] already exists"}]}]}';
                }
                foreach ($tid as $imei) {
                    if ($imei == "AN" || $imei == "RE" || $imei == "") continue;
                    $content_ok["transactions"][0]["devices"][] = array("serialNumber" => $imei);
                }
                $content = json_encode($content_ok);
            }
            $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "[STATUS]" . $statusCode, SourceEnum::ApiSamsung, $order);
            $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "[STATUS][RESPONSE]" . $content, SourceEnum::ApiSamsung, $order);

            if ($statusCode == "200") {
                $statutRetour = StatutRequeteEnum::Succes;
                $resultat = json_decode($content, true);
                $this->logger->writeLogToDatabase(ProgrammeEnrolementEnum::Knox,$order,"[STATUS][RESPONSE][".$statusCode."]",$resultat);
                $devices = $resultat["transactions"][0]["devices"];
                $retour = array();
                $one_device_ok = false;
                $one_device_ko = false;
                foreach ($devices as $device) {
                    $imei = $device['imei'] ?? "";
                    $sn = $device['serialNumber'] ?? "";
                    $code = $device['code'] ?? "";
                    $statutImei = StatutRequeteEnum::Succes->value;
                    if ($code != "") {
                        $one_device_ko = true;
                        $statutImei = StatutRequeteEnum::Erreur->value;
                    } else {
                        $one_device_ok = true;
                    }
                    $errorMessage = $device['data'] ?? "";
                    $retour["devices"][] = array("imei" => $imei, "sn" => $sn, "statut" => $statutImei, "message" => $errorMessage);
                }
                if ($one_device_ok && $one_device_ko)
                    $statutRetour = StatutRequeteEnum::SuccesPartiel;
                elseif ($one_device_ko)
                    $statutRetour = StatutRequeteEnum::Erreur;

                $retour["statut"] = $statutRetour->value;
                $retour["statusCode"] = $statusCode;
            } else {
                if ($statusCode >= 500) {
                    $retour = array("statut" => StatutRequeteEnum::ErreurARejouer->value, "statusCode" => $statusCode, "code" => "", "message" => $content);
                    $this->logger->writeLogToDatabase(ProgrammeEnrolementEnum::Knox, $order, "[STATUS][RESPONSE][AREJOUER]" . $content, null);
                } else {
                    $retour = array("statut" => StatutRequeteEnum::Erreur->value, "statusCode" => $statusCode, "code" => "", "message" => $content);
                    $this->logger->writeLogToDatabase(ProgrammeEnrolementEnum::Knox, $order, "[STATUS][RESPONSE][ERREUR]" . $content, null);
                }
            }
        } catch (\Exception $exception) {
            $this->logger->writeLog(LogLevelEnum::Critique, __METHOD__ . "[" . __LINE__ . "]", $exception->getMessage(), SourceEnum::ApiSamsung, $order);
            $retour = array("statut" => StatutRequeteEnum::Exception->value, "code" => $exception->getCode(), "message" => $exception->getMessage());
        }

        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "[RETOUR] " . json_encode($retour), SourceEnum::ApiSamsung, $order);

        return $retour;
    }

    public function callServeur(string $methode, string $url, string $body): array
    {
        $SHIP_TO = $_SERVER['DEP_SHIP_TO'];
        $KNOX_URL_STATUS = $_SERVER['KNOX_URL_STATUS'];
        $KNOX_API_TOKEN = $_SERVER['KNOX_API_TOKEN'];
        $KNOX_RESELLER_ID = $_SERVER['KNOX_RESELLER_ID'];
//        $KNOX_URL_ENROLL = 'https://openrest.services.pack/api/SamsungKnox/Knox/1.0/reseller/devices/status?resellerId=3915551481&vendorId=7854573063&transactionId=T23018422';
//        $KNOX_URL_ENROLL = 'https://openrest.services.pack/api/SamsungKnox/Knox/1.0/reseller/devices';

        $httpClient = HttpClient::create([
            'headers' => ['Content-Type' => 'application/json', 'X-WSM-API-TOKEN' => $KNOX_API_TOKEN, "Authorization" => "Basic " . $this->encodedAuth],
            #           'body' => '{"resellerId":"3915551481","transactionType":"New","devices":{"type":"IMEI","list":["352566270397071"]},"customerId":"7723828581","vendorId":"7854573063","transactionId":"T23018422"}'
        ]);
        $response = $httpClient->request('GET', $url);
        $statusCode = $response->getStatusCode();
        $content = $response->getContent(false);

        $retour = array($statusCode, $content);
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "[RETOUR][STATUS] " . $statusCode, SourceEnum::ApiSamsung, null);
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "[RETOUR][CONTENT] " . $content, SourceEnum::ApiSamsung, null);
        return $retour;
    }

    public function testServeur(string $vendorId, string $deviceEnrollmentTransactionId): array
    {
        $KNOX_URL_STATUS = $_SERVER['KNOX_URL_STATUS'];
        $KNOX_API_TOKEN = $_SERVER['KNOX_API_TOKEN'];
        $KNOX_RESELLER_ID = $_SERVER['KNOX_RESELLER_ID'];
        $url = $KNOX_URL_STATUS . '?resellerId=' . $KNOX_RESELLER_ID . '&vendorId=' . $vendorId . '&transactionId=' . $deviceEnrollmentTransactionId;

        $response = $this->client->request(
            'GET',
            $url, [
                'headers' => ['Content-Type' => 'application/json', 'X-WSM-API-TOKEN' => $KNOX_API_TOKEN, "Authorization" => "Basic " . $this->encodedAuth],
                'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT]
        );
        $statusCode = $response->getStatusCode();
        $content = $response->getContent(false);
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "[RETOUR][STATUS] " . $statusCode, SourceEnum::ApiSamsung, null);
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "[RETOUR][CONTENT] " . $content, SourceEnum::ApiSamsung, null);

        $retour = array($statusCode, $content);
        return $retour;
    }
}

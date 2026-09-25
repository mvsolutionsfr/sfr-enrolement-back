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
use Symfony\Contracts\HttpClient\HttpClientInterface;
use App\Toolbox\StatutRequeteEnum;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

//use function Sodium\add;

class ZTRestService extends AbstractRestService
{
    private $utc;
    private $client;
    private $encodedAuth;
    private $ztURLProvisioning;
    private $ztResellerId;
    private string $bouchon;

    public function __construct(HttpClientInterface $client, LoggerESService $logger, SessionService $session, MetricsService $metricsService)
    {
        parent::__construct(null, $logger, $session, $metricsService);
        $this->client = $client;
        $this->ztURLProvisioning = $_SERVER['ZT_URL_PROVISIONING'] ?? "";
        $this->ztResellerId = $_SERVER['ZT_RESELLER_ID'] ?? "";
        $this->bouchon = $_SERVER['ZT_BOUCHON_SERVER'] ?? "";
        $userauth = $_SERVER['ZT_PAWS_USER'] ?? "";
        $passwdauth = $_SERVER['ZT_PAWS_PASSWORD'] ?? "";
        $this->encodedAuth = base64_encode($userauth . ":" . $passwdauth);


    }

    public function createCustomerId(string $getResellerId, string $getRaisonSociale, string $getEmail): string
    {
        $customerId = "";
        $urlProvisioning = $this->ztURLProvisioning . "/partners/" . $getResellerId . "/customers";

//[REQUEST][CREATE CLIENT] PartnerId [1155496840] companyName [SYNAMBU 1] email [gestionknox@gmail.com]
//[REQUEST][JSON] {"customer":{"companyName":"SYNAMBU 1","ownerEmails":["gestionknox@gmail.com"]}}
//[REQUEST][URL] http://openrest.services.prod/api/google/androiddeviceprovisioning/1.0/partners/1155496840/customers
//[RESPONSE][CREATE CLIENT] {  "companyId": "1793224283",  "companyName": "SYNAMBU 1",  "name": "partners/1155496840/customers/1793224283"}

        $this->metricsService->createMetric(MetricIndicatorEnum::createId, "Google", "", "");

        $token = $this->getToken();
        if ($token == "") return "";

        $formFields = [
            "customer" => [
                "companyName" => $getRaisonSociale,
                "ownerEmails" => [
                    $getEmail
                ]
            ]
        ];

        $this->logger->writeLogToDatabase(ProgrammeEnrolementEnum::Zerotouch, null, "[CUSTOMERID][BODY]",$formFields);

        if ($this->bouchon != "1") {
            $response = $this->client->request(
                'POST',
                $urlProvisioning, [
                'headers' => ['Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $token, "Basic-Authorization" => "Basic " . $this->encodedAuth],
                'json' => $formFields,
            ],
            );
            $statusCode = $response->getStatusCode();
            $content = $response->getContent(false);
        } else {
            $statusCode = 200;
            $c = rand(100000, 200000);
            $content = '{  "companyId": "' . $c . '",  "companyName": "' . $getRaisonSociale . '",  "name": "partners/1155496840/customers/' . $c . '"}';
        }

        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "[STATUS][RESPONSE][" . $statusCode . "] " . $content, SourceEnum::ApiGoogle);

        if ($statusCode == 200) {
            $retour = json_decode($content, true);
            $this->logger->writeLogToDatabase(ProgrammeEnrolementEnum::Zerotouch, null, "[CUSTOMERID][RESPONSE]",$retour);
            $customerId = $retour["companyId"];
        } else {
            $this->logger->writeLogToDatabase(ProgrammeEnrolementEnum::Zerotouch, null, "[CUSTOMERID][ERREUR] ".$content,null);
        }


        return $customerId;
    }

    protected function processAppel(TypeEnrolementEnum $orderType, string $depResellerId, string $customerId, Orders $order, string $removeTransactionId, array $terminaux, string $transactionId, ?\DateTimeInterface $dateCreation = null): ?array
    {
        $token = $this->getToken($order);

        if ($token == "") {
            $this->logger->writeLog(LogLevelEnum::Erreur, __METHOD__ . "[" . __LINE__ . "]", "Erreur récupération jeton", SourceEnum::ApiGoogle, $order);
            return array("statut" => StatutRequeteEnum::ErreurARejouer->value, "statusCode" => "500", "code" => "", "statusMessage" => "Erreur récupération du jeton");
        }

        $mode = "OR";
        $tt="";
        if ($orderType == TypeEnrolementEnum::Inscription) {
            $tt = "OR";
            $formFields = ['claims' => []];
            foreach ($terminaux as $terminal) {
                /** @var Terminal $terminal */

                if ($terminal->getNumeroSerie() != null && $terminal->getNumeroSerie() != "") {
                    $tt .= '-' . $terminal->getNumeroSerie();
                    if ($terminal->getNumeroIMEI() != null && $terminal->getNumeroIMEI() != "")
                        $formFields["claims"][] = array("customerId" => $customerId, "sectionType" => "SECTION_TYPE_ZERO_TOUCH", "deviceIdentifier" => array("imei" => $terminal->getNumeroIMEI(), "manufacturer" => $terminal->getFabricant()->getCode()));
                    else
                        $formFields["claims"][] = array("customerId" => $customerId, "sectionType" => "SECTION_TYPE_ZERO_TOUCH", "deviceIdentifier" => array("serialNumber" => $terminal->getNumeroSerie(), "manufacturer" => $terminal->getFabricant()->getCode()));
                } else {
                    $tt .= '-' . $terminal->getNumeroIMEI();
                    $formFields["claims"][] = array("customerId" => $customerId, "sectionType" => "SECTION_TYPE_ZERO_TOUCH", "deviceIdentifier" => array("imei" => $terminal->getNumeroIMEI(), "manufacturer" => $terminal->getFabricant()->getCode()));
                }
            }
            $urlProvisioning = $this->ztURLProvisioning . "/partners/" . $this->ztResellerId . "/devices:claimAsync";

        } else {
            $formFields = ['unclaims' => []];
            if ($orderType == TypeEnrolementEnum::Desinscription) $tt = "RE"; else $tt = "AN";
            $mode = $tt;
            foreach ($terminaux as $terminal) {
                /** @var Terminal $terminal */
                if ($terminal->getNumeroSerie() != null && $terminal->getNumeroSerie() != "") {
                    $tt .= '-' . $terminal->getNumeroSerie();
                    if ($terminal->getNumeroIMEI() != null && $terminal->getNumeroIMEI() != "")
//                        $formFields["unclaims"][] = array("sectionType" => "SECTION_TYPE_ZERO_TOUCH", "deviceIdentifier" => array("serialNumber" => $terminal->getNumeroSerie(), "imei" => $terminal->getNumeroIMEI(), "manufacturer" => $terminal->getFabricant()->getCode()));
                        $formFields["unclaims"][] = array("sectionType" => "SECTION_TYPE_ZERO_TOUCH", "deviceIdentifier" => array("imei" => $terminal->getNumeroIMEI(), "manufacturer" => ""));
                    else
//                        $formFields["unclaims"][] = array("sectionType" => "SECTION_TYPE_ZERO_TOUCH", "deviceIdentifier" => array("serialNumber" => $terminal->getNumeroSerie(), "manufacturer" => $terminal->getFabricant()->getCode()));
                        $formFields["unclaims"][] = array("sectionType" => "SECTION_TYPE_ZERO_TOUCH", "deviceIdentifier" => array("serialNumber" => $terminal->getNumeroSerie(), "manufacturer" => ""));
                } else {
//                    $formFields["unclaims"][] = array("sectionType" => "SECTION_TYPE_ZERO_TOUCH", "deviceIdentifier" => array("imei" => $terminal->getNumeroIMEI(), "manufacturer" => $terminal->getFabricant()->getCode()));
                    $tt .= '-' . $terminal->getNumeroIMEI();
                    $formFields["unclaims"][] = array("sectionType" => "SECTION_TYPE_ZERO_TOUCH", "deviceIdentifier" => array("imei" => $terminal->getNumeroIMEI(), "manufacturer" => ""));
                }
            }

            $urlProvisioning = $this->ztURLProvisioning . "/partners/" . $this->ztResellerId . "/devices:unclaimAsync";
        }
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "[REQUEST][ZT][URL]" . $urlProvisioning, SourceEnum::ApiGoogle, $order);

        $json = json_encode($formFields);
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "[REQUEST][ZT][BODY]" . $json, SourceEnum::ApiGoogle,$order);
        $this->logger->writeLogToDatabase(ProgrammeEnrolementEnum::Zerotouch,$order,"[".$mode."][BODY]",$formFields);

        try {
            if ($this->bouchon != "1") {
                $response = $this->client->request(
                    'POST',
                    $urlProvisioning, [
                    'headers' => ['Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $token, "Basic-Authorization" => "Basic " . $this->encodedAuth],
                    'json' => $formFields,
                ],
                );
                $statusCode = $response->getStatusCode();
                $content = $response->getContent(false);
            } else {
                $statusCode = 200;
                $content = '{  "name": "' . $tt . '"}';
            }

            $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "[".$mode."][RESPONSE]" . $content, SourceEnum::ApiGoogle,$order);


            if ($statusCode == 200) {

                $retour = json_decode($content, true);
                $this->logger->writeLogToDatabase(ProgrammeEnrolementEnum::Zerotouch,$order,"[".$mode."][RESPONSE]",$retour);
                $transactionId = $retour['name'] ?? "";

                if ($transactionId) {
                    $resultat['statut'] = StatutRequeteEnum::Succes->value;
                    $resultat["statusCode"] = $statusCode;
                    $resultat["statusMessage"] = "";
                    $resultat["transactionId"] = $transactionId;
                } else {
                    $resultat['statut'] = StatutRequeteEnum::Erreur->value;
                    $resultat["statusCode"] = $statusCode;
                    $resultat["statusMessage"] = $content;
                    $resultat["transactionId"] = "";
                }
            } else {
                if ((int)$statusCode >= 500) {
                    $retour = array("statut" => StatutRequeteEnum::ErreurARejouer->value, "statusCode" => $statusCode, "code" => "", "message" => $content);
                    $this->logger->writeLogToDatabase(ProgrammeEnrolementEnum::Zerotouch, $order, "[".$mode."][RESPONSE][AREJOUER]" . $content, null);
                } else {
                    $retour = array("statut" => StatutRequeteEnum::Erreur->value, "statusCode" => $statusCode, "code" => "", "message" => $content);
                    $this->logger->writeLogToDatabase(ProgrammeEnrolementEnum::Zerotouch, $order, "[".$mode."][RESPONSE][ERREUR][" . $statusCode . "]".$message,null);
                }
            }
        } catch (\Exception $exception) {
            $this->logger->writeLog(LogLevelEnum::Critique, __METHOD__ . "[" . __LINE__ . "]", "Exception " . $exception->getMessage(), SourceEnum::ApiGoogle,$order);
            $resultat = array("statut" => StatutRequeteEnum::Exception->value, "statusCode" => "", "code" => $exception->getCode(), "statusMessage" => $exception->getMessage());
        }

        $this->logger->writeLog(LogLevelEnum::Debug, __METHOD__ . "[" . __LINE__ . "]", "[RETOUR] " . json_encode($resultat), SourceEnum::ApiGoogle,$order);

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

        $urlProvisioning = $this->ztURLProvisioning . "/" . $deviceEnrollmentTransactionId;
        $this->logger->writeLog(LogLevelEnum::Debug, __METHOD__ . "[" . __LINE__ . "]", "[STATUS][GET]" . $urlProvisioning, SourceEnum::ApiGoogle,$order);
        $this->logger->writeLogToDatabase(ProgrammeEnrolementEnum::Zerotouch,$order,"[STATUS][URL] ".$urlProvisioning,null);

        try {
            // TODO Décommenter
            $token = $this->getToken($order);
            if ($token == "") {
                $this->logger->writeLog(LogLevelEnum::Erreur, __METHOD__ . "[" . __LINE__ . "]", "Erreur récupération jeton", SourceEnum::ApiGoogle,$order);
                return array("statut" => StatutRequeteEnum::ErreurARejouer->value, "statusCode" => "500", "code" => "", "message" => "Erreur récupération du jeton");
            }
            if ($this->bouchon != "1") {
                $response = $this->client->request(
                    'GET',
                    $urlProvisioning, [
                    'headers' => ['Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $token, "Basic-Authorization" => "Basic " . $this->encodedAuth]
                ],
                );

                $statusCode = $response->getStatusCode();
                $content = $response->getContent(false);
            } else {
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
                        $resultat["response"]["perDeviceStatus"][] = $device;
                    }
                    $content = json_encode($resultat);
                }
            }
            $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "[STATUS][RESPONSE]" . $content, SourceEnum::ApiGoogle,$order);
            $retour = array("statut" => "0", "statusCode" => "", "code" => "", "message" => "");
            if ($statusCode == "200") {
                $statutRetour = StatutRequeteEnum::Erreur;
                $resultat = json_decode($content, true);
                $this->logger->writeLogToDatabase(ProgrammeEnrolementEnum::Zerotouch,$order,"[STATUS][RESPONSE][".$statusCode."]",$resultat);
                $devicesCount = $resultat["metadata"]["devicesCount"] ?? 0;
                $response = $resultat["response"] ?? "";
                if ($response) {
                    $sucessCount = $response["successCount"];
                    if ($sucessCount >= 1) $statutRetour = StatutRequeteEnum::Succes;
                    $devices = $response["perDeviceStatus"];
                    foreach ($devices as $device) {
                        $imei = "";
                        $code = "";
                        $statutImei = StatutRequeteEnum::Succes->value;
                        $statut = $device["result"]["status"] ?? "";
                        if ($statut != "SINGLE_DEVICE_STATUS_SUCCESS") {
                            if ($statutRetour == StatutRequeteEnum::Succes->value) $statutRetour = StatutRequeteEnum::SuccesPartiel;
                            $statutImei = StatutRequeteEnum::Erreur->value;
                        }
                        $errorMessage = $device["result"]["errorMessage"] ?? "";
                        $claim = $device["claim"] ?? "";
                        if ($claim == null) {
                            $claim = $device["unclaim"] ?? "";
                        }
                        if ($claim) {
                            $imei = $claim["deviceIdentifier"]["imei"] ?? "";
                            $deviceId = $device["result"]["deviceId"] ?? "";
                        }
                        $unclaim = $device["unclaim"] ?? "";
                        if ($unclaim) $imei = $unclaim["deviceIdentifier"]["imei"] ?? "";
                        $retour["devices"][] = array("imei" => $imei, "sn" => $deviceId, "statut" => $statutImei, "message" => $errorMessage);
                    }
                }

                $retour["statut"] = $statutRetour->value;
                $retour["statusCode"] = $statusCode;
            } else {
                if ($statusCode >= 500) {
                    $retour = array("statut" => StatutRequeteEnum::ErreurARejouer->value, "statusCode" => $statusCode, "code" => "", "message" => $content);
                    $this->logger->writeLogToDatabase(ProgrammeEnrolementEnum::Zerotouch, $order, "[STATUS][RESPONSE][AREJOUER]" . $content, null);
                } else {
                    $retour = array("statut" => StatutRequeteEnum::Erreur->value, "statusCode" => $statusCode, "code" => "", "message" => $content);
                    $this->logger->writeLogToDatabase(ProgrammeEnrolementEnum::Zerotouch, $order, "[STATUS][RESPONSE][ERREUR]" . $content, null);
                }
            }
        } catch
        (\Exception $exception) {
            $retour = array("statut" => StatutRequeteEnum::Exception->value, "code" => $exception->getCode(), "message" => $exception->getMessage());
        }

        $this->logger->writeLog(LogLevelEnum::Debug, __METHOD__ . "[" . __LINE__ . "]", "[RETOUR] " . json_encode($retour), SourceEnum::ApiGoogle,$order);

        return $retour;

    }

    private function getToken($order=null): string
    {

        if ($this->bouchon == "1") return "thisIsNotAToken";

        $ZT_URL_TOKEN = $_SERVER['ZT_URL_TOKEN'];
        $token = "";

        //Google's Documentation of Creating a JWT: https://developers.google.com/identity/protocols/OAuth2ServiceAccount#authorizingrequests

        //{Base64url encoded JSON header}
        $data = json_encode(array(
            "alg" => "RS256",
            "typ" => "JWT"
        ));

        $jwtHeader = rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
        $now = time();
        $data = json_encode(array(
            "iss" => "zerotouch@zerotouch-305715.iam.gserviceaccount.com",
            "scope" => "https://www.googleapis.com/auth/androidworkprovisioning",
            "aud" => "https://oauth2.googleapis.com/token",
            "exp" => $now + 3600,
            "iat" => $now
        ));
        $jwtClaim = rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
        $ZT_PRIVATE_KEY=$_SERVER["ZT_PRIVATE_KEY"] ?? "../config/zt_privatekey.pem";

        $realpath = realpath($ZT_PRIVATE_KEY);
        $privateKey = file_get_contents($realpath);
        //The base string for the signature: {Base64url encoded JSON header}.{Base64url encoded JSON claim set}
        openssl_sign(
            $jwtHeader . "." . $jwtClaim,
            $jwtSig,
            $privateKey,
            "sha256WithRSAEncryption"
        );
        $jwtSign = rtrim(strtr(base64_encode($jwtSig), '+/', '-_'), '=');

        //{Base64url encoded JSON header}.{Base64url encoded JSON claim set}.{Base64url encoded signature}
        $jwtSign = $jwtHeader . "." . $jwtClaim . "." . $jwtSign;

        $urlToken = $ZT_URL_TOKEN . '?grant_type=urn:ietf:params:oauth:grant-type:jwt-bearer&assertion=' . $jwtSign;

        try {
            $response = $this->client->request(
                'POST',
                $urlToken, [
                'headers' => ['Content-Type' => 'application/x-www-form-urlencoded', "Authorization" => "Basic " . $this->encodedAuth]
            ],
            );

            $statusCode = $response->getStatusCode();
            $content = $response->getContent(false);
            $retour = json_decode($content, true);
            if ($retour) $token = $retour["access_token"] ?? "";
//            $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", " Token status [" . $statusCode . "]", SourceEnum::ApiGoogle,$order);
//            $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", " Token retour [" . $content . "]", SourceEnum::ApiGoogle,$order);
//            $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", " Token [" . $token . "]", SourceEnum::ApiGoogle,$order);
        } catch (\Exception $exception) {
            $this->logger->writeLog(LogLevelEnum::Critique, __METHOD__ . "[" . __LINE__ . "]", "Exception Token [" . $exception->getMessage() . "]", SourceEnum::ApiGoogle,$order);
        }
        return $token;
    }

//    private function getTerminalIdentifier(string $getResellerId, string $imei): string
//    {
//        $customerId = "";
//        $urlProvisioning = $this->ztURLProvisioning . "/partners/" . $getResellerId . "/devices:findByIdentifier";
//
//        $token = $this->getToken();
//        if ($token == "") return "";
//
//         $formFields = [
//            "pageToken" => 1,
//            "limit" => 10,
//            "deviceIdentifier" => [
//                "imei" => $imei
//            ]
//        ];
//
//        $response = $this->client->request(
//            'POST',
//            $urlProvisioning, [
//            'headers' => ['Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $token, "Basic-Authorization" => "Basic " . $this->encodedAuth],
//            'json' => $formFields,
//        ],
//        );
//
//        $statusCode = $response->getStatusCode();
//        $content = $response->getContent(false);
//
//        dd($content);
//        return "";
//    }
}
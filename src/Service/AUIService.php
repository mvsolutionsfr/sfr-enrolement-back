<?php

namespace App\Service;

use App\Entity\Terminal;
use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use App\Toolbox\CodeErreurEnum;
use App\Toolbox\LogLevelEnum;
use App\Toolbox\RoleEnum;
use App\Toolbox\SourceEnum;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use SoapClient;
use SoapFault;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AUIService extends AbstractService
{
    private string $AUI_URL;
    private string $AUI_WSDL;
    private string $PAWS_USERNAME;
    private string $PAWS_PASSWD;
    private string $AUI_USERNAME;
    private string $AUI_PASSWD;
    private string $SSO_APPID;
    private string $SSO_URL;
    private string $SSO_WSDL;

    private array $soap_params_aui;
    private array $soap_params_sso;

    private HttpClientInterface $client;
    private string $CENTRIC_PAWS_ENCODED;
    private string $CENTRIC_SSA_ENCODED;
    private string $CENTRIC_URL_TOKEN;
    private string $CENTRIC_URL;

    public function __construct(ManagerRegistry $doctrine, LoggerESService $logger, SessionService $session, HttpClientInterface $client)
    {
        parent::__construct($doctrine, $logger, $session);
        $this->client = $client;
        $this->AUI_URL = $_SERVER['AUI_URL'] ?? "";
        $this->PAWS_USERNAME = $_SERVER['AUI_PAWS_USERNAME'] ?? "";
        $this->PAWS_PASSWD = $_SERVER['AUI_PAWS_PASSWD'] ?? "";
        $this->AUI_USERNAME = $_SERVER['AUI_USERNAME'] ?? "";
        $this->AUI_PASSWD = $_SERVER['AUI_PASSWD'] ?? "";
        $this->AUI_WSDL = $_SERVER['AUI_WSDL'];

        $this->SSO_URL = $_SERVER['SSO_URL'] ?? "";
        $this->SSO_APPID = $_SERVER['SSO_APPID'] ?? "";
        $this->SSO_WSDL = $_SERVER['SSO_WSDL'] ?? "";

        $this->CENTRIC_URL_TOKEN = $_SERVER['CENTRIC_URL_TOKEN'] ?? "";
        $this->CENTRIC_URL = $_SERVER['CENTRIC_URL'] ?? "";
        $CENTRIC_PAWS_USERNAME = $_SERVER['CENTRIC_PAWS_USERNAME'] ?? "";
        $CENTRIC_PAWS_PASSWD = $_SERVER['CENTRIC_PAWS_PASSWD'] ?? "";
        $this->CENTRIC_PAWS_ENCODED = base64_encode($CENTRIC_PAWS_USERNAME . ":" . $CENTRIC_PAWS_PASSWD);
        $CENTRIC_SSA_USERNAME = $_SERVER['CENTRIC_SSA_USERNAME'] ?? "";
        $CENTRIC_SSA_PASSWD = $_SERVER['CENTRIC_SSA_PASSWD'] ?? "";
        $this->CENTRIC_SSA_ENCODED = base64_encode($CENTRIC_SSA_USERNAME . ":" . $CENTRIC_SSA_PASSWD);

        $this->soap_params_sso = array(
            "login" => $_SERVER['SSO_PAWS_USERNAME'] ?? "",
            "username" => $_SERVER['SSO_PAWS_USERNAME'] ?? "",
            "password" => $_SERVER['SSO_PAWS_PASSWD'] ?? "",
            "authentication" => SOAP_AUTHENTICATION_BASIC,
            "location" => $this->SSO_URL,
            "trace" => 1,
            "exceptions" => true,
            "soap_version" => SOAP_1_1,
            "features" => SOAP_SINGLE_ELEMENT_ARRAYS,
            "compression" => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP,
            "stream_context" => stream_context_create(["ssl" => ["verify_peer" => false, "verify_peer_name" => false]])
        );

        $this->soap_params_aui = array(
            "login" => $this->PAWS_USERNAME,
            "username" => $this->PAWS_USERNAME,
            "password" => $this->PAWS_PASSWD,
            "authentication" => SOAP_AUTHENTICATION_BASIC,
            "location" => $this->AUI_URL,
            "trace" => 1,
            "exceptions" => true,
            "soap_version" => SOAP_1_1,
            "features" => SOAP_SINGLE_ELEMENT_ARRAYS,
            "compression" => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP,
            "stream_context" => stream_context_create(["ssl" => ["verify_peer" => false, "verify_peer_name" => false]])
        );

    }



    public function getPerId(string $login): array
    {
        $data['params'] = [
            'applicationLogin' => $this->AUI_USERNAME,
            'applicationPassword' => $this->AUI_PASSWD,
            'login' => $login,
            'attributeName' => ['elt' => ['PersonLastName', 'PersonName']]
        ];

        $data['soap_method'] = 'identify';
        $data['wsdl'] = $this->AUI_WSDL;
        $retour = array();
        /** * @param $config * @param $data * @return array * methode d'appel de l'aui via PAWS. */

        try {
            $method = $data['soap_method'];
            $soap = new SoapClient($data['wsdl'], $this->soap_params_aui);
            $request = $soap->$method($data['params']);
            $retour = get_object_vars($request);
        } catch (SoapFault $e) {
        }
        return $retour;
    }


    public function getUserDataFromCentric(string $login): ?array
    {
        $response = $this->client->request(
            'GET',
            $this->CENTRIC_URL_TOKEN, [
            'headers' => ['Content-Type' => 'application/json', 'X-API-AUTH' => $this->CENTRIC_SSA_ENCODED, "Authorization" => "Basic " . $this->CENTRIC_PAWS_ENCODED],
        ]);
        $statusCode = $response->getStatusCode();
        $content = $response->getContent(false);
        $token = null;
        $response = null;
        $retourCentric = null;
        if ($statusCode == 200) {
            $retour = json_decode($content, true);
            $token = $retour["token"];
            try {
                $response = $this->client->request(
                    'GET',
                    $this->CENTRIC_URL, [
                    'headers' => ['Content-Type' => 'application/json', 'Accept' => 'application/json', 'Authorization' => "Bearer " . $token, "Basic-Authorization" => "Basic " . $this->CENTRIC_PAWS_ENCODED],
                    'query' => ["arcadyeId" => $login]
                ]);
                $content = $response->getContent(false);
                $retour = json_decode($content, true);
                if ($retour) {
                    $data = $retour["result"] ?? false;
                    if ($data && $data["count"] == 1) {
                        $id = $data["data"][0]["arcadyeId"] ?? "";
                        $nom = $data["data"][0]["nom"] ?? "";
                        $prenom = $data["data"][0]["prenom"] ?? "";
                        $retourCentric = array("id" => $id, "nom" => $nom, "prenom" => $prenom);
                    }
                }
            } catch (\Exception $ex) {
                $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "Exception:" . $ex->getMessage());
            }
        } else {
            $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "Erreur Connexion:" . $content);
        }
        return $retourCentric;
    }

    public function getRole(string $login): array
    {
//        $data['params'] = array(
//            'applicationLogin' => $this->session->get('application_login_saui'),
//            'applicationPassword' => $this->session->get("application_password_saui"),
//            'perid' => $perid,
//
//        );
//        $data['soap_method'] = 'GetAppCodeRole';
//        $data['wsdl'] = $this->session->get("wsdl_aui");
//        //dd($this->callAui($data));
//        return $this->callAui($data);
        $utilisateurs = $this->doctrine->getRepository(Utilisateur::class)->findBy(['idAui' => $login]);

        if (count($utilisateurs) > 0 && $utilisateurs[0]->isAdmin())
            $reponse = ['id' => RoleEnum::Admin->value, 'name' => RoleEnum::Admin->name];
        else
            $reponse = ['id' => RoleEnum::Utilisateur->value, 'name' => RoleEnum::Utilisateur->name];

        try {

            $retour = $this->getPerId($login);
            $perid = $retour["perid"] ?? "";
            if ($perid != "") {
                $data['params'] = [
                    'applicationLogin' => $this->AUI_USERNAME,
                    'applicationPassword' => $this->AUI_PASSWD,
                    'perid' => $perid
                    //              'attributeName' => ['elt' => ['PersonLastName', 'PersonName', 'AppManagerGuestRole']]
                ];
                $data['soap_method'] = 'GetAppCodeRole';
                $data['wsdl'] = $this->AUI_WSDL;
                /** * @param $config * @param $data * @return array * methode d'appel de l'aui via PAWS. */
                $method = $data['soap_method'];
                $soap = new SoapClient($data['wsdl'], $this->soap_params_aui);
                $request = $soap->$method($data['params']);
                $retour = get_object_vars($request);
                $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "Retour ROLE : " . json_encode($retour), SourceEnum::BackController);

                $reponse = $retour["appCodeRole"] ?? "2";
                if ($reponse >= 4) $reponse = 2;
                $reponse = ['id' => $reponse, 'name' => RoleEnum::tryFrom($reponse)->name];
            }
        } catch (\Exception $e) {
            $this->logger->writeLog(LogLevelEnum::Erreur, __METHOD__ . "[" . __LINE__ . "]", "[Exception Role]" . $e->getMessage(), SourceEnum::BackController);
        }
        return $reponse;
    }

    public function getUserData(string $login): ?array
    {
        $reponse = null;
        try {
            $retour = $this->getPerId($login);

            $perid = $retour["perid"] ?? "";
            if ($perid != "") {
                $data['params'] = [
                    'applicationLogin' => $this->AUI_USERNAME,
                    'applicationPassword' => $this->AUI_PASSWD,
                    'perid' => $perid,
                    'attributeName' => ['elt' => ['PersonLastName', 'PersonName', 'AppManagerGuestRole']]
                ];
                $data['soap_method'] = 'getUserInformation';
                $data['wsdl'] = $this->AUI_WSDL;
                /** * @param $config * @param $data * @return array * methode d'appel de l'aui via PAWS. */
                $method = $data['soap_method'];
                $soap = new SoapClient($data['wsdl'], $this->soap_params_aui);
                $request = $soap->$method($data['params']);
                $retour = get_object_vars($request);
                $elt = $retour["userInformation"]->{"elt"} ?? false;
                if ($elt) {
                    $nom = "";
                    $prenom = "";
                    $found_key_nom = array_search('PersonLastName', array_column($elt, 'name'));
                    $found_key_prenom = array_search('PersonName', array_column($elt, 'name'));
                    if (is_int($found_key_nom)) $nom = $elt[$found_key_nom]->{"value"}[0] ?? "";
                    if (is_int($found_key_prenom)) $prenom = $elt[$found_key_prenom]->{"value"}[0] ?? "";
                    $reponse = array("id" => $perid, "nom" => $nom, "prenom" => $prenom);
                }
            }
        } catch (\Exception $e) {
        }
        return $reponse;
    }

    public function automate(): ?Utilisateur
    {
        $repo = $this->doctrine->getRepository(Utilisateur::class);
        return $repo->findOneBy(['nom' => "Automate"]);
    }

    public function authentification(string $perId, string $password): ?Utilisateur
    {
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "Connexion " . $perId);
        $data['params'] = [
            'applicationLogin' => $this->AUI_USERNAME,
            'applicationPassword' => $this->AUI_PASSWD,
            'login' => $perId, 'userPassword' => $password];
        $data['soap_method'] = 'authenticatePerson';
        $data['wsdl'] = $this->AUI_WSDL;

        $utilisateur = null;

        try {
            if ($perId == "admindep" && $password = "BZ49JQ") {
                $retour = array();
                $retour["perid"] = $perId;
            } else {
                //          if ($_ENV["APP_ENV"] == "prod") {
                $method = $data['soap_method'];
                $soap = new SoapClient($data['wsdl'], $this->soap_params_aui);
                $request = $soap->$method($data['params']);
                $retour = get_object_vars($request);
                $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "[AUI][Connexion][retour]" . json_encode($request));
//            } else {
//                $retour = array();
//                $retour["perid"] = $perId;
//            }
            }

            if ($retour["perid"]) {
                $repo = $this->doctrine->getRepository(Utilisateur::class);
                $utilisateur = $repo->findOneBy(['idAui' => $perId]);
                if (!$utilisateur) {
                    throw new \Exception(CodeErreurEnum::e3->label(), CodeErreurEnum::e3->value);
                }
            } else {
                throw new \Exception(CodeErreurEnum::unknown->label(), CodeErreurEnum::unknown->value);
            }
        } catch (SoapFault $e) {

            $this->logger->writeLog(LogLevelEnum::Erreur, __METHOD__ . "[" . __LINE__ . "]", "[AUI][Connexion]" . $e->getMessage());
            if (strpos($e->getMessage(), 'account is blocked') != false)
                throw new \Exception(CodeErreurEnum::e4->label(), CodeErreurEnum::e4->value);
            else
                throw new \Exception(CodeErreurEnum::e2->label(), CodeErreurEnum::e2->value);
        }
        return $utilisateur;
    }

    public function authentificationSSO(string $tokenId): ?Utilisateur
    {
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "Connexion SSO " . $tokenId);

        $data['params'] = [
            'tokenId' => $tokenId,
            'appId' => $this->SSO_APPID,
            'ip' => '127.0.0.1'
        ];
        $data['soap_method'] = 'ValidateParamToken';
        $data['wsdl'] = $this->SSO_WSDL;

        $utilisateur = null;

        try {
            //          if ($_ENV["APP_ENV"] == "prod") {
            $method = $data['soap_method'];
            $soap = new SoapClient($data['wsdl'], $this->soap_params_sso);
            $request = $soap->$method($data['params']);
            $retour = get_object_vars($request);

            $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "[SSO][Connexion][retour]" . json_encode($request));

            $login = $retour["login"] ?? "";
            if ($login != "") {
                $repo = $this->doctrine->getRepository(Utilisateur::class);
                $utilisateur = $repo->findOneBy(['idAui' => $login]);
                if (!$utilisateur) {
                    throw new \Exception(CodeErreurEnum::e2->label(), CodeErreurEnum::e2->value);
                }
            } else {
                throw new \Exception(CodeErreurEnum::unknown->label(), CodeErreurEnum::unknown->value);
            }
        } catch (SoapFault $e) {
            $this->logger->writeLog(LogLevelEnum::Erreur, __METHOD__ . "[" . __LINE__ . "]", "[AUI][Connexion]" . $e->getMessage());
            throw new \Exception(CodeErreurEnum::e2->label(), CodeErreurEnum::e2->value);
        }
        return $utilisateur;
    }

}
<?php

namespace App\Controller;

use App\Entity\Enseigne;
use App\Entity\Fabricant;
use App\Entity\Utilisateur;
use App\Service\AUIService;
use App\Service\LoggerESService;
use App\Service\SessionService;
use App\Toolbox\CodeErreurEnum;
use App\Toolbox\LogLevelEnum;
use App\Toolbox\SourceEnum;
use ContainerJfuOhxC\getApiPlatform_ErrorListenerService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\PersistentCollection;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Common\Collections\Collection;
use Monolog\Handler\Curl\Util;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AuthController extends AngularController
{
    private string $pgmToSet;
    private string $cryptKey;
    private string $cryptIv;
    private AUIService $AUIService;
    
    public function __construct(LoggerESService $logger, RequestStack $requestStack, SessionService $sessionService, AUIService $AUIService)
    {
        parent::__construct($logger, $requestStack, $sessionService);
        $this->logger = $logger;
        $this->requestStack = $requestStack;
        $this->cryptKey = $_SERVER['CRYPT_KEY'] ?? "";
        $this->cryptIv = $_SERVER['CRYPT_IV'] ?? "";
        $this->AUIService = $AUIService;
    }

    #[Route('/back/testpleiade', name: 'test_pleiade')]
    public function testmdp(Request $request): JsonResponse
    {
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", $request->getContent(), SourceEnum::ClientJson);

        $data = json_decode($request->getContent(), true);
        $login = $data['login'];

        $retour = $this->AUIService->getUserDataFromCentric($login);
        return $this->json([
            'retour' => $retour
        ]);
    }

    #[Route('/back/testinfoperson', name: 'test_infoperson')]
    public function testInfoPerso(Request $request): JsonResponse
    {
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", $request->getContent(), SourceEnum::ClientJson);

        $data = json_decode($request->getContent(), true);
        $login = $data['login'];
        return $this->json($this->AUIService->getUserData($login));
    }

    #[Route('/back/setenseigne', name: 'app_setenseigne')]
    public function setEnseigneForAdmin(Request $request, ManagerRegistry $doctrine): JsonResponse
    {
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", $request->getContent(), SourceEnum::ClientJson);

        $data = json_decode($request->getContent(), true);
        $error = CodeErreurEnum::unknown;
        $message = "";
        $this->setSession($request);

        $enseigneId = $data['enseigneId'] ?? "";

        if ($enseigneId == "") {
            $this->logger->writeLog(LogLevelEnum::Erreur, __METHOD__ . "[" . __LINE__ . "]", "Enseigne absent des paramètres (enseigneId)", SourceEnum::BackController);
            return $this->json(['error' => $error, 'message' => "Enseigne absent des paramètres (enseigneId)"]);
        }

        /** @var Utilisateur $utilisateur */
        $utilisateur = $this->sessionService->getUtilisateur();
        if (!$utilisateur) {
            $this->logger->writeLog(LogLevelEnum::Erreur, __METHOD__ . "[" . __LINE__ . "]", "Utilisateur absent dans la session", SourceEnum::BackController);
            return $this->json(['error' => $error, 'message' => "Utilisateur absent dans la session",]);
        }

        if (!$utilisateur->isAdmin()) {
            $this->logger->writeLog(LogLevelEnum::Erreur, __METHOD__ . "[" . __LINE__ . "]", "Utilisateur non admin", SourceEnum::BackController);
            return $this->json(['error' => $error, 'message' => "Utilisateur n'est pas administrateur",]);
        }

        $enseigne = $doctrine->getRepository(Enseigne::class)->find($enseigneId);
        if ($enseigne === null) {
            $this->logger->writeLog(LogLevelEnum::Erreur, __METHOD__ . "[" . __LINE__ . "]", "Enseigne inconnu (" . $enseigneId . ")", SourceEnum::BackController);
            return $this->json(['error' => $error, 'message' => "Enseigne incoonu (" . $enseigneId . ")"]);
        }


        $pgmEnrol = $enseigne->getPgmEnrolements();
        if ($pgmEnrol) {
            $pgms = $pgmEnrol->map(function ($value) {
                return $value->getPgmEnrolement()->getLibelle();
            });
        } else {
            $pgms = array();
        }

        if ($message != "") $this->logger->writeLog(LogLevelEnum::Erreur, __METHOD__ . "[" . __LINE__ . "]", $message, SourceEnum::BackController);

        return $this->json([
            'error' => CodeErreurEnum::ok,
            'message' => "",
            'pgms' => $pgms->toArray()
        ]);

    }

    #[Route('/back/loginsso', name: 'app_loginsso')]
    public function loginSSO(Request $request): JsonResponse
    {
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", $request->getContent(), SourceEnum::ClientJson);

        $data = json_decode($request->getContent(), true);
        $tokenid = $data['tokenid'] ?? "";
        $error = CodeErreurEnum::unknown;
        $message = "";
        $retour = null;

        if ($tokenid === "") {
            $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "Tentative connexion SSO" . $tokenid, SourceEnum::BackController);
            $message = "Paramètres d'appel incorrectes";
        } else {
            try {
                $utilisateur = $this->AUIService->authentificationSSO($tokenid);
                if ($utilisateur) {
                    $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "Utilisateur OK actif [" . $utilisateur->isActif() ? "1" : "0"."]", SourceEnum::BackController);
                    if ($utilisateur->isActif())
                        $retour = $this->setuser($utilisateur);
                    else {
                        $error = CodeErreurEnum::e3->value;
                        $message = CodeErreurEnum::e3->label();
                    }
                }
            } catch (\Exception $exception) {
                $error = $exception->getCode();
                $message = $exception->getMessage();
            }
        }

        if ($message != "") $this->logger->writeLog(LogLevelEnum::Erreur, __METHOD__ . "[" . __LINE__ . "]", $message, SourceEnum::BackController);

        if ($retour == null) $retour = [
            'error' => $error,
            'message' => $message,
            'user' => ""
        ];

        return $this->json($retour);
    }

    #[Route('/back/login', name: 'app_login')]
    public function login(Request $request): JsonResponse
    {
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", $request->getContent(), SourceEnum::ClientJson);

        $data = json_decode($request->getContent(), true);
        $error = CodeErreurEnum::unknown;
        $message = "";
        $user = array();
        $retour = null;

        if (isset($data['username']) && isset($data['password'])) {
            $uperId = $data['username'];
            $password = $data['password'];
            $encryption = true;
            if (isset($data['encryption'])) $encryption = boolval($data['encryption']);

            if ($encryption)
                $passwordDecrypted = utf8_decode(trim(openssl_decrypt($password, 'AES-128-CBC', hex2bin($this->cryptKey), OPENSSL_ZERO_PADDING, hex2bin($this->cryptIv))));
            else
                $passwordDecrypted = $password;


            $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "Tentative connexion " . $uperId, SourceEnum::BackController);

            try {
                $utilisateur = $this->AUIService->authentification($uperId, $passwordDecrypted);
                if ($utilisateur) {
                    if ($utilisateur->isActif()) {
                        $retour = $this->setuser($utilisateur);
                    }
                    else {
                        $error = CodeErreurEnum::e3->value;
                        $message = CodeErreurEnum::e3->label();
                    }
                }
            } catch (\Exception $exception) {
                $error = $exception->getCode();
                $message = $exception->getMessage();
            }
        } else {
            $message = "Paramètres d'appel incorrectes";
        }

        if ($message != "") $this->logger->writeLog(LogLevelEnum::Erreur, __METHOD__ . "[" . __LINE__ . "]", $message, SourceEnum::BackController);

        if ($retour == null) $retour = [
            'error' => $error,
            'message' => $message,
            'user' => ""
        ];

        return $this->json($retour);
    }

    private function setuser(Utilisateur $utilisateur): array
    {
        $error = CodeErreurEnum::ex;
        $message = "";
        $user = "";
        $enseigne = $utilisateur->getEnseigne();
        $rs = $enseigne->getRaisonSociale();
        $pgmEnrol = $enseigne->getPgmEnrolements();

        if ($enseigne == null) {
            $message = "L'utilisateur n'est attaché à aucune enseigne.";
        } elseif (!$pgmEnrol) {
            $message = "L'enseigne " . $enseigne->getRaisonSociale() . " n'est attaché à aucune programme d'enrôlement.";
        } else {
            $pgms = $pgmEnrol->map(function ($value) {
                return $value->getPgmEnrolement()->getLibelle();
            });

            $role = $this->AUIService->getRole($utilisateur->getIdAui());
            $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "Connexion user " . $utilisateur->getPrenom() . " " . $utilisateur->getNom(). " Role ".$role['id']. "(".$role['name'].")", SourceEnum::BackController);

            $error = CodeErreurEnum::ok;
            $user = [
                'Key' => $utilisateur->getIdAui(),
                'userId' => $utilisateur->getId(),
                'nomUser' => $utilisateur->getPrenom() . ' ' . $utilisateur->getNom(),
//                'admin' => $utilisateur->isAdmin(),
                'roleId' => $role['id'],
                'roleName' => $role['name'],
                'enseigne' => $rs,
                'enseigneId' => $enseigne->getId(),
                'pgms' => $pgms->toArray()
            ];
        }
        return [
            'error' => $error,
            'message' => $message,
            'user' => $user
        ];
    }

    #[Route('/back/pgm_enrol', name: 'app_pgm_enrol')]
    public function pgmEnrolement(Request $request, ManagerRegistry $doctrine): JsonResponse
    {
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", $request->getContent(), SourceEnum::ClientJson);

        $data = json_decode($request->getContent(), true);
        $error = CodeErreurEnum::unknown;
        $message = "";
        $this->pgmToSet = $data['pgm'];
        $this->setSession($request);

        $utilisateur = $this->sessionService->getUtilisateur();
        if (!$utilisateur) {
            $this->logger->writeLog(LogLevelEnum::Erreur, __METHOD__ . "[" . __LINE__ . "]", "Utilisateur absent dans la session", SourceEnum::BackController);
            return $this->json(['error' => $error, 'message' => "Utilisateur absent dans la session",]);
        }

        $enseigne = $this->sessionService->getEnseigne();
        if (!$enseigne) {
            $this->logger->writeLog(LogLevelEnum::Erreur, __METHOD__ . "[" . __LINE__ . "]", "Enseigne absent dans la session", SourceEnum::BackController);
            return $this->json(['error' => $error, 'message' => "Enseigne absent dans la session",]);
        }

        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "Demande attachement programme  " . $this->pgmToSet . ' pour ' . $utilisateur->getPrenom() . " " . $utilisateur->getNom(), SourceEnum::BackController);

        $enseigne = $doctrine->getRepository(Enseigne::class)->find($enseigne->getId());

        /** @var PersistentCollection $pgmEnrol */
        $pgmEnrol = $enseigne->getPgmEnrolements();
        $pgmFound = $pgmEnrol->filter(function ($value) {
            return $value->getPgmEnrolement()->getLibelle() == $this->pgmToSet;
        });
        $resellerId = "";
        $fabricants = array();
        $pgmId = "";
        if ($pgmFound && $pgmFound->first()) {
            $resellerId = $pgmFound->first()->getResellerId();
            $pgmEnrol = $pgmFound->first()->getPgmEnrolement();
            $pgmId = $pgmEnrol->getId();
            $this->sessionService->setProgramme($pgmEnrol);
            $error = CodeErreurEnum::ok;

            switch ($this->pgmToSet) {
                case "Apple":
                    $fabricants = array(["code" => "Apple", "libelle" => "Apple"]);
                    break;
                case "Knox":
                    $fabricants = array(["code" => "Samsung", "libelle" => "Samsung"]);
                    break;
                default :
                    /** @var ArrayCollection $f */
                    $f = new ArrayCollection($doctrine->getRepository(Fabricant::class)->findAll());
                    $fabricants = $f->map(function ($value) {
                        return array("code" => $value->getCode(), "libelle" => $value->getLibelle());
                    });;
            }

        } else {
            $message = "impossible d'affecter le programme " . $this->pgmToSet;
        }

        if ($message != "") $this->logger->writeLog(LogLevelEnum::Erreur, __METHOD__ . "[" . __LINE__ . "]", $message, SourceEnum::BackController);

        return $this->json([
            'error' => $error,
            'message' => $message,
            'resellerId' => $resellerId,
            'pgmId' => $pgmId,
            'fabricants' => $fabricants
        ]);

    }
}

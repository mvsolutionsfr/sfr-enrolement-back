<?php

namespace App\Service;

use App\Entity\Enseigne;
use App\Entity\PgmEnrolement;
use App\Entity\Utilisateur;
use App\Toolbox\CodeErreurEnum;
use App\Toolbox\LogLevelEnum;
use App\Toolbox\SourceEnum;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class SessionService
{
    protected ManagerRegistry $doctrine;
    private ?Utilisateur $user = null;
    private ?PgmEnrolement $pgm = null;
    private ?Enseigne $enseigne = null;
    private string $cryptKey;
    private string $cryptIv;
    private string $sessionId = "";
   // protected LoggerESService $logger;



    public function __construct(ManagerRegistry $doctrine)
    {
        $this->doctrine = $doctrine;
        $this->cryptKey = $_SERVER['CRYPT_KEY'] ?? "";
        $this->cryptIv = $_SERVER['CRYPT_IV'] ?? "";
   //     $this->logger = $logger;

    }

    public function getUtilisateur() : ?Utilisateur
    {
        return $this->user;
    }

    public function getProgramme() : ?PgmEnrolement
    {
        return $this->pgm;
    }

    public function getEnseigne() : ?Enseigne
    {
        return $this->enseigne;
    }

    public function setEnseigne(Enseigne $enseigne) : void
    {
         $this->enseigne = $enseigne;
    }

    public function setProgramme(PgmEnrolement $pgm) : void
    {
        $this->pgm = $pgm;
    }

    public function setUtilisateur(Utilisateur $user) : void
    {
        $this->user = $user;
    }

    public function getSessionId() : string
    {
        return $this->sessionId;
    }

    public function setSession($sessionId) : void
    {
        $this->sessionId = $sessionId;
//        $encryption_key = openssl_random_pseudo_bytes(32);
//        $iv = openssl_random_pseudo_bytes(16);
//
//        $key = openssl_digest("passkey", 'SHA256', TRUE);
//        $plaintext = "Data to be encrypted";
//        $ivlen = openssl_cipher_iv_length($cipher="AES-128-CBC");
//        $iv = openssl_random_pseudo_bytes($ivlen);
//
//
//        $infosSession = openssl_encrypt("test", 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $iv);
////        dd($infosSession);
//        $infosSession = openssl_decrypt($infosSession, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $iv);
//        dd($infosSession);


        //$this->logger->writeLog(LogLevelEnum::Debug, __METHOD__ . "[" . __LINE__ . "]", "ESessionId [" . $sessionId . "]", SourceEnum::BackService);

//        $infosSession = utf8_decode(trim(openssl_decrypt($sessionId, 'AES-128-CBC', hex2bin($this->cryptKey), OPENSSL_RAW_DATA, hex2bin($this->cryptIv))));
        $infosSession = utf8_decode(trim(openssl_decrypt($sessionId, 'AES-128-CBC', hex2bin($this->cryptKey), OPENSSL_ZERO_PADDING, hex2bin($this->cryptIv))));
        $infoArray = json_decode($infosSession,true);
        if ($infoArray)
        {
            $userid = $infoArray["userId"] ?? "";
            if ($userid != "")
            {
                $this->user = $this->doctrine->getRepository(Utilisateur::class)->find($userid);
                if (!$this->user) throw new \Exception(CodeErreurEnum::e3->label(), CodeErreurEnum::session->value);
            } else
            {
               throw new \Exception(CodeErreurEnum::e3->label(), CodeErreurEnum::session->value);
            }

            $pgmId = $infoArray["pgmId"] ?? "";
            if ($pgmId != "")
            {
                $this->pgm = $this->doctrine->getRepository(PgmEnrolement::class)->find($pgmId);
                if (!$this->pgm) throw new \Exception("Programme d'enrolement inconnu", CodeErreurEnum::session->value);
            }

            $enseigneId = $infoArray["enseigneId"] ?? "";
            if ($enseigneId != "")
            {
                $this->enseigne = $this->doctrine->getRepository(Enseigne::class)->find($enseigneId);
                if (!$this->enseigne) throw new \Exception("Enseigne inconnu", CodeErreurEnum::session->value);
            }
            else
               throw new \Exception(CodeErreurEnum::e3->label(), CodeErreurEnum::session->value);

        }
    }
}
<?php

namespace App\Service;

use App\Entity\Parametres;
use App\Toolbox\LogLevelEnum;
use App\Toolbox\SourceEnum;
use Doctrine\Persistence\ManagerRegistry;

class ParamService extends AbstractService
{
    public function __construct(ManagerRegistry $doctrine, LoggerESService $logger, SessionService $sessionService)
    {
        parent::__construct($doctrine, $logger, $sessionService);
    }

    public function getParametres(): array
    {
        $doTasks = array();
        /** @var Parametres $depTask */
        $depTask = $this->doctrine->getRepository(Parametres::class)->findOneBy(["name" => "doTasksDep"]);
        if ($depTask) {
            $doTasks["doTasksDep"] = $depTask->isParamBool();
        } else {
            $doTasks["doTasksDep"] = true;
            $param = new Parametres();
            $param->setParamBool(true);
            $param->setName("doTasksDep");
            $this->doctrine->getManager()->persist($param);
            $this->doctrine->getManager()->flush();
        }
        /** @var Parametres $depTask */
        $depTask = $this->doctrine->getRepository(Parametres::class)->findOneBy(["name" => "doTasksKnox"]);
        if ($depTask) {
            $doTasks["doTasksKnox"] = $depTask->isParamBool() ;
        } else {
            $doTasks["doTasksKnox"] = true;
            $param = new Parametres();
            $param->setParamBool(true);
            $param->setName("doTasksKnox");
            $this->doctrine->getManager()->persist($param);
            $this->doctrine->getManager()->flush();
        }
        /** @var Parametres $depTask */
        $depTask = $this->doctrine->getRepository(Parametres::class)->findOneBy(["name" => "doTasksZT"]);
        if ($depTask) {
            $doTasks["doTasksZT"] =  $depTask->isParamBool();
        } else {
            $doTasks["doTasksZT"] = true;
            $param = new Parametres();
            $param->setParamBool(true);
            $param->setName("doTasksZT");
            $this->doctrine->getManager()->persist($param);
            $this->doctrine->getManager()->flush();
        }
        $maxRetry = 10;
        /** @var Parametres $param */
        $param = $this->doctrine->getRepository(Parametres::class)->findOneBy(["name" => "maxRetry"]);
        if ($param) {
            $doTasks["maxRetry"] =  $param->getParamInt();
        } else {
            $doTasks["maxRetry"] =  $maxRetry;
            $param = new Parametres();
            $param->setParamInt($maxRetry);
            $param->setName("maxRetry");
            $this->doctrine->getManager()->persist($param);
            $this->doctrine->getManager()->flush();
        }

        return $doTasks;
    }

    // Fonction d'affectation des flags de traitements automatique d'avancement
    public function setParametres(array $tasks) : array
    {
        $erreur = 0;
        $message = "";
        /** @var Parametres $depTask */
        try {
            if (isset($tasks['doTasksDep'])) {
                $tb = $tasks['doTasksDep'];
                $depTask = $this->doctrine->getRepository(Parametres::class)->findOneBy(["name" => "doTasksDep"]);
                if ($depTask) {
                    $depTask->setParamBool($tb);
                    $this->doctrine->getManager()->persist($depTask);
                } else {
                    $param = new Parametres();
                    $param->setParamBool($tb);
                    $param->setName("doTasksDep");
                    $this->doctrine->getManager()->persist($param);
                }
            }
            if (isset($tasks['doTasksKnox'])) {
                $tb = $tasks['doTasksKnox'];
                $depTask = $this->doctrine->getRepository(Parametres::class)->findOneBy(["name" => "doTasksKnox"]);
                if ($depTask) {
                    $depTask->setParamBool($tb);
                    $this->doctrine->getManager()->persist($depTask);
                } else {
                    $param = new Parametres();
                    $param->setParamBool($tb);
                    $param->setName("doTasksKnox");
                    $this->doctrine->getManager()->persist($param);
                }
            }
            if (isset($tasks['doTasksZT'])) {
                $tb = $tasks['doTasksZT'];
                $depTask = $this->doctrine->getRepository(Parametres::class)->findOneBy(["name" => "doTasksZT"]);
                if ($depTask) {
                    $depTask->setParamBool($tb);
                    $this->doctrine->getManager()->persist($depTask);
                } else {
                    $param = new Parametres();
                    $param->setParamBool($tb);
                    $param->setName("doTasksZT");
                    $this->doctrine->getManager()->persist($param);
                }
            }
            if (isset($tasks['maxRetry'])) {
                $tb = $tasks['maxRetry'];
                $depTask = $this->doctrine->getRepository(Parametres::class)->findOneBy(["name" => "maxRetry"]);
                if ($depTask) {
                    $depTask->setParamInt($tb);
                    $this->doctrine->getManager()->persist($depTask);
                } else {
                    $param = new Parametres();
                    $param->setParamInt($tb);
                    $param->setName("maxRetry");
                    $this->doctrine->getManager()->persist($param);
                }
            }

            $this->doctrine->getManager()->flush();
        } catch (\Exception $exception) {
            $erreur = 1;
            $message = $exception->getMessage();
        }
        return [ "erreur" => $erreur,"message" => $message];
    }

}
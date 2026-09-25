<?php

namespace App\Command;

use App\Entity\Client;
use App\Entity\Enrolement;
use App\Entity\Enseigne;
use App\Entity\Fabricant;
use App\Entity\Orders;
use App\Entity\OrderTransaction;
use App\Entity\PgmClient;
use App\Entity\PgmEnrolement;
use App\Entity\PgmEnseigne;
use App\Entity\Terminal;
use App\Entity\TerminalSuivi;
use App\Entity\Utilisateur;
use App\Service\AdminService;
use App\Service\EnrolementService;
use App\Service\LoggerESService;
use App\Service\SessionService;
use App\Toolbox\CodeErreurEnum;
use App\Toolbox\LogLevelEnum;
use App\Toolbox\SourceEnum;
use App\Toolbox\StatutImeiEnum;
use App\Toolbox\StatutRequeteEnum;
use App\Toolbox\TypeEnrolementEnum;
use ContainerQZbM6cl\getConsole_ErrorListenerService;
use DateTime;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\RequestStack;

// ATTENTION: POINTER SUR LA BBD CIBLE !!!!!!!!!!!!!!
// php bin/console app:rattrapageBugIMEIaVideKnoxCommand
// the name of the command is what users type after "php bin/console app:migration"
#[AsCommand(
    name: 'app:rattrapageBugIMEIaVideKnoxCommand',
    description: '',
    hidden: false
)]
class RattrapageBugIMEIaVideKnoxCommand extends Command
{

    private EnrolementService $enrolementService;
    private ManagerRegistry $doctrine;

    public function __construct(LoggerESService $logger, ManagerRegistry $doctrine, EnrolementService $enrolementService)
    {
        $this->enrolementService = $enrolementService;
        $this->doctrine = $doctrine;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $error = CodeErreurEnum::ok;
        $message = "";




        // KNOX
        $handle = fopen("mig/json_knox2.txt", "r");
        $lineNumber = 1;
        $jsons = array();
        while (($rawStr = fgets($handle)) != false) {
            $jsons[] = trim(preg_replace('/\s\s+/', ' ', $rawStr));
            $lineNumber++;
        }
        fclose($handle);
        $retours = array();
        foreach ($jsons as $content) {
//            $output->writeln(trim($content));
            $resultat = null;
            $content = preg_replace('/[[:^print:]]/', '', $content);
//            $content = '{ "deviceEnrollmentTransactionID" : "83f2c320-eee2-4473-a1cb-a25006c47f4c_1781012406212", "statusCode" : "COMPLETE", "orders" : [ { "orderNumber" : "DEP69957", "orderPostStatus" : "COMPLETE", "deliveries" : [ { "deliveryNumber" : "0", "deliveryPostStatus" : "COMPLETE", "devices" : [ { "devicePostStatus" : "COMPLETE", "deviceId" : "358622905858543" } ] } ] } ], "completedOn" : "2026-06-09T13:40:10Z", "transactionId" : "DEP69957" }';
            try {
                $resultat = json_decode(trim($content), true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $exception) {
                $output->writeln("EXCEPTION : " . $exception->getMessage());
                exit();
            }
            if ($resultat) {
//                dd($resultat);

                $orderid = $resultat["transactions"][0]['transactionId'];
                $id = str_getcsv($orderid, "P");
                if (!str_contains($id[1], "R")) {
                    $devices = $resultat["transactions"][0]["devices"] ?? "";
                    if ($devices) {
                        $retour = array("orderId" => $id[1]);
                        foreach ($devices as $device) {
                            $imei = $device['imei'] ?? "";
                            $retour["imei"][] = $imei;
                        }
                    }
                    $retours[] = $retour;
                } else {
                    $output->writeln("Commande retour : " . $orderid);

                }
            }


        }


//        // On supprime les nouveaux terminaux
//        $commandes = array();
//        foreach ($retours as $retour) {
//            $orderid = $retour["orderId"];
//            /** @var Orders $order */
//            foreach ($retour["imei"] as $imei) {
//                $output->writeln("imei " . $imei);
//                if ($imei) {
//                    /** @var Terminal $terminal */
//                    $terminal = $this->doctrine->getRepository(Terminal::class)->findByIMEIOrSerie($imei);
//                    if ($terminal) {
//                        $output->writeln("terminal " . $terminal->getId());
//                        $terminalSuivis = $terminal->getTerminalSuivis();
//                        foreach ($terminalSuivis as $ts) {
//                            $this->doctrine->getManager()->remove($ts);
//                        }
//
//                        $enrolements = $terminal->getEnrolements();
//                        foreach ($enrolements as $ts) {
//                            $this->doctrine->getManager()->remove($ts);
//                        }
//
//                        $this->doctrine->getManager()->remove($terminal);
//
//                        /** @var Orders $commande */
//                        $output->writeln("order " . $terminal->getOrders()->getId());
//                        $commandes[] = $terminal->getOrders();
//                    }
//                }
//            }
//        }

//        foreach ($commandes as $commande) {
//            $transacs = $commande->getTransactions();
//            /** @var OrderTransaction $transac */
//            foreach ($transacs as $transac) {
//
//                $suivis = $transac->getTerminalSuivis();
//                foreach ($suivis as $suivi) {
//                    $this->doctrine->getManager()->remove($suivi);
//                }
//                $requetes = $transac->getRequetes();
//                foreach ($requetes as $requete) {
//                    $this->doctrine->getManager()->remove($requete);
//                }
//                $this->doctrine->getManager()->remove($transac);
//            }
//
//            $this->doctrine->getManager()->remove($commande);
//        }


        foreach ($retours as $retour) {
            $orderid = $retour["orderId"];
            /** @var Orders $order */
            $order = $this->doctrine->getRepository(Orders::class)->find($orderid);
            if ($order) {
                foreach ($retour["imei"] as $imei) {
                    /** @var Terminal $terminal */
                    $terminal = $this->doctrine->getRepository(Terminal::class)->findByIMEIOrSerie($imei);
                    if (!$terminal) {
                        $trouve=false;
                        foreach ($order->getTerminauxEnroles() as $terminal) {
                            if ($terminal->getNumeroIMEI() == "") {
 //                               $output->writeln("commande " . $order->getId() . " terminal " . $terminal->getId() . " imei " . $imei);
                                $trouve=true;
                                $terminal->setNumeroIMEI($imei);
                                $this->doctrine->getManager()->persist(($terminal));
                                break;
                            }
                        }
                        if (!$trouve) {
                            $output->writeln("commande " . $order->getId() . " imei " . $imei." non placé");
                        }
                    } else {
//                        $output->writeln("commande " . $order->getId() . " imei ".$imei." terminal " . $terminal->getId() . " trouve");
                        $cmd = $terminal->getOrders();
                        if ($cmd) {
                            if ($cmd->getId() != $orderid) {
                                $output->writeln("commande " . $orderid . "/" . $cmd->getId() . " imei " . $imei . " terminal " . $terminal->getId() . " avec probleme");
                            }
                        } else {
                            $output->writeln("cmd ".$orderid." imei " . $imei . " terminal " . $terminal->getId() . " sans commande");
                        }

                    }
                }
            }
        }

 //      $this->doctrine->getManager()->flush();

       // $this->doctrine->getManager()->flush();
        return Command::SUCCESS;
    }

    protected
    function configure(): void
    {
        $this
            // the command help shown when running the command with the "--help" option
            ->setHelp("Cette command permet de lancer le traitement des commandes en attentes");
    }

}
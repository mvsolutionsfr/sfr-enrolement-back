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
// php bin/console app:rattrapageBugIMEIaVideCommand
// the name of the command is what users type after "php bin/console app:migration"
#[AsCommand(
    name: 'app:rattrapageBugIMEIaVideAppleCommand',
    description: '',
    hidden: false
)]
class RattrapageBugIMEIaVideAppleCommand extends Command
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


        // APPLE
        $handle = fopen("mig/json_dep2.txt", "r");
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
//            dd($content);
            try {
                $resultat = json_decode(trim($content), true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $exception) {
                $output->writeln("EXCEPTION : " . $exception->getMessage());
                exit();
            }
            if ($resultat) {
//                dd($resultat);

                $orderid = $resultat["orders"][0]['orderNumber'];
                $id = str_getcsv($orderid, "P");
                if ($id[0] == "DE") {
                    $devices = $resultat["orders"][0]["deliveries"][0]["devices"] ?? "";
                    if ($devices) {
                        $retour = array("orderId" => $id[1]);
                        foreach ($devices as $device) {
                            $imei = $device['deviceId'] ?? "";
                            $retour["imei"][] = $imei;
                        }
                    }
                    $retours[] = $retour;
                }
            }
        }

        // On supprime les nouveaux terminaux
//        $commandes = array();
//        foreach ($retours as $retour) {
//            $orderid = $retour["orderId"];
//            /** @var Orders $order */
//            foreach ($retour["imei"] as $imei) {
//                $output->write("imei " . $imei. " ");
//                if ($imei) {
//                    /** @var Terminal $terminal */
//                    $terminal = $this->doctrine->getRepository(Terminal::class)->findByIMEIOrSerie($imei);
//                    if ($terminal) {
//                        $commande = $terminal->getOrders();
//                        if ($commande->getId() != $orderid) {
//                            $terminalSuivis = $terminal->getTerminalSuivis();
//                            foreach ($terminalSuivis as $ts) {
//                                $this->doctrine->getManager()->remove($ts);
//                            }
//
//                            $enrolements = $terminal->getEnrolements();
//                            foreach ($enrolements as $ts) {
//                                $this->doctrine->getManager()->remove($ts);
//                            }
//
//                            $this->doctrine->getManager()->remove($terminal);
//
//                            /** @var Orders $commande */
//                            $output->writeln("terminal " . $terminal->getId()." order " . $terminal->getOrders()->getId());
//                            $commandes[] = $terminal->getOrders();
//                        } else {
//                            $output->writeln("terminal deja corrigé order " . $orderid);
//                        }
//                    } else {
//                        $output->writeln("introuvable");
//                    }
//                }
//            }
//        }

        $output->writeln("=============================");

        foreach ($retours as $retour) {
            $orderid = $retour["orderId"];
            /** @var Orders $order */
            $order = $this->doctrine->getRepository(Orders::class)->find($orderid);
            if ($order) {
                foreach ($retour["imei"] as $imei) {
                    $terminal = $this->doctrine->getRepository(Terminal::class)->findByIMEIOrSerie($imei);
                    if (!$terminal) {
                        $trouve=false;
                        foreach ($order->getTerminauxEnroles() as $terminal) {
                            if ($terminal->getNumeroIMEI() == "") {
                                $output->writeln("commande " . $order->getId() . " terminal " . $terminal->getId() . " imei " . $imei);
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
                        $output->writeln("commande " . $order->getId() . " imei ".$imei." terminal " . $terminal->getId() . " trouve");

                    }
                }
            }
        }

        $this->doctrine->getManager()->flush();

        //       $this->doctrine->getManager()->flush();
        return Command::SUCCESS;
    }

    protected function configure(): void
    {
        $this
            // the command help shown when running the command with the "--help" option
            ->setHelp("Cette command permet de lancer le traitement des commandes en attentes");
    }

}
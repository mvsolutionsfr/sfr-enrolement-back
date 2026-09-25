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
    name: 'app:rattrapageBugIMEIaVideZTCommand',
    description: '',
    hidden: false
)]
class RattrapageBugIMEIaVideZTCommand extends Command
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
        $handle = fopen("mig/json_zt2.txt", "r");
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
                $deviceId = $resultat["response"]["perDeviceStatus"][0]["result"]["deviceId"] ?? "";
                $imei = $resultat["response"]["perDeviceStatus"][0]["claim"]["deviceIdentifier"]["imei"] ?? "";
                if ($deviceId) {
                    /** @var Terminal $terminal */
                    $terminal = $this->doctrine->getRepository(Terminal::class)->findByIMEIOrSerie($deviceId);
                    if ($terminal) {
                        if ($terminal->getNumeroIMEI()=='') {
                            $terminal->setNumeroIMEI($imei);
                            $this->doctrine->getManager()->persist(($terminal));
                            $output->writeln("deviceId " . $deviceId . " imei " . $imei);
                        }
                    }


                }

            }
        }



       $this->doctrine->getManager()->flush();

        //       $this->doctrine->getManager()->flush();
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
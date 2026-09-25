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
use App\Toolbox\StatutEnrolementEnum;
use App\Toolbox\StatutImeiEnum;
use App\Toolbox\StatutRequeteEnum;
use App\Toolbox\TypeEnrolementEnum;
use DateTime;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\RequestStack;

// ATTENTION: POINTER SUR LA BBD CIBLE !!!!!!!!!!!!!!
//
// php bin/console app:list_clients
#[AsCommand(
    name: 'app:list_clients',
    description: 'Cette commande  fait un rattrapage des commandes sans vendorId',
    hidden: false
)]
class ListClientCommand extends Command
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
        $_SERVER['ERIC'] = true;

        $clients = $this->doctrine->getRepository(Enseigne::class)->find(6);

        $file = fopen("mig/clients.csv", "a");

        /** @var Client $client */
        foreach ($clients as $client) {
            $pgms = $client->getPgmEnrolements();
            /** @var PgmEnrolement $pgm */
            $apple = "";
            $knox = "";
            $zt = "";
            foreach ($pgms as $pgm)
            {
                if ($pgm->getPgmEnrolement()->getId() == 1) if ($apple == "") $apple = $pgm->getCustomerId(); else $apple .= "/".$pgm->getCustomerId();
                if ($pgm->getPgmEnrolement()->getId() == 2) if ($knox == "") $knox = $pgm->getCustomerId(); else $knox .= "/".$pgm->getCustomerId();
                if ($pgm->getPgmEnrolement()->getId() == 3) if ($zt == "") $zt = $pgm->getCustomerId(); else $zt .= "/".$pgm->getCustomerId();
            }
            $str = $client->getId().";".$client->getRaisonSociale().";".$client->getSiren().";".$apple.";".$knox.";".$zt.PHP_EOL;
            fwrite($file,$str);

        }
        fclose($file);
        return Command::SUCCESS;
    }

    protected function configure(): void
    {
        $this
            // the command help shown when running the command with the "--help" option
            ->setHelp("Cette commande permet de rattraper les commandes sans vendorId");
    }

}
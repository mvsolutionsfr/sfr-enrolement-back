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
// php bin/console app:supp_imei imei
#[AsCommand(
    name: 'app:supp_imei  imei',
    description: 'Cette commande supprime un imei',
    hidden: false
)]
class SuppressionImeiCommand extends Command
{

    private EnrolementService $enrolementService;
    private ManagerRegistry $doctrine;
    private SessionService $sessionService;

    public function __construct(LoggerESService $logger, ManagerRegistry $doctrine, EnrolementService $enrolementService, SessionService $sessionService)
    {
        $this->enrolementService = $enrolementService;
        $this->doctrine = $doctrine;
        $this->sessionService = $sessionService;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $error = CodeErreurEnum::ok;
        $message = "";
        $_SERVER['ERIC'] = true;

        $terminalId = $input->getArgument("terminal");



        /** @var Terminal $terminal */
        $terminal =$this->doctrine->getRepository(Terminal::class)->find($terminalId);
        if ($terminal!= null) {

            $terminalSuivis=$terminal->getTerminalSuivis();
            foreach ($terminalSuivis as $ts)
            {
                $this->doctrine->getManager()->remove($ts);
            }

            $enrolements  = $terminal->getEnrolements();
            foreach ($enrolements as $ts)
            {
                $this->doctrine->getManager()->remove($ts);
            }

            $order = $terminal->getOrders();
            $order?->removeTerminauxEnrole($terminal);

            $this->doctrine->getManager()->remove($terminal);
        }
        $this->doctrine->getManager()->flush();
        return Command::SUCCESS;
    }

    protected function configure(): void
    {
        $this
            // the command help shown when running the command with the "--help" option
            ->setHelp("Cette command permet de supprimer une terminal dans la base");
        $this->addArgument('terminal', InputArgument::REQUIRED, "Terminal à liberer");;
    }

}
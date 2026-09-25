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
use App\Service\AUIService;
use App\Service\EnrolementService;
use App\Service\LoggerESService;
use App\Service\SessionService;
use App\Toolbox\LogLevelEnum;
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
// cd src
// php ../bin/console app:checkusercommand id
// the name of the command is what users type after "php bin/console app:migration"
#[AsCommand(
    name: 'app:checkusercommand',
    description: 'Cette command intègre les données des fichiers présent dans migV1 sans effacer la base existante',
    hidden: false
)]
class CheckUserCommand extends Command
{
    private $pgmApple;
    private $pgmKnox;
    private $pgmZT;
    private $admin;
    private $automate;

    private $clientInconnu;
    private $dtnow;
    private $MaxFlush = 400;

    private Fabricant $fabricantApple;
    private Fabricant $fabricantSamsung;
    private Fabricant $fabricantCrosscall;

    private EnrolementService $enrolementService;
    private SessionService $sessionService;

    protected LoggerESService $logger;
    private AUIService $AUIService;
    private ObjectManager $manager;
    private ManagerRegistry $doctrine;
    private Enseigne $enseigneSFR;
    private OutputInterface $output;

    public function __construct(LoggerESService $logger, ManagerRegistry $doctrine, AdminService $adminService, AUIService $auiService)
    {
        $this->logger = $logger;
        $this->manager = $doctrine->getManager();
        $this->doctrine = $doctrine;
        $this->AUIService = $auiService;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->output = $output;

        $this->dtnow = date_create('now');
        $login = $input->getArgument("id");

        $retour = $this->AUIService->getUserData($login);
        $this->output->writeln("AUI");
        $this->output->writeln(json_encode(['retour' => $retour]));
        $retour = $this->AUIService->getRole($login);
        $this->output->writeln('Role : '.json_encode($retour));

        $retour = $this->AUIService->getUserDataFromCentric($login);
        $this->output->writeln("CENTRIC");
        $this->output->writeln(json_encode(['retour' => $retour]));

        return Command::SUCCESS;

        // or return this if some error happened during the execution
        // (it's equivalent to returning int(1))
        // return Command::FAILURE;

        // or return this to indicate incorrect command usage; e.g. invalid options
        // or missing arguments (it's equivalent to returning int(2))
        // return Command::INVALID
    }

    protected function configure(): void
    {
        $this
            // the command help shown when running the command with the "--help" option
            ->setHelp("Cette command intègre de nouveaux utilisateur à l'enseigne ");
        $this
            // ...
            ->addArgument('id', InputArgument::REQUIRED, "uperid ou idAui de l'utilisateur");;
    }

}
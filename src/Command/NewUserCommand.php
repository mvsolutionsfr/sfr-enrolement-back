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
use App\Toolbox\LogLevelEnum;
use App\Toolbox\StatutImeiEnum;
use App\Toolbox\StatutRequeteEnum;
use App\Toolbox\TypeEnrolementEnum;
use DateTime;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Util\Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\RequestStack;

// ATTENTION: POINTER SUR LA BBD CIBLE !!!!!!!!!!!!!!
// cd src
// php ../bin/console app:addnewusers 1
// the name of the command is what users type after "php bin/console app:migration"
#[AsCommand(
    name: 'app:addnewusers',
    description: 'Cette command intègre les données des fichiers présent dans migV1 sans effacer la base existante',
    hidden: false
)]
class NewUserCommand extends Command
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
    private AdminService $adminService;
    private ObjectManager $manager;
    private ManagerRegistry $doctrine;
    private Enseigne $enseigneSFR;
    private OutputInterface $output;

    public function __construct(LoggerESService $logger, ManagerRegistry $doctrine, AdminService $adminService)
    {
        $this->logger = $logger;
        $this->manager = $doctrine->getManager();
        $this->doctrine = $doctrine;
        $this->adminService = $adminService;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->output = $output;

        $this->dtnow = date_create('now');
        $ref = $input->getArgument("enseigne");
        if ($ref != null) $this->enseigneSFR = $this->doctrine->getRepository(Enseigne::class)->findOneBy(['ref' => $ref]);
        $this->admin = $this->doctrine->getRepository(Utilisateur::class)->find("1");

        $handle = fopen("../mig/uat/newusers.csv", "r");
        $lineNumber = 0;
        $utilisateursSFR = array();
        $utilisateurs = array();

        while (($rawStr = fgets($handle)) != false) {
            $lineNumber++;
            $utilisateur = str_getcsv($rawStr, ";");
            if ($ref == null) {
                $ref2 = $utilisateur[3];
                if ($ref2 == null || $ref2 == "") {
                    $this->output->writeln("Enseigne non renseigné pour " . $utilisateur[1] . " " . $utilisateur[2]);
                    exit();
                }
                $utilisateurs2SFR = array();
                $utilisateurs2 = array();
                $utilisateurs2[] = $utilisateur;
                $utilisateurs2SFR[] = $utilisateur[0];
                /** @var Enseigne $enseigne */
                $enseigne = $this->doctrine->getRepository(Enseigne::class)->findOneBy(['ref' => $ref2]);
                $this->output->writeln($enseigne->getRaisonSociale());
                try {
                    $this->adminService->usersToAddToEnseigne($utilisateurs2SFR, $enseigne, $utilisateurs2);
                } catch (\Exception $ex) {
                   $this->output->writeln($ex->getMessage());
                }
        } else {
                $utilisateurs[] = $utilisateur;
                $utilisateursSFR[] = $utilisateur[0];
            }
        }
        if ($ref != null)  $this->adminService->usersToAddToEnseigne($utilisateursSFR, $this->enseigneSFR, $utilisateurs);

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
            ->addArgument('enseigne', InputArgument::OPTIONAL, "Reference de l'enseigne Gesco");;
    }

}
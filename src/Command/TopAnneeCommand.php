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
use App\Service\KnoxRestService;
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
// php bin/console app:topanneecommand enseigneid pgmid
// the name of the command is what users type after "php bin/console app:migration"
#[AsCommand(
    name: 'app:topanneecommand',
    description: 'Cette command intègre les données des fichiers présent dans migV1 sans effacer la base existante',
    hidden: false
)]
class TopAnneeCommand extends Command
{

    private ObjectManager $manager;
    private ManagerRegistry $doctrine;
    private Enseigne $enseigne;



    public function __construct(LoggerESService $logger, ManagerRegistry $doctrine, KnoxRestService $knoxRestService)
    {
        $this->manager = $doctrine->getManager();
        $this->doctrine = $doctrine;

        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $error = CodeErreurEnum::ok;
        $message = "";
        $_SERVER['ERIC'] = true;
$topClientsAnnee="";

        $enseigneId=$input->getArgument("enseigneId");
        $pgmId=$input->getArgument("pgmId");

        $enseigne = $this->doctrine->getRepository(Enseigne::class)->find($enseigneId);
        $pgm = $this->doctrine->getRepository(PgmEnrolement::class)->find($pgmId);

        $clients = $enseigne->getClients();
        if ($clients) {
            $enrolementRepository = $this->doctrine->getRepository(Enrolement::class);
            $topClientsAnnee = $enrolementRepository->topClientAnnee($clients, $pgm);
        }

        $output->writeln(json_encode($topClientsAnnee));
        return Command::SUCCESS;
    }

    protected function configure(): void
    {
        $this
            // the command help shown when running the command with the "--help" option
            ->setHelp("Cette command permet de lancer le traitement de check status d'un appel knox");
        $this->addArgument('enseigneId', InputArgument::REQUIRED, "Id Enseigne");
        $this->addArgument('pgmId', InputArgument::REQUIRED, "Id Pgm");

        ;
    }

}
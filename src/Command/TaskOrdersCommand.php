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
use App\Toolbox\ProgrammeEnrolementEnum;
use App\Toolbox\SourceEnum;
use App\Toolbox\StatutImeiEnum;
use App\Toolbox\StatutRequeteEnum;
use App\Toolbox\TypeEnrolementEnum;
use DateTime;
use Doctrine\ORM\Tools\Pagination\Paginator;
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
// php bin/console app:taskorder
// the name of the command is what users type after "php bin/console app:migration"
#[AsCommand(
    name: 'app:taskorder',
    description: 'Cette commande présente le résumé de l\'ensemble des actions d`\'une commande',
    hidden: false
)]
class TaskOrdersCommand extends Command
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

        $commandesToCheck = $this->doctrine->getRepository(Orders::class)->findByCheckStatus();

//        if ($_SERVER["ERIC"] ?? false) dd($commandesToCheck);

        /** @var Orders $commande */
        foreach ($commandesToCheck as $commande) {


            $statut = $commande->getDernierStatut();
            /** @var PgmEnrolement $programme */
            $programme = $commande->getPgmEnrolement();
            if ($programme->getLibelle() == ProgrammeEnrolementEnum::Knox->name) {
                $output->writeln($commande->getId());
            }
        }
        return Command::SUCCESS;
    }

    protected function configure(): void
    {
        $this
            // the command help shown when running the command with the "--help" option
            ->setHelp("Cette command permet de lancer le traitement des commandes en attentes");
          //$this->addArgument('commande', InputArgument::REQUIRED, "Id de la commande");
        ;
    }

}
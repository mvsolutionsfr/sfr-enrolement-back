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
use App\Service\DepAppleRestService;
use App\Service\LoggerESService;
use App\Toolbox\LogLevelEnum;
use App\Toolbox\StatutImeiEnum;
use App\Toolbox\StatutRequeteEnum;
use App\Toolbox\TypeEnrolementEnum;
use DateInterval;
use DateTime;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\RequestStack;

// php bin/console app:migration-updatedatefromapple

#[AsCommand(
    name: 'app:migration-updatedatefromapple',
    description: 'Cette command intègre les dates de commande d\'Apple',
    hidden: false
)]
class MigrationUpdateDateFromAppleCommand extends Command
{
    private $pgmApple;
    private $pgmKnox;
    private $pgmZT;
    private $admin;
    private $clientInconnu;
    private $dtnow;
    private $MaxFlush = 400;


    private Fabricant $fabricantApple;
    private Fabricant $fabricantSamsung;
    private Fabricant $fabricantCrosscall;


    protected LoggerESService $logger;
    private ObjectManager $manager;
    private ManagerRegistry $doctrine;
    private Enseigne $enseigneSFR;
    private OutputInterface $output;
    private DepAppleRestService $appleRestService;

    public function __construct(LoggerESService $logger, ManagerRegistry $doctrine, DepAppleRestService $appleRestService)
    {
        $this->logger = $logger;
        $this->manager = $doctrine->getManager();
        $this->doctrine = $doctrine;
        $this->appleRestService = $appleRestService;
        ini_set("memory_limit", -1);
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->output=$output;
        $dateDebutMigration =date_create("7/08/2024");
        $dateDebutMEP = date_create("7/27/2024");

        $commandes = $this->doctrine->getRepository(Orders::class)->findByDates($dateDebutMigration, $dateDebutMEP);
        /** @var Orders $commande */
        foreach ($commandes as $commande)
        {
            if ($commande->getPgmEnrolement()->getId() != 1) continue;
            $resultat = $this->appleRestService->updateDateCommand($commande->getResellerId(),$commande->getId());
            $dateC = "";
            if ($resultat) $dateC = $resultat["date"] ?? "";
            $dateCreation = null;
            date_default_timezone_set('UTC');
            if ($dateC != "") $dateCreation = DateTime::createFromFormat('Y-m-d\TH:i:s+',$dateC);
            if ($dateCreation) {
                $dateCreation->add(DateInterval::createFromDateString('2 hours'));
                $output->writeln("[".$commande->getId()."] ".$commande->getDateCreation()->format("d/M/Y H:i:s")." - ".$dateCreation->format("d/M/Y H:i:s"));
                $commande->setDateCreation($dateCreation);
                $this->doctrine->getManager()->persist($commande);
            } else {
                $output->writeln("[".$commande->getId()."] ERROR ".json_encode($resultat));
            }

        }
        $this->doctrine->getManager()->flush();
        $output->writeln("Fin migration");

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
            ->setHelp('Cette command intègre les dates de depApple d\'enrolement des fichiers présent dans mig/v1');
    }



}
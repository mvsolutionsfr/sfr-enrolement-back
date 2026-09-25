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
// php bin/console app:SupprimeSerieImeiCommand
// the name of the command is what users type after "php bin/console app:migration"
#[AsCommand(
    name: 'app:SupprimeSerieImeiCommand',
    description: '',
    hidden: true
)]
class SupprimeSerieImeiCommand extends Command
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
        $imeis[] = "357072488685049";
        $imeis[] = "357072488771294";
        $imeis[] = "357072488771013";
        $imeis[] = "357072488694124";
        $imeis[] = "357072488893007";
        $imeis[] = "357072488703818";
        $imeis[] = "357072488694561";
        $imeis[] = "357072488755982";
        $imeis[] = "357072488871060";
        $imeis[] = "357072488722792";
        $imeis[] = "351010649786344";
        $imeis[] = "350738804370980";




        $imeisByOrder = array();
        foreach ($imeis as $imei) {
            /** @var Terminal $terminal */
            $terminaux = $this->doctrine->getRepository(Terminal::class)->findBy(['numeroIMEI' => $imei]);
            if ($terminaux != null || count($terminaux) > 0) {

                foreach ($terminaux as $terminal) {
//                    if (count($terminaux) > 1 ) {
                        $output->writeln($imei . " " . $terminal->getId() . " " . $terminal->getStatut());
//                        if ($terminal->getStatut() == 2) {
//                            $terminalSuivis=$terminal->getTerminalSuivis();
//                            foreach ($terminalSuivis as $ts)
//                            {
//                                $this->doctrine->getManager()->remove($ts);
//                            }
//
//                            $enrolements  = $terminal->getEnrolements();
//                            foreach ($enrolements as $ts)
//                            {
//                                $this->doctrine->getManager()->remove($ts);
//                            }
//
//                            $order = $terminal->getOrders();
//                            $order?->removeTerminauxEnrole($terminal);
//
//                            $this->doctrine->getManager()->remove($terminal);
//                        }
//                    }
                        if ($terminal->getNumeroSerie() != null) {
                        $terminal->setNumeroSerie(null);
                        $this->doctrine->getManager()->persist($terminal);
                    }
                }
            } else
            {
                $output->writeln($imei. " Non enrolé");
            }

        }
        $this->doctrine->getManager()->flush();
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
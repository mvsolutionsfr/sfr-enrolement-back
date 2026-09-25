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
// php bin/console app:desincription_imei imei
#[AsCommand(
    name: 'app:desincription_imei',
    hidden: false
)]
class DesincriptionIMEICommand extends Command
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
        $user = $this->doctrine->getRepository(Utilisateur::class)->find(1);
        $this->sessionService->setUtilisateur($user);
        $enseigne = $this->doctrine->getRepository(Enseigne::class)->find(1);
        $this->sessionService->setEnseigne($enseigne);
        $pgm = $this->doctrine->getRepository(PgmEnrolement::class)->find(1);
        $this->sessionService->setProgramme($pgm);

//        $imei = $input->getArgument("imei");
//        /** @var Terminal $terminal */
//        $terminal = $this->doctrine->getRepository(Terminal::class)->findByIMEIOrSerie($imei);
//        if ($terminal) {
//            if ($terminal->getStatut() == StatutImeiEnum::Enrole->value) {
//                $order = $terminal->getOrders();
        $imeis = array();
        $imeis[] = "350308473755604";
//        $imeis[] = "356794114765983";
//        $imeis[] = "356784110947107";

                $retour = $this->enrolementService->desenrolement($user, $imeis);
                dd($retour);
//                $output->writeln("Terminal en cours de desenrolement de la commande ".$order->getId());
//            } else {
//                $output->writeln("IMEI non enrolé");
//                return Command::FAILURE;
//            }
//        } else {
//            $output->writeln("Terminal introuvable");
//            return Command::FAILURE;
//        }
        return Command::SUCCESS;
    }

    protected function configure(): void
    {
        $this
            // the command help shown when running the command with the "--help" option
            ->setHelp("Cette commande permet de desincrire les terminaux du'une commande");
//        $this->addArgument('imei', InputArgument::REQUIRED, "Numero imei");
    }

}
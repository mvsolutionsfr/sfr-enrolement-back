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
// php bin/console app:desincription_commande numCmd
#[AsCommand(
    name: 'app:desincription_commande',
    hidden: false
)]
class DesincriptionCmdKnoxCommand extends Command
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
        $orderId=$input->getArgument("numCmd");
        $commandes = $this->doctrine->getRepository(Orders::class)->findBy( ["id" => $orderId]);
        /** @var Orders $commande */
        foreach ($commandes as $commande)
        {
            $this->sessionService->setEnseigne($commande->getEnseigne());
            $this->sessionService->setProgramme($commande->getPgmEnrolement());
            $terminaux = $commande->getTerminauxEnroles();
            /** @var Terminal $terminal */
            $imeis = array();
            foreach ($terminaux as $terminal)
                {
                    $imeis[] = $terminal->getNumeroIMEI();
                }
//            dd($imeis);
            $this->enrolementService->desenrolement($user,$imeis);
        }
        return Command::SUCCESS;
    }

    protected function configure(): void
    {
        $this
            // the command help shown when running the command with the "--help" option
            ->setHelp("Cette commande permet de desincrire les terminaux du'une commande");
        $this->addArgument('numCmd', InputArgument::REQUIRED, "Numero de commande ");
    }

}
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
// php bin/console app:affecte_imei_commande
#[AsCommand(
    name: 'app:affecte_imei_commande',
    hidden: false
)]
class AffecteIMEICommandeCommand extends Command
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
        /** @var Terminal $terminal */
        $terminal = $this->doctrine->getRepository(Terminal::class)->findByIMEIOrSerie("TI00041499");
        /** @var Orders $order1 */
        $order1 = $this->doctrine->getRepository(Orders::class)->find(69328);
 //       /** @var Orders $order2 */
 //       $order2 = $this->doctrine->getRepository(Orders::class)->find(18387);
        if ($terminal) {
            $order1->addTerminauxEnrole($terminal);
            $enrolement = new Enrolement();
            $enrolement->setClient($order1->getClient());
            $enrolement->setTerminal($terminal);
            $enrolement->setDate(date_create('now'));

            $terminal->setStatut(StatutImeiEnum::Enrole->value);
            $this->doctrine->getManager()->persist($terminal);
            $this->doctrine->getManager()->persist($order1);
            $this->doctrine->getManager()->persist($enrolement);

//            dd($terminal->getEnrolements());
//            /** @var Enrolement $enrolement */
//            foreach ($terminal->getEnrolements() as $enrolement)
//            {
//                $output->writeln($enrolement->getClient()->getRaisonSociale());
////                if ($enrolement->getClient()->getRaisonSociale() == "ALLSPRING") $this->doctrine->getManager()->remove($enrolement);
//            }
//
//            /** @var TerminalSuivi $ts */
//            foreach ($terminal->getTerminalSuivis() as $ts)
//                       {
//                           $output->writeln($ts->getTransaction()->getId());
////                           if ($ts->getTransaction()->getId() == 20757 ) $this->doctrine->getManager()->remove($ts);
//                       }


//            $order1->removeTerminauxEnrole($terminal);
//            $order2->addTerminauxEnrole($terminal);
        } else {
            $output->writeln("Terminal introuvable");
            return Command::FAILURE;
        }
        $this->doctrine->getManager()->flush();
        return Command::SUCCESS;
    }

    protected function configure(): void
    {
        $this
            // the command help shown when running the command with the "--help" option
            ->setHelp("Cette commande permet de desincrire les terminaux du'une commande");
    }

}
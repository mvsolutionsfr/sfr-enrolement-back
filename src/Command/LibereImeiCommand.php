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
use Doctrine\Common\Collections\ArrayCollection;
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
// php bin/console app:libereimei  numImei
// the name of the command is what users type after "php bin/console app:migration"
#[AsCommand(
    name: 'app:libereimei numImei',
    description: 'Cette command libère un Imei',
    hidden: false
)]
class LibereImeiCommand extends Command
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

        $imeis[] = "350761567586725";
        $imeis[] = "356826119243259";
        $imeis[] = "351010649922188";
        $imeis[] = "356780113414103";
        $imeis[] = "356781113296938";
        $imeis[] = "356778114396295";
        $imeis[] = "356786114270592";
        $imeis[] = "356786114378809";
        $imeis[] = "356787110824747";
        $imeis[] = "356787110951540";
        $imeis[] = "356787110873942";
        $imeis[] = "356787110786193";
        $imeis[] = "356787110802115";
        $imeis[] = "356787110899996";
        $imeis[] = "356788110674538";
        $imeis[] = "356788110658283";
        $imeis[] = "356779110736187";
        $imeis[] = "356788110562733";
        $imeis[] = "353878132560188";
        $imeis[] = "353504400988931";
        $imeis[] = "353878136707942";
        $imeis[] = "354267899487042";
        $imeis[] = "353878132463474";
        $imeis[] = "359949180229671";
        $imeis[] = "354267899771890";
        $imeis[] = "354267898557886";

        $error = CodeErreurEnum::ok;
        $message = "";
        $_SERVER['ERIC'] = true;

 //       $imei = $input->getArgument("imei");

        foreach ($imeis as $imei) {
$output->write("IMEI ".$imei);
            /** @var Terminal $terminal */
            $terminaux = $this->doctrine->getRepository(Terminal::class)->findBy(['numeroIMEI' => $imei]);
            if ($terminaux != null || count($terminaux) > 0) {
                $terminal = $terminaux[0];
                $terminal->setStatut(StatutImeiEnum::Enrole->value);

                $suivis = $terminal->getTerminalSuivis();
                /** @var TerminalSuivi $suivi */
                $ordersCollection = new ArrayCollection();
                $suivi = $suivis->last();
                if ($suivi) {
                    $orderId = $suivi->getTransaction()->getOrders()->getId();
                    $order = $this->doctrine->getRepository(Orders::class)->find($orderId);
                    /** @var Orders $order */
                    if ($order) {
                        $output->write(" Order ".$orderId);
                        $enrolement = new Enrolement();
                        $enrolement->setDate($suivi->getTransaction()->getDateCreation());
                        $enrolement->setClient($order->getClient());
                        $output->writeln(" Client ".$order->getClient()->getRaisonSociale());
                        $enrolement->setTerminal($terminal);
//                        $this->doctrine->getManager()->persist($enrolement);
                        $terminal->setOrders($order);
                    }
                }
                $this->doctrine->getManager()->flush();
                $this->doctrine->getManager()->persist($terminal);
            }
        }
        return Command::SUCCESS;
    }

    protected function configure(): void
    {
        $this
            // the command help shown when running the command with the "--help" option
            ->setHelp("Cette command permet de lancer le traitement des commandes en attentes");
      //  $this->addArgument('imei', InputArgument::REQUIRED, "Imei à liberer");;
    }

}
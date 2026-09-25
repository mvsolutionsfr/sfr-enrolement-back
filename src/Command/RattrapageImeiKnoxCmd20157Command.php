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
use App\Toolbox\StatutEnrolementEnum;
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
// php bin/console app:rattrage_20157
#[AsCommand(
    name: 'app:rattrage_20157',
    description: 'Cette commande  fait un rattrapage des commandes sans vendorId',
    hidden: false
)]
class RattrapageImeiKnoxCmd20157Command extends Command
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
        $error = CodeErreurEnum::ok;
        $message = "";
        $_SERVER['ERIC'] = true;


        /** @var OrderTransaction $orderTransaction */
        $orderTransaction = $this->doctrine->getRepository(OrderTransaction::class)->find(80720);
        $orderTransaction->setStatut(StatutEnrolementEnum::Ok->value);
        $this->doctrine->getManager()->persist($orderTransaction);
        /** @var Orders $order */
//        $order = $this->doctrine->getRepository(Orders::class)->find(20157);
        $order = $orderTransaction->getOrders();
        $order->setDernierStatut(StatutEnrolementEnum::Ok->value);
        $this->doctrine->getManager()->persist($order);

//        $client = $order->getClient();
//        $terminauxSuivi = $this->doctrine->getRepository(TerminalSuivi::class)->findBy(["transaction" => $orderTransaction]);
//        /** @var Terminal $terminal */
//        $orders = array();
//        /** @var TerminalSuivi $terminalSuivi */
////        $terminalSuivi = $terminauxSuivi[0];
////        $this->doctrine->getManager()->remove($terminalSuivi);
//        foreach ($terminauxSuivi as $terminalSuivi) {
//            $terminal = $terminalSuivi->getTerminal();
//            $output->writeln($terminal->getId(). "- ".$terminalSuivi->getMessage());
//            $terminal->setOrders($order);
//            $enrol = new Enrolement();
//            $enrol->setClient($client);
//            $enrol->setTerminal($terminal);
//            $enrol->setDate($orderTransaction->getDateCreation());
//            $this->doctrine->getManager()->persist($enrol);
//$terminal->setStatut(StatutImeiEnum::Enrole->value);
//            $this->doctrine->getManager()->persist($terminal);
//
////            $this->doctrine->getManager()->persist($terminal);
////            $enrols = $terminal->getEnrolements();
////            /** @var Enrolement $enrol */
////            $enrol = $enrols[0];
////            $enrol->setClient($client);
//        }
        $this->doctrine->getManager()->flush();

        return Command::SUCCESS;
    }

    protected function configure(): void
    {
        $this
            // the command help shown when running the command with the "--help" option
            ->setHelp("Cette commande permet de rattraper les commandes sans vendorId");
    }

}
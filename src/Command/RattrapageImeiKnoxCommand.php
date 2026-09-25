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
// php bin/console app:rattrage_imei_knox
#[AsCommand(
    name: 'app:rattrage_imei_knox',
    description: 'Cette commande  fait un rattrapage des commandes sans vendorId',
    hidden: false
)]
class RattrapageImeiKnoxCommand extends Command
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

        $terminaux = $this->doctrine->getRepository(Terminal::class)->findBy(["fabricant" => 44, "statut" => 0, "programme" => 2 ]);
        /** @var Terminal $terminal */
        $orders = array();
        foreach ($terminaux as $terminal) {
            $order = $terminal->getOrders();
            if (in_array($order->getId(), $orders))
                continue;
            else
                $orders[] = $order->getId();
           if ($order->getId() < 33000 ) continue;
            $output->writeln($order->getId()." [".$order->getDateCreation()->format("Y-m-d\TH:i:s\Z")."] statut [".StatutEnrolementEnum::tryFrom($order->getDernierStatut())->name."]");
            /** @var OrderTransaction $lastTransaction */
            $lastTransaction = $this->doctrine->getRepository(OrderTransaction::class)->findLastTransactionByCommande($order);
            if ($lastTransaction && $lastTransaction->getStatut() == 2) {
                $suivis=$lastTransaction->getTerminalSuivis();
                foreach ($suivis as $suivi)
                {
                    $this->doctrine->getManager()->remove($suivi);
                }
                $requetes = $lastTransaction->getRequetes();
                foreach ($requetes as $requete) {
                    $this->doctrine->getManager()->remove($requete);
                }
                $this->doctrine->getManager()->remove($lastTransaction);
                $order->setResellerId("");
                $order->setDernierStatut(1);
                $this->doctrine->getManager()->persist($order);
            }
        }
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
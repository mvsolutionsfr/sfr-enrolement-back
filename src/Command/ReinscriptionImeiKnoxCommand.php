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
// php bin/console app:reinscription_commande numCmd
#[AsCommand(
    name: 'app:reinscription_commande',
    hidden: false
)]
class ReinscriptionImeiKnoxCommand extends Command
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
        $user = $this->doctrine->getRepository(Utilisateur::class)->find(1);
        $orderId = $input->getArgument("numCmd");
        /** @var Fabricant $fabSamsung */
        $fabSamsung = $this->doctrine->getRepository(Fabricant::class)->find(44);
        $commandes = $this->doctrine->getRepository(Orders::class)->findBy(["id" => $orderId]);
        /** @var Orders $commande */
        foreach ($commandes as $commande) {
            /** @var OrderTransaction $transaction */
            $transaction = null;
            $transactions = $commande->getTransactions();
            if ($transactions) $transaction = $transactions->first();
            if ($transaction) {
                $suivis = $transaction->getTerminalSuivis();
                /** @var Terminal $terminal */
                $imeis = array();
                /** @var TerminalSuivi $suivi */
                foreach ($suivis as $suivi) {
                    $terminal = $suivi->getTerminal();
                    $imeis[] = $terminal->getNumeroIMEI();
                }
                $client = $commande->getClient();
                $pgmsClients = $this->doctrine->getRepository(PgmClient::class)->findByClientByPgm($client,$commande->getPgmEnrolement());
                if ($pgmsClients) {
                    if (count($pgmsClients) > 1) {
                        $output->writeln("Client ayant plusieurs customerIds");
                        /** @var PgmClient $pgmClient */
                        foreach ($pgmsClients as $pgmClient)
                        {
                            $output->writeln($pgmClient->getCustomerId());
                        }
                        return Command::FAILURE;
                    } else {
                        $output->writeln("CustomerId : ".$pgmsClients[0]->getCustomerId());
                        $retour = $this->enrolementService->enrolement($user, $commande->getPgmEnrolement(), $commande->getClient(), $pgmsClients[0]->getCustomerId(), $commande->getEnseigne(), "", $imeis, $fabSamsung);
                        $cmd = $retour["cmd"] ?? null;
                        $erreurs = $retour["erreur"] ;
                        if ($cmd)  $output->writeln("CmdId : ".$cmd->getId());
                        foreach ($erreurs as $erreur) $output->writeln("Erreur : ".$erreur);

                    }
                } else {
                    $output->writeln("Client n'ayant pas de customerId");
                    return Command::FAILURE;
                }
            } else
                return Command::FAILURE;

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
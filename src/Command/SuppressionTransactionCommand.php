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
// php bin/console app:supprime_transacs
// the name of the command is what users type after "php bin/console app:migration"
#[AsCommand(
    name: 'app:supprime_transacs ',
    description: 'Cette commande supprime les transaction d\'une liste',
    hidden: false
)]
class SuppressionTransactionCommand extends Command
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

        $transacs = array();
        $transacs[] = '163533';
//        $transacs[] = '51202';
//        $transacs[] = '51225';
//        $transacs[] = '51226';
//        $transacs[] = '51229';
//        $transacs[] = '51231';
//        $transacs[] = '51235';
//        $transacs[] = '51252';
//        $transacs[] = '51431';
        //       $transacs[] = '51434';
        //      $transacs[] = '51437';


        $error = CodeErreurEnum::ok;
        $message = "";
        $_SERVER['ERIC'] = true;


        foreach ($transacs as $transacId) {
            /** @var OrderTransaction $commande */
            $output->writeln($transacId);
/** @var OrderTransaction $transac */
            $transac = $this->doctrine->getRepository(OrderTransaction::class)->find($transacId);
            if ($transac != null) {
                $suivis = $transac->getTerminalSuivis();
                /** @var TerminalSuivi $suivi */
                foreach ($suivis as $suivi) {
                    $terminal = $suivi->getTerminal();
                    $terminal->setStatut(StatutImeiEnum::Enrole->value);
                    $this->doctrine->getManager()->persist($terminal);
                    $this->doctrine->getManager()->remove($suivi);
                }
                $requetes = $transac->getRequetes();
                foreach ($requetes as $requete) {
                    $this->doctrine->getManager()->remove($requete);
                }
                $this->doctrine->getManager()->remove($transac);
            }
        }
        $this->doctrine->getManager()->flush();
        return Command::SUCCESS;
    }

    protected function configure(): void
    {
        $this
            // the command help shown when running the command with the "--help" option
            ->setHelp("Cette command permet de supprimer une commande dans la base");
    }

}
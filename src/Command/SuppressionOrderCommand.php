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
// php bin/console app:supprimecommande  numCmd
// the name of the command is what users type after "php bin/console app:migration"
#[AsCommand(
    name: 'app:supprimecommande  numCmd',
    description: 'Cette commande libère une commande',
    hidden: false
)]
class SuppressionOrderCommand extends Command
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

        $commandeId = $input->getArgument("commande");

        /** @var Orders $commande */
        $commande =$this->doctrine->getRepository(Orders::class)->find($commandeId);
        if ($commande!= null) {

            $terminaux=$commande->getTerminauxEnroles();
            foreach ($terminaux as $terminal)
            {
   //             if ($terminal->getStatut() != StatutImeiEnum::Enrole->value) {
                    $enrolements = $terminal->getEnrolements();
                    foreach ($enrolements as $enrolement) $this->doctrine->getManager()->remove($enrolement);
                    $this->doctrine->getManager()->remove($terminal);
   //             }
                $commande->removeTerminauxEnrole($terminal);
            }

            $transacs=$commande->getTransactions();
            /** @var OrderTransaction $transac */
            foreach ($transacs as $transac)
            {

                $suivis=$transac->getTerminalSuivis();
                foreach ($suivis as $suivi)
                {
                    $this->doctrine->getManager()->remove($suivi);
                }
                $requetes=$transac->getRequetes();
                foreach ($requetes as $requete)
                {
                    $this->doctrine->getManager()->remove($requete);
                }
                $this->doctrine->getManager()->remove($transac);
            }

            $this->doctrine->getManager()->remove($commande);

        }
        $this->doctrine->getManager()->flush();
        return Command::SUCCESS;
    }

    protected function configure(): void
    {
        $this
            // the command help shown when running the command with the "--help" option
            ->setHelp("Cette command permet de supprimer une commande dans la base");
        $this->addArgument('commande', InputArgument::REQUIRED, "Commande à liberer");;
    }

}
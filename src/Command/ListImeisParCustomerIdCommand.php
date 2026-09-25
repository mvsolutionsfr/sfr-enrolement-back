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
// php bin/console app:list_imeis_par_customer
#[AsCommand(
    name: 'app:list_imeis_par_customer',
    hidden: false
)]
class ListImeisParCustomerIdCommand extends Command
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

        $customerId = "1947442935";
        $pgmClients = $this->doctrine->getRepository(PgmClient::class)->findBy(["customerId" => $customerId]);

        $file = fopen("mig/imeis_" . $customerId . ".csv", "a");

        /** @var PgmClient $pgmClient */
        foreach ($pgmClients as $pgmClient) {
            $client = $pgmClient->getClient();
            $enrolements = $client->getEnrolements();
            /** @var Enrolement $enrolement */
            foreach ($enrolements as $enrolement) {
                $terminal =$enrolement->getTerminal();
                if ($terminal->getStatut() == 1)
                {
$output->writeln($terminal->getNumeroIMEI());
$terminal->setStatut(2);
                    $this->doctrine->getManager()->persist($terminal);
                }
//                if ($enrolement->getDateFin() == null) {
//                    $str = $client->getId() . ";" . $client->getRaisonSociale() . ";" . $enrolement->getTerminal()->getNumeroIMEI() . ";" . $enrolement->getTerminal()->getNumeroSerie() . ";".$enrolement->getDate()->format("Y-m-d H:i:s").PHP_EOL;
//                    fwrite($file, $str);
//                }
            }
        }
        $this->doctrine->getManager()->flush();

        fclose($file);
        return Command::SUCCESS;
    }

    protected function configure(): void
    {
        $this
            // the command help shown when running the command with the "--help" option
            ->setHelp("Cette commande permet de rattraper les commandes sans vendorId");
    }

}
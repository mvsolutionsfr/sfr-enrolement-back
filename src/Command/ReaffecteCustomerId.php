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
// php bin/console app:reaffectecustid
// the name of the command is what users type after "php bin/console app:migration"
#[AsCommand(
    name: 'app:reaffectecustid',
    description: 'Cette commande reaffecte les customer avec un espace',
    hidden: false
)]
class ReaffecteCustomerId extends Command
{

    private ManagerRegistry $doctrine;

    public function __construct(LoggerESService $logger, ManagerRegistry $doctrine, EnrolementService $enrolementService)
    {
        $this->doctrine = $doctrine;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {

        $lcommandes = array();
        $imeis = $this->chargeImeis();


        $error = CodeErreurEnum::ok;
        $message = "";
        $_SERVER['ERIC'] = true;
        $ordersCollection = new ArrayCollection();

        $orders = $this->doctrine->getRepository(Orders::class)->findBy(["customerId" => "1948444417 "]);

        /** @var Orders $order */
        foreach ($orders as $order) {
            $output->writeln($order->getId(). "[".$order->getCustomerId()."]");
            $order->setCustomerId("1948444417");
            $this->doctrine->getManager()->persist($order);
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

    protected function chargeImeis(): array
    {
        $imeis[]="356808581021652";
//        $imeis[]="353452301873184";
//        $imeis[]="353452301875064";
//        $imeis[]="353452301874547";
//        $imeis[]="352681304799308";
//        $imeis[]="353452301921850";
//        $imeis[]="355241324711234";
//        $imeis[]="355241324712943";
//        $imeis[]="357262979290017";
//        $imeis[]="357262979289258";
//        $imeis[]="352887816473429";

        return $imeis;

    }
}
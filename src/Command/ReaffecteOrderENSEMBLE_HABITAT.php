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
// php bin/console app:reaffecteOrderENSEMBLE_HABITAT
// the name of the command is what users type after "php bin/console app:migration"
#[AsCommand(
    name: 'app:reaffecteOrderENSEMBLE_HABITAT',
    hidden: false
)]
class ReaffecteOrderENSEMBLE_HABITAT extends Command
{

    private ManagerRegistry $doctrine;

    public function __construct(LoggerESService $logger, ManagerRegistry $doctrine, EnrolementService $enrolementService)
    {
        $this->doctrine = $doctrine;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {

        $clientIds[] = "349";
        $clientIds[] = "376";
        $clientIds[] = "1150";
        $clientIds[] = "1152";
        $clientIds[] = "1161";
        $clientIds[] = "1192";
        $clientIds[] = "1226";
        $clientIds[] = "1287";
        $clientIds[] = "1336";
        $clientIds[] = "1372";
        $clientIds[] = "1526";

        $error = CodeErreurEnum::ok;
        $message = "";
        $_SERVER['ERIC'] = true;
        $ordersCollection = new ArrayCollection();

        $comeIn =  $this->doctrine->getRepository(Enseigne::class)->find(27);
        /** @var Client $client */
        foreach ($clientIds as $clientId) {
            $client = $this->doctrine->getRepository(Client::class)->find($clientId);
            $client->setEnseigne($comeIn);
            /** @var Orders $order */
            foreach ($client->getOrders() as $order)
            {
                $order->setEnseigne($comeIn);
                $this->doctrine->getManager()->persist($order);
            }
            $this->doctrine->getManager()->persist($client);

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
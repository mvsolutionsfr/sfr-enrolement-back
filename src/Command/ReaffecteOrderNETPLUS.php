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
// php bin/console app:reaffecteOrderNETPLUS
// the name of the command is what users type after "php bin/console app:migration"
#[AsCommand(
    name: 'app:reaffecteOrderNETPLUS',
    hidden: false
)]
class ReaffecteOrderNETPLUS extends Command
{

    private ManagerRegistry $doctrine;

    public function __construct(LoggerESService $logger, ManagerRegistry $doctrine, EnrolementService $enrolementService)
    {
        $this->doctrine = $doctrine;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {

        $clientIds[] = "100";
        $clientIds[] = "104";
        $clientIds[] = "105";
        $clientIds[] = "116";
        $clientIds[] = "117";
        $clientIds[] = "119";
        $clientIds[] = "133";
        $clientIds[] = "143";
        $clientIds[] = "146";
        $clientIds[] = "152";
        $clientIds[] = "154";
        $clientIds[] = "158";
        $clientIds[] = "173";
        $clientIds[] = "184";
        $clientIds[] = "186";
        $clientIds[] = "196";
        $clientIds[] = "217";
        $clientIds[] = "236";
        $clientIds[] = "269";
        $clientIds[] = "282";
        $clientIds[] = "295";

        $error = CodeErreurEnum::ok;
        $message = "";
        $_SERVER['ERIC'] = true;
        $ordersCollection = new ArrayCollection();

        $saphelec =  $this->doctrine->getRepository(Enseigne::class)->find(15);
        /** @var Client $client */
        foreach ($clientIds as $clientId) {
            $client = $this->doctrine->getRepository(Client::class)->find($clientId);
            $client->setEnseigne($saphelec);
            /** @var Orders $order */
            foreach ($client->getOrders() as $order)
            {
                $order->setEnseigne($saphelec);
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
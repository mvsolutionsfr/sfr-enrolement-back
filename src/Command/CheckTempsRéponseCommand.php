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
use App\Service\AUIService;
use App\Service\EnrolementService;
use App\Service\LoggerESService;
use App\Service\SessionService;
use App\Toolbox\LogLevelEnum;
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
use Symfony\Contracts\HttpClient\HttpClientInterface;

// ATTENTION: POINTER SUR LA BBD CIBLE !!!!!!!!!!!!!!
// php bin/console app:CheckTempsRéponseCommand
// the name of the command is what users type after "php bin/console app:migration"
#[AsCommand(
    name: 'app:CheckTempsRéponseCommand',
    description: 'Cette command intègre les données des fichiers présent dans migV1 sans effacer la base existante',
    hidden: false
)]
class CheckTempsRéponseCommand extends Command
{

    private $client;

    public function __construct(LoggerESService $logger, HttpClientInterface $client)
    {
        $this->client = $client;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $timeDiffInSeconds = 0;
        $nbRepeat = 10;
        for ($i = 0; $i<$nbRepeat; $i++) {
            $serveur_dev = 'http://enrolement-back-develop.enrolement-sbd-dev.ctn1.pic.services.pack';
            $starttime = microtime(true);

            $response = $this->client->request(
                'GET',
                $serveur_dev . '/tools/alive', [
                'headers' => ['Content-Type' => 'application/json']
            ],
            );
            $statusCode = $response->getStatusCode();
            $content = $response->getContent(false);
            $endtime = microtime(true);
            $timeDiffInSeconds += ($endtime - $starttime);
        }
        $timeDiffInSeconds = $timeDiffInSeconds / $nbRepeat;
        $output->writeln($timeDiffInSeconds);

        return Command::SUCCESS;

        // or return this if some error happened during the execution
        // (it's equivalent to returning int(1))
        // return Command::FAILURE;

        // or return this to indicate incorrect command usage; e.g. invalid options
        // or missing arguments (it's equivalent to returning int(2))
        // return Command::INVALID
    }

    protected function configure(): void
    {
        $this
            // the command help shown when running the command with the "--help" option
            ->setHelp("Cette command intègre de nouveaux utilisateur à l'enseigne ");
    }

}
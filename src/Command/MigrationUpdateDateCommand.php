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
use App\Service\LoggerESService;
use App\Toolbox\LogLevelEnum;
use App\Toolbox\StatutImeiEnum;
use App\Toolbox\StatutRequeteEnum;
use App\Toolbox\TypeEnrolementEnum;
use DateInterval;
use DateTime;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\RequestStack;

// php bin/console app:migration-updatedate

#[AsCommand(
    name: 'app:migration-updatedate',
    description: 'Cette command intègre les données des fichiers présent dans mig/v1 sans effacer la base existante',
    hidden: false
)]
class MigrationUpdateDateCommand extends Command
{
    private $pgmApple;
    private $pgmKnox;
    private $pgmZT;
    private $admin;
    private $clientInconnu;
    private $dtnow;
    private $MaxFlush = 400;


    private Fabricant $fabricantApple;
    private Fabricant $fabricantSamsung;
    private Fabricant $fabricantCrosscall;


    protected LoggerESService $logger;
    private ObjectManager $manager;
    private ManagerRegistry $doctrine;
    private Enseigne $enseigneSFR;
    private OutputInterface $output;

    public function __construct(LoggerESService $logger, ManagerRegistry $doctrine)
    {
        $this->logger = $logger;
        $this->manager = $doctrine->getManager();
        $this->doctrine = $doctrine;
        ini_set("memory_limit", -1);
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->dtnow = date_create('now');
$this->output=$output;
        $this->chargeCommandesDep();;
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "[Migration] Fin integration");
        $output->writeln("Fin migration");

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
            ->setHelp('Cette command intègre les dates de depApple d\'enrolement des fichiers présent dans mig/v1');
    }


    private function chargeCommandesDep(): void
    {
        /////////////////////////////////////  Traitement du fichier orders DEP
        $this->logger->writeLog(LogLevelEnum::Info, __METHOD__ . "[" . __LINE__ . "]", "[Migration] Traitement des commandes DEP");
        $handle = fopen("mig/v1/orders_dep.csv", "r");
        $lineNumber = 1;
        $commandes = array();
        $compteur = 0;
        while (($rawStr = fgets($handle)) != false) {
            try {
                $ref = "";
                $commandesCsv = str_getcsv($rawStr, ";");
                $lineNumber++;
                $compteur++;
                if ($compteur >= $this->MaxFlush) {
                    $this->output->writeln(date_create('now')->format('Y-m-d H:i:s') . " " . $lineNumber);
                    $compteur = 0;
                    $this->clearAndResetDoctrine();
                }
                $ref = $commandesCsv[0] ?? "";
                $order = $this->doctrine->getRepository(Orders::class)->findOneBy(['ref' => $ref]);
                if ($order) {
                    $dateCreation = DateTime::createFromFormat('Y-m-d\TH:i:s+', $commandesCsv[6]);
                    if ($dateCreation) {
                        $order->setDateCreation($dateCreation);
                        $this->manager->persist($order);
                    } else {
                        $this->output->writeln(date_create('now')->format('Y-m-d H:i:s') . "[ERREUR] Commande " . $ref. " format date incorrect : " . $dateCreation);
                    }
                } else {
                    $this->output->writeln(date_create('now')->format('Y-m-d H:i:s') . "[ERREUR] " . "Commande " . $ref. " introuvable");
                }
            } catch
            (\Exception $exception) {
                $this->logger->writeLog(LogLevelEnum::Critique, __METHOD__ . "[" . __LINE__ . "]", "[Migration][Orders][" . $ref . "] Exception " . $exception->getMessage() . "[" . json_encode($commandesCsv));
            }
        }

        $this->output->writeln(date_create('now')->format('Y-m-d H:i:s') . " " . "Flush des commandes");
        $this->clearAndResetDoctrine();
    }

     private function clearAndResetDoctrine(): void
    {
        $this->manager->flush();
        $this->output->writeln(date_create('now')->format('Y-m-d H:i:s') . " Mémoire " . memory_get_usage());
    }

}
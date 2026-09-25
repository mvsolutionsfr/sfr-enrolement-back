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
use App\Service\KnoxRestService;
use App\Service\LoggerESService;
use App\Service\SessionService;
use App\Toolbox\CodeErreurEnum;
use App\Toolbox\LogLevelEnum;
use App\Toolbox\SourceEnum;
use App\Toolbox\StatutImeiEnum;
use App\Toolbox\StatutRequeteEnum;
use App\Toolbox\TypeEnrolementEnum;
use DateInterval;
use DatePeriod;
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
// php bin/console app:volumetriecommand dateDebut dateFin jour/mois/annee
// the name of the command is what users type after "php bin/console app:migration"
#[AsCommand(
    name: 'app:volumetriecommand',
    description: 'Cette command intègre les données des fichiers présent dans migV1 sans effacer la base existante',
    hidden: false
)]
class VolumetrieCommand extends Command
{

    private ObjectManager $manager;
    private ManagerRegistry $doctrine;
    private Enseigne $enseigne;



    public function __construct(LoggerESService $logger, ManagerRegistry $doctrine, KnoxRestService $knoxRestService)
    {
        $this->manager = $doctrine->getManager();
        $this->doctrine = $doctrine;

        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $error = CodeErreurEnum::ok;
        $message = "";
        $_SERVER['ERIC'] = true;
$topClientsAnnee="";


        $dateDebutParam=$input->getArgument("dateDebut");
        $dateFinParam=$input->getArgument("dateFin");
        $periodicite=$input->getArgument("periodicite");
        $output->writeln("Date Début [".$dateDebutParam."] Date Fin [".$dateFinParam."] Periodicite [".$periodicite."]");
        $file = fopen("mig/stats-".$periodicite.".csv", "a");

        $dateDebut = DateTime::createFromFormat('d/m/Y', $dateDebutParam);
        $dateDebut->setTime(0,0,0);
        $dateFin = DateTime::createFromFormat('d/m/Y', $dateFinParam);
        $dateFin->setTime(0,0,0);


        switch ($periodicite) {
            case "jour":
                $output->writeln("Statistique quotidienne");
                $interval = DateInterval::createFromDateString('1 day');
                break;
            case "mois":
                $output->writeln("Statitique mensuelle");
                $interval = DateInterval::createFromDateString('1 month');
                break;
            case "année":
                $output->writeln("Statistique annuelle");
                $interval = DateInterval::createFromDateString('1 year');
                break;
            default:
                $output->writeln("Périodicité incorrect (jour/mois/annee)");
                return Command::FAILURE;
        }
        $dateFin=$dateFin->add($interval);

        $period = new DatePeriod($dateDebut, $interval, $dateFin);

        $d=null;
        foreach ($period as $dt) {
            if ($d==null) { $d=$dt ; continue; }
            $orders = $this->doctrine->getRepository(Orders::class)->findByDates($d,$dt);
            $nbTerminauxEnroles = $this->doctrine->getRepository(Enrolement::class)->findEnrolementByDates($d,$dt);
            $nbTerminauxDesenroles = $this->doctrine->getRepository(Enrolement::class)->findDesenrolementByDates($d,$dt);
            /** @var Orders $order */
//           $terminaux=0;
//            foreach ($orders as $order)
//            {
//                $terminaux += $order->getTerminauxEnroles()->count();
//            }
            $str = $d->format("d-m-Y").";".count($orders).";".count($nbTerminauxEnroles).";".count($nbTerminauxDesenroles);
            $output->writeln($str);
            $d=$dt;
            fwrite($file,$str."\r\n");
        }
        fclose($file);
       //        $enrolementRepository = $this->doctrine->getRepository(Enrolement::class);
        return Command::SUCCESS;
    }

    protected function configure(): void
    {
        $this
            // the command help shown when running the command with the "--help" option
            ->setHelp("Cette command permet de lancer le les statistiquie d'inscripption et de désinscription sur une période");
        $this->addArgument('dateDebut', InputArgument::REQUIRED, "Date de début");
        $this->addArgument('dateFin', InputArgument::REQUIRED, "Date de fin");
        $this->addArgument('periodicite', InputArgument::REQUIRED, "Périodicité (jour/mois/annee");

        ;
    }

}
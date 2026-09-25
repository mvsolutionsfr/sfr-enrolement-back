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
// php bin/console app:bugverisure
#[AsCommand(
    name: 'app:bugverisure',
    description: 'Cette commande rejour une commande en erreur',
    hidden: false
)]
class BugVerisureIMEICommand extends Command
{

    private ManagerRegistry $doctrine;

    public function __construct(LoggerESService $logger, ManagerRegistry $doctrine, EnrolementService $enrolementService)
    {
        $this->doctrine = $doctrine;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $error = CodeErreurEnum::ok;
        $message = "";
        $_SERVER['ERIC'] = true;

        $commandeId = "35736";
        $imeis = ["352665180064168","352665180061982","352665180061974","352665180054318","352665180054284","352665180054243","352665180053237","352665180051876","352665180051868","352665180051819","352665180051702","352665180051686","352665180051595","352665180051587","352665180051546","352665180051397","352665180049656","352665180049623","352665180049557","352665180049508","354747959934694","352665180064010","352665180061990","352665180061958","352665180054391","352665180054219","352665180053260","352665180053120","352665180053047","352665180053021","352665180051777","352665180051579","352665180051553","352665180051405","352665180051355","352665180051330","352665180051173","352665180050522","352665180049615","352665180049524","352665180326500","352665180326484","352665180326476","352665180326450","352665180326435","352665180326344","352665180326336","352665180321014","352665180320966","352665180320925","352665180320909","352665180320792","352665180320768","352665180320727","352665180320719","352665180140067","352665180139101","352665180138954","352665180138913","352665180031530","354747959960608","354747959947035","354747959946748","354747959946706","354747959946680","354747959946557","352665180334785","352665180334728","352665180334710","352665180322764","352665180322707","352665180322608","352665180321535","352665180321303","352665180320420","352665180319786","352665180144341","352665180057576","352665180057568","352665180057519","352665180336749","352665180325957","352665180325882","352665180325841","352665180325676","352665180320693","352665180320685","352665180320578","352665180320537","352665180320479","352665180320321","352665180320230","352665180320222","352665180320107","352665180320081","352665180148813","352665180139580","352665180139176","352665180059945","352665180059788"];
//
//        $commandeId = "35735";
//        $imeis = ["354747959945997","352665180159182","352665180159174","352665180159166","352665180159133","352665180159083","352665180064028","352665180055067","352665180055059","352665180055042","352665180054730","352665180054698","352665180054680","352665180054664","352665180054656","352665180054649","352665180054631","352665180054516","352665180052148","352665180052072","352665180327383","352665180326492","352665180326443","352665180326419","352665180326385","352665180326377","352665180326252","352665180326229","352665180325908","352665180321154","352665180321048","352665180320982","352665180320784","352665180320750","352665180320636","352665180320628","352665180320461","352665180320305","352665180320255","352665180061115","354747959946763","354747959946672","354747959935204","354747959926187","354747959926138","352665180328936","352665180328118","352665180327938","352665180327680","352665180325809","352665180320248","352665180139473","352665180139309","352665180139283","352665180139051","352665180062055","352665180061842","352665180059051","352665180055869","352665180051835","354747959934579","354747959931559","352665180054482","352665180054409","352665180054334","352665180054326","352665180054300","352665180054292","352665180054227","352665180053203","352665180052064","352665180052031","352665180052007","352665180051959","352665180051942","352665180051934","352665180051371","352665180051223","352665180051215","352665180051199","354747959946144","354747959946045","354747959945948","354747959945906","354747959945518","354747959928662","354747959928613","354747959928597","354747959928548","354747959926260","354747959925874","352665180064226","352665180055083","352665180055000","352665180054979","352665180054938","352665180051520","352665180051504","352665180051389","352665180051314"];

//        $commandeId = "35734";
//        $imeis = ["354747959929561","354747959928233","352665180159653","352665180159620","352665180159539","352665180159521","352665180159505","352665180159398","352665180159372","352665180159265","352665180159240","352665180159216","352665180159125","352665180052999","352665180052957","352665180052882","352665180051157","352665180050787","352665180050118","352665180050043","354747959944891","354747959932243","354747959929355","354747959929280","352665180064200","352665180064002","352665180061735","352665180054854","352665180053211","352665180053187","352665180053161","352665180053088","352665180053062","352665180051785","352665180051421","352665180051363","352665180051348","352665180051322","352665180051298","352665180050969","352665180329082","352665180328126","352665180325999","352665180325874","352665180325858","352665180325825","352665180325783","352665180325759","352665180325742","352665180325643","352665180325635","352665180320313","352665180320289","352665180320263","352665180320198","352665180320172","352665180320156","352665180320115","352665180320065","352665180061834","354747959931351","352665180328019","352665180327953","352665180327722","352665180327185","352665180327177","352665180327169","352665180325577","352665180325452","352665180325304","352665180325254","352665180325213","352665180324778","352665180323887","352665180148672","352665180148656","352665180139432","352665180139408","352665180139317","352665180139192","352665180326393","352665180326328","352665180326294","352665180326211","352665180326187","352665180326120","352665180326096","352665180325940","352665180325932","352665180325528","352665180324935","352665180320990","352665180320842","352665180320818","352665180320701","352665180320602","352665180320453","352665180320388","352665180320347","352665180320271"];

        /** @var Orders $commande */
        $commande = $this->doctrine->getRepository(Orders::class)->find($commandeId);
        if ($commande != null) {

            $transaction = new OrderTransaction();
            $transaction->setOrders($commande);
            $transaction->setDateCreation($commande->getDateCreation());
            $transaction->setLastTransacOnType(true);
            $transaction->setEstCreePar($commande->getEstCreePar());
            $transaction->setStatut(StatutEnrolementEnum::Ok->value);
            $transaction->setTypeTransaction(TypeEnrolementEnum::Inscription->value);
            $transaction->setStatutTransacPgm("");
            $transaction->setStatutMsgPgm("");
            $transaction->setTransactionId("KP".$commandeId);
            $this->doctrine->getManager()->persist($transaction);

//            if ($order) $order->setLastTypeTransaction($typeTransac);

            $fabricantSamsung = $this->doctrine->getRepository(Fabricant::class)->findOneBy(['code' => "Samsung"]);

            foreach ($imeis as $imei)
            {
                $imeiBase = new Terminal();
                $imeiBase->setFabricant($fabricantSamsung);
                $imeiBase->setProgramme($commande->getPgmEnrolement());
                $imeiBase->setNumeroIMEI($imei);
                $imeiBase->setStatut(StatutImeiEnum::Enrole->value);

                $imeiBase->setOrders($commande);

                $terminalSuivi = new TerminalSuivi();
                $terminalSuivi->setTerminal($imeiBase);
                $terminalSuivi->setMessage("");
                $terminalSuivi->setTransaction($transaction);
                $terminalSuivi->setStatut(StatutRequeteEnum::Succes->value);

                $this->setEnrolement($imeiBase,$commande->getClient(),$commande->getDateCreation());

                $this->doctrine->getManager()->persist($terminalSuivi);
                $this->doctrine->getManager()->persist($imeiBase);
            }

            $this->doctrine->getManager()->flush();
            return Command::SUCCESS;
        }
        return Command::FAILURE;
    }

    protected function configure(): void
    {
        $this
            // the command help shown when running the command with the "--help" option
            ->setHelp("Cette command permet de supprimer une commande dans la base");
    }

    public function setEnrolement(Terminal $terminalEntity, Client $client, ?DateTime $dt): void
    {

        $enrolement = new Enrolement();
        $enrolement->setClient($client);
        //       $enrolement->setTerminal($terminalEntity);
        $enrolement->setDate($dt);
        $terminalEntity->addEnrolement($enrolement);
        $this->doctrine->getManager()->persist($enrolement);
    }

}
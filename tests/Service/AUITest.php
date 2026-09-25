<?php

namespace App\tests\Service;

use App\Service\AdminService;
use App\Service\AUIService;
use App\Service\ClientService;
use App\Toolbox\CodeErreurEnum;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use App\Service\EnrolementService;
use App\Toolbox\ProgrammeEnrolementEnum;

final class AUITest extends KernelTestCase
{
// php bin/phpunit tests/Service/AUITest.php
    public function testConnexionOk(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        // 3 services à déclarer
        $auiService = $container->get(AUIService::class);

        // On récupèe l'enseigne
        $utilisateur = $auiService->authentification("u163116", "password");

      //  dd($utilisateur);
        $this->assertNotEmpty($utilisateur,"Utilisateur introuvable");
        if ($utilisateur) {
            $this->assertSame($utilisateur->getNom(), "RAVE");
        }

    }

    public function testConnexionKO_3(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        // 3 services à déclarer
        $auiService = $container->get(AUIService::class);

        try {
            $utilisateur = $auiService->authentification("u144914", "password");
            $this->fail("utilisateur trouvé");
        } catch (\Exception $exception)
        {
            $this->assertSame($exception->getCode(), CodeErreurEnum::e3->value);
        }
    }

    public function InfosUtilisateur(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        // 3 services à déclarer
        $auiService = $container->get(AUIService::class);

        // On récupèe l'enseigne
        $utilisateur = $auiService->getUserData("1631166");

        $this->assertEmpty($utilisateur,"Utilisateur introuvable");
        if ($utilisateur) {
            $this->assertSame($utilisateur->getName(), "RAVE");
        }
        else {
        }
    }

}


<?php

namespace App\tests\Service;


// Après chargement des fixture Apple
// php bin/console --env=test --purge-with-truncate doctrine:fixtures:load --group=clients
// php bin/phpunit tests/Service/AppleTest.php

use App\Service\TerminalService;
use App\Toolbox\BaseTestCase;
use App\Toolbox\LogLevelEnum;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AppleTest extends WebTestCase
{

    protected KernelBrowser $client;
    protected string $sessionId;

    public function testEnrolement(): void
    {
        $this->client = static::createClient();
        $ts = $this->getContainer()->get(TerminalService::class);

        $this->connexion();

        $content = [
            'client' => '1',
            'reference' => 'reference client',
            'terminaux' => array('866228059717256', '868074057439230', '356557082621540'),
//            'fabricant' => '1',
            'type_id_terminal' => 'IMEI',    // 0 IMEI, 1 SN
            'session_id' => $this->sessionId
        ];

        $this->client->request('POST', '/enrolement', [], [], [], json_encode($content));
        $this->assertResponseIsSuccessful();
        $result = $this->client->getResponse()->getContent();
        $ts->writeLog(LogLevelEnum::Info, __METHOD__, "[RETOUR]" . $result);
        $this->assertNotNull($result);

        $json = json_decode($result, true);
        $this->assertArrayHasKey("error", $json);
        $this->assertEquals('0', $json['error']);

    }

    public function testDoEnrollement(): void
    {
        $this->client = static::createClient();
        $ts = $this->getContainer()->get(TerminalService::class);
        $this->connexion();
        $content = [
            'session_id' => $this->sessionId
        ];
        $this->client->request('POST', '/enrolement/dotasks', [], [], [], json_encode($content));
        $this->assertResponseIsSuccessful();
        $result = $this->client->getResponse()->getContent();
        $ts->writeLog(LogLevelEnum::Info, __METHOD__, "[RETOUR]" . $result);
        $this->assertNotNull($result);
        $json = json_decode($result, true);
        $this->assertArrayHasKey("error", $json);
        $this->assertEquals('0', $json['error']);
    }

//    public function testCheckStatusEnrollement(): void
//    {
//        $this->client = static::createClient();
//        $ts = $this->getContainer()->get(TerminalService::class);
//        $this->connexion();
//        $content = [
//            'session_id' => $this->sessionId
//        ];
//        $this->client->request('POST', '/enrolement/dotasks', [], [], [], json_encode($content));
//        $this->assertResponseIsSuccessful();
//        $result = $this->client->getResponse()->getContent();
//        $ts->writeLog(LogLevelEnum::Info, __METHOD__, "[RETOUR]" . $result);
//        $this->assertNotNull($result);
//        $json = json_decode($result, true);
//        $this->assertArrayHasKey("error", $json);
//        $this->assertEquals('0', $json['error']);
//    }
//
//    public function testDesenrolement(): void
//    {
//        $this->client = static::createClient();
//        $ts = $this->getContainer()->get(TerminalService::class);
//        $this->connexion();
//        $content = [
//            'client' => '1',
//            'terminaux' => array('868074057439230', '356557082621540'),
////            'terminaux' => array('866228059717256','868074057439230', '356557082621540'),
//            'session_id' => $this->sessionId
//        ];
//        $this->client->request('POST', '/desenrolement', [], [], [], json_encode($content));
//        $this->assertResponseIsSuccessful();
//        $result = $this->client->getResponse()->getContent();
//        $ts->writeLog(LogLevelEnum::Info, __METHOD__, "[RETOUR]" . $result);
//        $this->assertNotNull($result);
//        $json = json_decode($result, true);
//        $this->assertArrayHasKey("error", $json);
//        $this->assertEquals('0', $json['error']);
//    }
//
//    public function testDoDesenrollement(): void
//    {
//        $this->client = static::createClient();
//        $ts = $this->getContainer()->get(TerminalService::class);
//        $this->connexion();
//        $content = [
//            'session_id' => $this->sessionId
//        ];
//        $this->client->request('POST', '/enrolement/dotasks', [], [], [], json_encode($content));
//        $this->assertResponseIsSuccessful();
//        $result = $this->client->getResponse()->getContent();
//        $ts->writeLog(LogLevelEnum::Info, __METHOD__, "[RETOUR]" . $result);
//        $this->assertNotNull($result);
//        $json = json_decode($result, true);
//        $this->assertArrayHasKey("error", $json);
//        $this->assertEquals('0', $json['error']);
//    }
//
//    public function testCheckStatusDesenrollement(): void
//    {
//        $this->client = static::createClient();
//        $ts = $this->getContainer()->get(TerminalService::class);
//        $this->connexion();
//        $content = [
//            'session_id' => $this->sessionId
//        ];
//        $this->client->request('POST', '/enrolement/dotasks', [], [], [], json_encode($content));
//        $this->assertResponseIsSuccessful();
//        $result = $this->client->getResponse()->getContent();
//        $ts->writeLog(LogLevelEnum::Info, __METHOD__, "[RETOUR]" . $result);
//        $this->assertNotNull($result);
//        $json = json_decode($result, true);
//        $this->assertArrayHasKey("error", $json);
//        $this->assertEquals('0', $json['error']);
//    }
//
//    public function testAnnulation(): void
//    {
//        $this->client = static::createClient();
//        $ts = $this->getContainer()->get(TerminalService::class);
//        $this->connexion();
//        $content = [
//            'client' => '1',
//            'terminaux' => array('866228059717256'),
//            'session_id' => $this->sessionId
//        ];
//        $this->client->request('POST', '/desenrolement', [], [], [], json_encode($content));
//        $this->assertResponseIsSuccessful();
//        $result = $this->client->getResponse()->getContent();
//        $ts->writeLog(LogLevelEnum::Info, __METHOD__, "[ANNULATION]" . $result);
//        $this->assertNotNull($result);
//        $json = json_decode($result, true);
//        $this->assertArrayHasKey("error", $json);
//        $this->assertEquals('0', $json['error']);
//    }
//
//    public function testDoAnnulation(): void
//    {
//        $this->client = static::createClient();
//        $ts = $this->getContainer()->get(TerminalService::class);
//        $this->connexion();
//        $content = [
//            'session_id' => $this->sessionId
//        ];
//        $this->client->request('POST', '/enrolement/dotasks', [], [], [], json_encode($content));
//        $this->assertResponseIsSuccessful();
//        $result = $this->client->getResponse()->getContent();
//        $ts->writeLog(LogLevelEnum::Info, __METHOD__, "[ANNULATION]" . $result);
//        $this->assertNotNull($result);
//        $json = json_decode($result, true);
//        $this->assertArrayHasKey("error", $json);
//        $this->assertEquals('0', $json['error']);
//    }
//
//    public function testCheckStatusAnnulation(): void
//    {
//        $this->client = static::createClient();
//        $ts = $this->getContainer()->get(TerminalService::class);
//        $this->connexion();
//        $content = [
//            'session_id' => $this->sessionId
//        ];
//        $this->client->request('POST', '/enrolement/dotasks', [], [], [], json_encode($content));
//        $this->assertResponseIsSuccessful();
//        $result = $this->client->getResponse()->getContent();
//        $ts->writeLog(LogLevelEnum::Info, __METHOD__, "[ANNULATION]" . $result);
//        $this->assertNotNull($result);
//        $json = json_decode($result, true);
//        $this->assertArrayHasKey("error", $json);
//        $this->assertEquals('0', $json['error']);
//    }

    private function connexion(): void
    {
        $content = [
            'username' => 'u163116',
            'password' => 'password',
        ];

        // Request a specific page
        $crawler = $this->client->request('POST', '/login', [], [], [], json_encode($content));

        $this->assertResponseIsSuccessful();
        $result = $this->client->getResponse()->getContent();
        $this->assertNotNull($result);

        $json = json_decode($result, true);
        $this->assertArrayHasKey("user", $json);
        $this->assertArrayHasKey("session_id", $json["user"]);

        $this->sessionId = $json["user"]["session_id"];

        $this->assertNotNull($this->sessionId);

        // Connexion au programme PgmEnrol

        $content = [
            'session_id' => $this->sessionId,
            'pgm' => 'Apple',
        ];

        $crawler = $this->client->request('POST', '/pgm_enrol', [], [], [], json_encode($content));
        $this->assertResponseIsSuccessful();
        $result = $this->client->getResponse()->getContent();
        $this->assertNotNull($result);

        $json = json_decode($result, true);
        $this->assertArrayHasKey("resellerId", $json);

    }
}

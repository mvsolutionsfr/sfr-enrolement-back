<?php

namespace App\Controller;

use App\Service\LoggerESService;
use App\Service\SessionService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

abstract class AngularController extends AbstractController
{
    protected RequestStack $requestStack;
    protected LoggerESService $logger;
    public SessionService $sessionService;

    public function __construct(LoggerESService $logger, RequestStack $requestStack,SessionService $sessionService)
    {
        $this->requestStack = $requestStack;
        $this->logger = $logger;
        $this->sessionService = $sessionService;
    }

    public function setSession(Request $request )
    {
        $data = json_decode($request->getContent(), true);
        $sessionId = $data['session_id'] ?? null;
        if ($sessionId == null) throw new \Exception("Session_id absent de la requete");
        $this->sessionService->setSession($sessionId);

    }
}
<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class TestController extends AbstractController
{
    #[Route('/test-anon', name: 'test_anon')]
    public function testAnon()
    {
        return new Response('Anonymous access OK');
    }

    #[Route('/success', name: 'success')]
    public function success()
    {
        return new Response('SUCCESS');
    }

    #[Route('/failure', name: 'failure')]
    public function failure()
    {
        return new Response('FAILURE');
    }
} 
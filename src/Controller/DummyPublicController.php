<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DummyPublicController extends AbstractController
{
    #[Route('/test-anon', name: 'test_anon')]
    public function testAnon(): Response
    {
        return new Response('This is a public test endpoint.');
    }
} 
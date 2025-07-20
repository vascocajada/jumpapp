<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;

class BaseController extends AbstractController
{
    protected function renderWithConfig(string $view, array $parameters = [], Response $response = null): Response
    {
        $user = $this->getUser();
        $config = null;
        if ($user && $user instanceof \App\Entity\User) {
            $config = $user->getConfig();
        }
        $parameters['config'] = $config;
        return parent::render($view, $parameters, $response);
    }
} 
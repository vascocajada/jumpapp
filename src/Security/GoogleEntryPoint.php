<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Routing\RouterInterface;

class GoogleEntryPoint implements AuthenticationEntryPointInterface
{
    private RouterInterface $router;

    public function __construct(RouterInterface $router)
    {
        $this->router = $router;
    }

    public function start(Request $request, ?\Throwable $authException = null): RedirectResponse
    {
        // Redirect to the Google OAuth start route
        return new RedirectResponse($this->router->generate('connect_google_start'));
    }
} 
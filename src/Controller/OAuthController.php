<?php

namespace App\Controller;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;

class OAuthController extends AbstractController
{
    #[Route('/connect/google', name: 'connect_google_start')]
    public function connectGoogle(ClientRegistry $clientRegistry)
    {
        // Redirect to Google for main user authentication
        return $clientRegistry->getClient('google')->redirect(
            [
                'email',
                'profile',
                'https://www.googleapis.com/auth/gmail.readonly',
                'https://www.googleapis.com/auth/gmail.modify',
            ],
            [
                'access_type' => 'offline',
                'prompt' => 'consent',
            ]
        );
    }

    #[Route('/connect/google/add-account', name: 'connect_google_add_account')]
    public function connectGoogleAddAccount(ClientRegistry $clientRegistry, Request $request)
    {
        // Store in session that we're adding an account and the current user's email
        $request->getSession()->set('adding_gmail_account', true);
        $request->getSession()->set('current_user_email', $this->getUser()->getUserIdentifier());
        
        // Redirect to Google for adding additional Gmail accounts
        return $clientRegistry->getClient('google')->redirect(
            [
                'email',
                'profile',
                'https://www.googleapis.com/auth/gmail.readonly',
                'https://www.googleapis.com/auth/gmail.modify',
            ],
            [
                'access_type' => 'offline',
                'prompt' => 'consent',
            ]
        );
    }

    #[Route('/connect/google/check', name: 'connect_google_check')]
    public function connectGoogleCheck()
    {
        // TEMP: Return a plain response to debug if this controller is being called directly
        return new \Symfony\Component\HttpFoundation\Response('Should be handled by authenticator');
    }
} 
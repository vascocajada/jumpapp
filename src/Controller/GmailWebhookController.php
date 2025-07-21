<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Messenger\MessageBusInterface;
use App\Message\ImportEmailsMessage;
use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Firebase\JWT\JWT;
use Firebase\JWT\JWK;

class GmailWebhookController extends AbstractController
{
    #[Route('/webhook/gmail', name: 'gmail_webhook', methods: ['POST'])]
    public function gmailWebhook(Request $request, MessageBusInterface $bus, LoggerInterface $logger, EntityManagerInterface $em): Response
    {
        $content = $request->getContent();
        $logger->info('Received Gmail webhook', ['headers' => $request->headers->all(), 'body' => $content]);

        // JWT validation
        $authHeader = $request->headers->get('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            $logger->warning('Missing or invalid Authorization header');
            return new Response('Unauthorized', 401);
        }
        $jwt = substr($authHeader, 7);
        if (!$this->isValidGoogleJwt($jwt, $logger)) {
            $logger->warning('Invalid Google JWT');
            return new Response('Unauthorized', 401);
        }

        $data = json_decode($content, true);
        if (!isset($data['message']['data'])) {
            $logger->warning('No message data in webhook');
            return new Response('No data', 400);
        }

        // Decode the Pub/Sub message data (base64)
        $decoded = json_decode(base64_decode($data['message']['data']), true);

        $logger->info('Decoded Gmail webhook', ['decoded' => $decoded]);

        if (!isset($decoded['emailAddress'])) {
            $logger->warning('No emailAddress in webhook');
            return new Response('No emailAddress', 400);
        }

        // Find the GmailAccount entity for this email
        $gmailAccount = $em->getRepository(\App\Entity\GmailAccount::class)
            ->findOneBy(['email' => $decoded['emailAddress']]);
        if (!$gmailAccount) {
            $logger->warning('No GmailAccount found for webhook email', ['email' => $decoded['emailAddress']]);
            return new Response('No GmailAccount', 404);
        }

        // Dispatch import message for this account
        $bus->dispatch(new ImportEmailsMessage($gmailAccount->getId()));
        $logger->info('Dispatched ImportEmailsMessage from webhook', ['gmailAccountId' => $gmailAccount->getId()]);

        return new Response('OK', 200);
    }

    private function isValidGoogleJwt(string $jwt, LoggerInterface $logger): bool
    {
        try {
            $keys = json_decode(file_get_contents('https://www.googleapis.com/oauth2/v1/certs'), true); // decode as array
            JWT::decode($jwt, JWK::parseKeySet($keys));
            return true;
        } catch (\Throwable $e) {
            $logger->error('JWT validation error', ['error' => $e->getMessage()]);
            return false;
        }
    }
} 
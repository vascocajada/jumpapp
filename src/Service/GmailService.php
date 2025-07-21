<?php

namespace App\Service;

use Google\Client as GoogleClient;
use Google\Service\Gmail;

class GmailService
{
    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;

    public function __construct(string $clientId, string $clientSecret, string $redirectUri)
    {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->redirectUri = $redirectUri;
    }

    public function getClient(string $accessToken, ?string $refreshToken = null, &$newToken = null): GoogleClient
    {
        $client = new GoogleClient();
        $client->setClientId($this->clientId);
        $client->setClientSecret($this->clientSecret);
        $client->setRedirectUri($this->redirectUri);
        $client->setAccessToken($accessToken);
        $client->addScope([
            Gmail::GMAIL_READONLY,
            Gmail::GMAIL_MODIFY,
        ]);

        // If token is expired and we have a refresh token, refresh it
        if ($client->isAccessTokenExpired() && $refreshToken) {
            $newToken = $client->fetchAccessTokenWithRefreshToken($refreshToken);
            $client->setAccessToken($newToken);
        }

        return $client;
    }

    public function fetchMessages(GoogleClient $client, int $maxResults = 10): array
    {
        $service = new Gmail($client);
        $messages = $service->users_messages->listUsersMessages('me', [
            'maxResults' => $maxResults,
            'q' => 'in:inbox'
        ]);
        $result = [];
        foreach ($messages->getMessages() as $message) {
            $msg = $service->users_messages->get('me', $message->getId(), ['format' => 'full']);
            $result[] = $msg;
        }
        return $result;
    }

    public function archiveMessage(GoogleClient $client, string $messageId): bool
    {
        try {
            $service = new Gmail($client);
            // Remove the INBOX label to archive
            $modifyRequest = new \Google\Service\Gmail\ModifyMessageRequest();
            $modifyRequest->setRemoveLabelIds(['INBOX']);
            
            $result = $service->users_messages->modify('me', $messageId, $modifyRequest);
            
            // Log success for debugging
            return true;
            
        } catch (\Google\Service\Exception $e) {
            throw new \Exception('Failed to archive email in Gmail: ' . $e->getMessage() . ' (Code: ' . $e->getCode() . ')');
        } catch (\Exception $e) {
            throw new \Exception('Failed to archive email in Gmail: ' . $e->getMessage());
        }
    }

    public function watchInbox(\App\Entity\GmailAccount $gmailAccount, string $topicName): \Google\Service\Gmail\WatchResponse
    {
        $tokenData = json_decode($gmailAccount->getAccessToken(), true);
        $client = $this->getClient($tokenData['access_token'], $tokenData['refresh_token'], $newToken);
        $service = new \Google\Service\Gmail($client);

        $watchRequest = new \Google\Service\Gmail\WatchRequest([
            'topicName' => $topicName,
            'labelIds' => ['INBOX'],
        ]);
        return $service->users->watch('me', $watchRequest);
    }
} 
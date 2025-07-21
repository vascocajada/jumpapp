<?php

namespace App\Service;

use App\Entity\Email;
use App\Entity\GmailAccount;
use App\Exception\CategoryNotFoundException;
use Doctrine\ORM\EntityManagerInterface;


class EmailImportService
{
    private GmailService $gmailService;
    private OpenAIService $openAIService;

    private EntityManagerInterface $em;

    public function __construct(GmailService $gmailService, OpenAIService $openAIService, EntityManagerInterface $em)
    {
        $this->gmailService = $gmailService;
        $this->openAIService = $openAIService;
        $this->em = $em;
    }

    /**
     * Fetch, categorize, summarize, and archive emails for a Gmail account.
     * Returns [processedCount, archivedCount, errors[]]
     */
    public function importForAccount(GmailAccount $gmailAccount, int $maxResults = 10): array
    {
        $user = $gmailAccount->getOwner();
        $tokenData = json_decode($gmailAccount->getAccessToken(), true);
        $accessToken = $tokenData['access_token'] ?? null;
        $refreshToken = $tokenData['refresh_token'] ?? null;
        $newToken = null;
        $errors = [];

        if (!$accessToken || !$refreshToken) {
            $errors[] = 'Missing Gmail access or refresh token.';
            return [0, 0, $errors];
        }

        $client = $this->gmailService->getClient($accessToken, $refreshToken, $newToken);
        if ($newToken) {
            $gmailAccount->setAccessToken(json_encode($newToken));
            $this->em->flush();
        }

        $messages = $this->gmailService->fetchMessages($client, $maxResults);
        $categoryEntities = method_exists($user, 'getCategories') ? $user->getCategories()->toArray() : [];
        $categoryPairs = array_map(fn($cat) => $cat->getName() . ': ' . $cat->getDescription(), $categoryEntities);
        $categoryNames = array_map(fn($cat) => $cat->getName(), $categoryEntities);
        $processed = 0;
        $archived = 0;

        foreach ($messages as $msg) {
            $gmailId = $msg->getId();
            $existing = $this->em->getRepository(Email::class)->findOneBy([
                'gmailId' => $gmailId,
                'gmailAccount' => $gmailAccount
            ]);
            if ($existing) {
                // Archive existing emails that are still in inbox
                try {
                    if ($this->gmailService->archiveMessage($client, $gmailId)) {
                        $archived++;
                    }
                } catch (\Exception $e) {
                    $errors[] = $e->getMessage();
                }
                continue;
            }
            $payload = $msg->getPayload();
            $headers = $payload->getHeaders();
            $subject = '';
            $from = '';
            $body = '';
            $htmlBody = '';
            $plainBody = '';
            $listUnsubscribe = null;
            foreach ($headers as $header) {
                if ($header->getName() === 'Subject') {
                    $subject = $header->getValue();
                }
                if ($header->getName() === 'From') {
                    $from = $header->getValue();
                }
                if (strtolower($header->getName()) === 'list-unsubscribe') {
                    $listUnsubscribe = $header->getValue();
                }
            }
            $parts = $payload->getParts();
            if ($parts) {
                foreach ($parts as $part) {
                    if ($part->getMimeType() === 'text/html') {
                        $htmlBody = base64_decode(strtr($part->getBody()->getData(), '-_', '+/'));
                    }
                    if ($part->getMimeType() === 'text/plain') {
                        $plainBody = base64_decode(strtr($part->getBody()->getData(), '-_', '+/'));
                    }
                }
            }
            if (!$htmlBody && $payload->getBody()) {
                $plainBody = base64_decode(strtr($payload->getBody()->getData(), '-_', '+/'));
            }
            $body = $htmlBody ?: $plainBody;
            try {
                $chosenName = $categoryPairs ? $this->openAIService->categorizeEmail($body ?: $subject, $categoryPairs, $categoryNames) : null;
            } catch (CategoryNotFoundException $e) {
                $errors[] = $e->getMessage();
                continue;
            }
            $summary = $this->openAIService->summarizeEmail($body ?: $subject);
            $categoryEntity = $chosenName ? $this->em->getRepository(\App\Entity\Category::class)->findOneBy(['name' => $chosenName]) : null;
            if (!$categoryEntity) {
                $errors[] = 'Category not found for name: ' . $chosenName;
                continue;
            }
            $email = new Email();
            $email->setGmailId($gmailId);
            $email->setSubject($subject);
            $email->setSender($from);
            $email->setSummary($summary);
            $email->setOwner($user);
            $email->setGmailAccount($gmailAccount);
            $email->setReceivedAt(new \DateTimeImmutable());
            $email->setBody($body);
            $email->setCategory($categoryEntity);
            $email->setListUnsubscribe($listUnsubscribe);
            $this->em->persist($email);
            $processed++;
            try {
                if ($this->gmailService->archiveMessage($client, $gmailId)) {
                    $archived++;
                }
            } catch (\Exception $e) {
                $errors[] = $e->getMessage();
            }
        }
        $this->em->flush();
        return [$processed, $archived, $errors];
    }
}

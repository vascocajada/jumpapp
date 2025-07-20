<?php
namespace App\MessageHandler;

use App\Message\UnsubscribeEmailMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\OpenAIService;

use App\Service\UnsubscribeAutomationService;
use App\Service\EmailService;
use App\Entity\Email;
use App\Entity\Notification;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

#[AsMessageHandler]
class UnsubscribeEmailMessageHandler
{
    private EntityManagerInterface $em;
    private OpenAIService $openAIService;

    private UnsubscribeAutomationService $unsubscribeAutomationService;
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;
    private EmailService $emailService;

    public function __construct(
        EntityManagerInterface $em,
        OpenAIService $openAIService,
        UnsubscribeAutomationService $unsubscribeAutomationService,
        HttpClientInterface $httpClient,
        LoggerInterface $logger,
        EmailService $emailService
    ) {
        $this->em = $em;
        $this->openAIService = $openAIService;
        $this->unsubscribeAutomationService = $unsubscribeAutomationService;
        $this->httpClient = $httpClient;
        $this->logger = $logger;
        $this->emailService = $emailService;
    }

    public function __invoke(UnsubscribeEmailMessage $message)
    {
        $this->logger->info('UnsubscribeEmailMessageHandler invoked', ['emailId' => $message->getEmailId()]);
        // Start transaction to prevent EntityManager corruption
        $this->em->getConnection()->beginTransaction();
        
        try {
            $this->logger->info('Fetching email entity', ['emailId' => $message->getEmailId()]);
            $email = $this->em->getRepository(Email::class)->find($message->getEmailId());
            if (!$email) {
                $this->logger->warning('Email not found for unsubscribe', ['emailId' => $message->getEmailId()]);
                $this->em->getConnection()->rollBack();
                return;
            }

            $this->logger->info('Setting unsubscribe status to in_progress', ['emailId' => $email->getId()]);
            $email->setUnsubscribeStatus('in_progress');
            $this->em->flush();

            $body = $email->getBody() ?? '';
            $subject = $email->getSubject() ?? '';
            $listUnsubscribe = $email->getListUnsubscribe();
            $this->logger->info('Email loaded', [
                'emailId' => $email->getId(),
                'subject' => $subject,
                'hasBody' => !empty($body),
                'listUnsubscribe' => $listUnsubscribe
            ]);
            // Before List-Unsubscribe logic in __invoke:
            $config = $email->getOwner() ? $email->getOwner()->getConfig() : null;
            $useListUnsubscribe = $config ? $config->getUseGmailListUnsubscribe() : false;
            // Try List-Unsubscribe header first only if enabled in config
            if ($listUnsubscribe && $useListUnsubscribe) {
                $this->logger->info('Attempting List-Unsubscribe header processing', ['emailId' => $email->getId()]);
                $result = $this->handleListUnsubscribe($email, $listUnsubscribe);
                $this->logger->info('List-Unsubscribe result', ['emailId' => $email->getId(), 'result' => $result]);
                if ($result !== null) {
                    $this->em->flush();
                    $this->em->getConnection()->commit();
                    $this->logger->info('UnsubscribeEmailMessageHandler completed via List-Unsubscribe', ['emailId' => $email->getId()]);
                    return;
                }
            }
            // Fallback to AI-based unsubscribe link detection
            $this->logger->info('Attempting AI-based unsubscribe link detection', ['emailId' => $email->getId()]);
            $unsubscribeUrl = $this->openAIService->findUnsubscribeLink($body);
            $this->logger->info('AI-detected unsubscribe URL', ['emailId' => $email->getId(), 'unsubscribeUrl' => $unsubscribeUrl]);
            if (!$unsubscribeUrl || !filter_var($unsubscribeUrl, FILTER_VALIDATE_URL) || !preg_match('/^https?:\/\//i', $unsubscribeUrl)) {
                $this->logger->info('Invalid or missing unsubscribe URL', ['emailId' => $email->getId(), 'unsubscribeUrl' => $unsubscribeUrl]);
                $this->handleUnsubscribeResult($email, 'Unsubscribe failed: invalid or missing unsubscribe link.', 'error');
                $this->em->flush();
                $this->em->getConnection()->commit();
                $this->logger->info('UnsubscribeEmailMessageHandler completed with error (invalid/missing link)', ['emailId' => $email->getId()]);
                return;
            }
            $this->logger->info('Automating unsubscribe process', ['emailId' => $email->getId(), 'unsubscribeUrl' => $unsubscribeUrl]);
            $result = $this->unsubscribeAutomationService->automateUnsubscribe($unsubscribeUrl);
            $this->logger->info('Unsubscribe automation result', ['emailId' => $email->getId(), 'result' => $result]);
            if ($result['status'] === 'success') {
                $this->handleUnsubscribeResult($email, $result['message'], 'success');
                $this->logger->info('Unsubscribe marked as success', ['emailId' => $email->getId(), 'message' => $result['message']]);
            } elseif ($result['status'] === 'failure' || $result['status'] === 'error') {
                $this->handleUnsubscribeResult($email, $result['message'], 'error');
                $this->logger->info('Unsubscribe marked as error', ['emailId' => $email->getId(), 'message' => $result['message']]);
            } elseif ($result['status'] === 'inconclusive') {
                $this->handleUnsubscribeResult($email, $result['message'], 'inconclusive');
                $this->logger->info('Unsubscribe marked as inconclusive', ['emailId' => $email->getId(), 'message' => $result['message']]);
            } else {
                $this->handleUnsubscribeResult($email, 'Unsubscribe failed: unknown status.', 'error');
                $this->logger->info('Unsubscribe marked as error (unknown status)', ['emailId' => $email->getId(), 'message' => $result['message'] ?? 'Unknown']);
            }
            $this->em->flush();
            $this->em->getConnection()->commit();
            $this->logger->info('UnsubscribeEmailMessageHandler completed', ['emailId' => $email->getId()]);
        } catch (\Doctrine\DBAL\Exception\DriverException $e) {
            $this->logger->error('Database error during unsubscribe processing', [
                'emailId' => $message->getEmailId(),
                'error' => $e->getMessage(),
                'sqlState' => $e->getSQLState()
            ]);
            $this->em->getConnection()->rollBack();
            $this->em->clear(); // Detach all entities
            throw $e; // Let Messenger retry
        } catch (\Doctrine\ORM\Exception\ORMException $e) {
            $this->logger->error('ORM error during unsubscribe processing', [
                'emailId' => $message->getEmailId(),
                'error' => $e->getMessage()
            ]);
            $this->em->getConnection()->rollBack();
            $this->em->clear(); // Detach all entities
            throw $e; // Let Messenger retry
        } catch (\Throwable $e) {
            $this->logger->error('Unexpected error during unsubscribe processing', [
                'emailId' => $message->getEmailId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->em->getConnection()->rollBack();
            $this->em->clear(); // Detach all entities
            throw $e; // Let Messenger retry
        }
    }



    /**
     * Handle List-Unsubscribe header processing
     * Supports both https:// and mailto: formats
     */
    private function handleListUnsubscribe(Email $email, string $listUnsubscribe): ?array
    {
        $this->logger->info('handleListUnsubscribe called', [
            'emailId' => $email->getId(),
            'listUnsubscribe' => $listUnsubscribe
        ]);
        $this->logger->info('Processing List-Unsubscribe header', [
            'emailId' => $email->getId(),
            'listUnsubscribe' => $listUnsubscribe
        ]);

        // Extract URLs from <url> format
        if (!preg_match_all('/<([^>]+)>/', $listUnsubscribe, $matches)) {
            $this->logger->warning('Invalid List-Unsubscribe format', [
                'emailId' => $email->getId(),
                'listUnsubscribe' => $listUnsubscribe
            ]);
            $this->handleUnsubscribeResult($email, 'Invalid List-Unsubscribe header format.', 'error');
            return ['status' => 'error'];
        }
        $this->logger->info('List-Unsubscribe candidates extracted', [
            'emailId' => $email->getId(),
            'candidates' => $matches[1]
        ]);
        foreach ($matches[1] as $candidate) {
            $this->logger->info('Processing List-Unsubscribe candidate', [
                'emailId' => $email->getId(),
                'candidate' => $candidate
            ]);

            if (preg_match('/^https?:\/\//i', $candidate)) {
                $this->logger->info('Candidate is HTTP/HTTPS URL', [
                    'emailId' => $email->getId(),
                    'candidate' => $candidate
                ]);
                // HTTP/HTTPS URL - make direct request
                return $this->handleHttpUnsubscribe($email, $candidate);
            } elseif (preg_match('/^mailto:/i', $candidate)) {
                $this->logger->info('Candidate is mailto URL', [
                    'emailId' => $email->getId(),
                    'candidate' => $candidate
                ]);
                // Mailto URL - send unsubscribe email
                return $this->handleMailtoUnsubscribe($email, $candidate);
            }
        }

        // If we get here, no valid https:// or mailto: found
        $this->logger->error('No valid unsubscribe method found in List-Unsubscribe', [
            'emailId' => $email->getId(),
            'listUnsubscribe' => $listUnsubscribe
        ]);
        $this->handleUnsubscribeResult($email, 'List-Unsubscribe header contains unsupported format. Only https:// and mailto: are supported.', 'error');
        return ['status' => 'error'];
    }

    /**
     * Handle HTTP/HTTPS unsubscribe requests
     */
    private function handleHttpUnsubscribe(Email $email, string $url): array
    {
        $this->logger->info('Processing HTTP unsubscribe request', [
            'emailId' => $email->getId(),
            'url' => $url
        ]);

        try {
            $response = $this->httpClient->request('GET', $url, [
                'timeout' => 30,
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (compatible; EmailSorter/1.0)',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                ]
            ]);

            $statusCode = $response->getStatusCode();
            $content = $response->getContent();

            $this->logger->info('HTTP unsubscribe response received', [
                'emailId' => $email->getId(),
                'url' => $url,
                'statusCode' => $statusCode,
                'contentLength' => strlen($content)
            ]);

            // For one-click unsubscribe, only 2xx status codes definitively indicate success
            // RFC 8058 specifies that 2xx status codes indicate successful unsubscription
            if ($statusCode >= 200 && $statusCode < 300) {
                $message = "Successfully unsubscribed via HTTP request (Status: {$statusCode})";
                $this->handleUnsubscribeResult($email, $message, 'success');
                return ['status' => 'success'];
            }

            // 3xx redirects are ambiguous - could be success redirect or error redirect
            // 4xx and 5xx status codes indicate failure
            $this->handleUnsubscribeResult($email, "HTTP unsubscribe request failed (Status: {$statusCode})", 'error');
            return ['status' => 'error'];

        } catch (\Exception $e) {
            $this->logger->error('HTTP unsubscribe request failed', [
                'emailId' => $email->getId(),
                'url' => $url,
                'error' => $e->getMessage()
            ]);
            $errorMessage = "HTTP unsubscribe request failed: " . substr($e->getMessage(), 0, 200);
            $this->handleUnsubscribeResult($email, $errorMessage, 'error');
            return ['status' => 'error'];
        }
    }

    /**
     * Handle mailto: unsubscribe requests
     */
    private function handleMailtoUnsubscribe(Email $email, string $mailtoUrl): array
    {
        $this->logger->info('Processing mailto unsubscribe request', [
            'emailId' => $email->getId(),
            'mailtoUrl' => $mailtoUrl
        ]);

        // Parse mailto URL
        $mailtoData = parse_url($mailtoUrl);
        if (!$mailtoData || !isset($mailtoData['scheme']) || $mailtoData['scheme'] !== 'mailto') {
            $this->handleUnsubscribeResult($email, 'Invalid mailto URL format.', 'error');
            return ['status' => 'error'];
        }

        $to = $mailtoData['path'] ?? '';
        if (empty($to)) {
            $this->handleUnsubscribeResult($email, 'No recipient email address found in mailto URL.', 'error');
            return ['status' => 'error'];
        }

        $subject = '';
        $body = '';

        // Parse query parameters
        if (isset($mailtoData['query'])) {
            parse_str($mailtoData['query'], $params);
            $subject = $params['subject'] ?? '';
            $body = $params['body'] ?? '';
        }

        // Send the unsubscribe email
        $success = $this->emailService->sendUnsubscribeEmail($email, $to, $subject, $body);
        
        if ($success) {
            $this->handleUnsubscribeResult($email, "Unsubscribe email sent successfully to {$to}", 'success');
            return ['status' => 'success'];
        } else {
            $this->handleUnsubscribeResult($email, "Failed to send unsubscribe email to {$to}", 'error');
            return ['status' => 'error'];
        }
    }

    /**
     * Handle unsubscribe result (success, error, inconclusive)
     */
    private function handleUnsubscribeResult(Email $email, string $message, string $status): void
    {
        $email->setUnsubscribeStatus($status);
        $notification = new Notification();
        $notification->setUser($email->getOwner());
        $notification->setMessage($this->validateNotificationMessage($message));
        $notification->setType($status);
        $notification->setRelatedEmail($email);
        $notification->setIsRead(false);
        $this->em->persist($notification);
    }

    /**
     * Validate and truncate notification message to fit database constraints
     */
    private function validateNotificationMessage(string $message): string
    {
        // Ensure message fits in VARCHAR(255)
        $validatedMessage = substr(trim($message), 0, 255);
        
        // Log if message was truncated
        if (strlen($message) > 255) {
            $this->logger->warning('Notification message was truncated', [
                'originalLength' => strlen($message),
                'truncatedLength' => strlen($validatedMessage),
                'originalMessage' => $message
            ]);
        }
        
        return $validatedMessage;
    }


} 
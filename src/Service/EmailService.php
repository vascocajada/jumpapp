<?php

namespace App\Service;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email as MimeEmail;
use Psr\Log\LoggerInterface;
use App\Entity\Email;

class EmailService
{
    private MailerInterface $mailer;
    private LoggerInterface $logger;

    public function __construct(
        MailerInterface $mailer,
        LoggerInterface $logger
    ) {
        $this->mailer = $mailer;
        $this->logger = $logger;
    }

    /**
     * Send a generic email
     */
    public function sendEmail(string $from, string $to, string $subject, string $body, array $options = []): bool
    {
        try {
            $htmlBody = $options['htmlBody'] ?? $this->createHtmlBody($body);
            
            $mimeEmail = (new MimeEmail())
                ->from($from)
                ->to($to)
                ->subject($subject)
                ->text($body)
                ->html($htmlBody);

            $this->mailer->send($mimeEmail);

            $this->logger->info('Email sent successfully', [
                'from' => $from,
                'to' => $to,
                'subject' => $subject
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('Failed to send email', [
                'from' => $from,
                'to' => $to,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Send an unsubscribe email for mailto: URLs
     */
    public function sendUnsubscribeEmail(Email $email, string $to, string $subject = '', string $body = ''): bool
    {
        // Use the user's email address (the person who wants to unsubscribe)
        $fromEmail = $email->getOwner()->getEmail();
        
        // If no subject provided, use RFC 8058 default
        if (empty($subject)) {
            $subject = 'unsubscribe';
        }
        
        // If no body provided, use RFC 8058 default
        if (empty($body)) {
            $body = 'unsubscribe';
        }

        return $this->sendEmail($fromEmail, $to, $subject, $body);
    }

    /**
     * Create HTML version of the email body
     */
    private function createHtmlBody(string $textBody): string
    {
        $htmlBody = nl2br(htmlspecialchars($textBody));
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <title>Unsubscribe Request</title>
        </head>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                {$htmlBody}
            </div>
        </body>
        </html>";
    }
} 
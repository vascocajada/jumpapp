<?php

namespace App\MessageHandler;

use App\Message\ImportEmailsMessage;
use App\Service\EmailImportService;
use App\Repository\GmailAccountRepository;
use App\Entity\Notification;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Psr\Log\LoggerInterface;

#[AsMessageHandler]
class ImportEmailsMessageHandler
{
    private EmailImportService $emailImportService;
    private GmailAccountRepository $gmailAccountRepository;
    private EntityManagerInterface $em;
    private LoggerInterface $logger;

    public function __construct(EmailImportService $emailImportService, GmailAccountRepository $gmailAccountRepository, EntityManagerInterface $em, LoggerInterface $logger)
    {
        $this->emailImportService = $emailImportService;
        $this->gmailAccountRepository = $gmailAccountRepository;
        $this->em = $em;
        $this->logger = $logger;
    }

    public function __invoke(ImportEmailsMessage $message)
    {
        $this->logger->info('Importing emails for user: ' . $message->getUserIdentifier());
        try {
            $user = $this->em->getRepository(User::class)->find($message->getUserIdentifier());
            if (!$user) {
                $this->logger->error('User not found for identifier: ' . $message->getUserIdentifier());
                $notification = new Notification();
                $notification->setType('error');
                $notification->setMessage('Email import failed: user not found.');
                // No user to associate, so skip setUser
                if ($this->em->isOpen()) {
                    $this->em->persist($notification);
                    $this->em->flush();
                }
                return;
            }
            $this->logger->info('Found user: ' . $user->getId());
            $accounts = $this->gmailAccountRepository->findBy(['owner' => $user]);
            $totalProcessed = 0;
            foreach ($accounts as $account) {
                $this->logger->info('Importing emails for account: ' . $account->getId());
                [$processed] = $this->emailImportService->importForAccount($account, 10);
                $this->logger->info('Imported ' . $processed . ' emails for account: ' . $account->getId());
                $totalProcessed += $processed;
            }
            $notification = new Notification();
            $notification->setUser($user);
            $notification->setType('success');
            $notification->setMessage("Imported $totalProcessed emails from Gmail.");
            if ($this->em->isOpen()) {
                $this->em->persist($notification);
                $this->em->flush();
            }
        } catch (\Throwable $e) {
            $this->logger->error('Exception during email import: ' . $e->getMessage());
            // Try to notify the user if possible
            $user = $user ?? null;
            $notification = new Notification();
            if ($user) {
                $notification->setUser($user);
            }
            $notification->setType('error');
            $notification->setMessage('Email import failed: ' . $e->getMessage());
            if ($this->em->isOpen()) {
                $this->em->persist($notification);
                $this->em->flush();
            }
        }
    }
} 
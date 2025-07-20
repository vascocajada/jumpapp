<?php

namespace App\Command;

use App\Entity\GmailAccount;
use App\Service\EmailImportService;
use App\Repository\GmailAccountRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:fetch-gmail-emails',
    description: 'Fetch, categorize, summarize, and archive emails for all Gmail accounts or a specific account.'
)]
class FetchGmailEmailsCommand extends Command
{
    private EmailImportService $emailImportService;
    private GmailAccountRepository $gmailAccountRepository;
    private EntityManagerInterface $em;
    private LoggerInterface $logger;

    public function __construct(EmailImportService $emailImportService, GmailAccountRepository $gmailAccountRepository, EntityManagerInterface $em, LoggerInterface $logger)
    {
        parent::__construct();
        $this->emailImportService = $emailImportService;
        $this->gmailAccountRepository = $gmailAccountRepository;
        $this->em = $em;
        $this->logger = $logger;
    }

    protected function configure(): void
    {
        $this
            ->addArgument('accountId', InputArgument::OPTIONAL, 'GmailAccount ID to process (if omitted, processes all accounts)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $accountId = $input->getArgument('accountId');
        $accounts = [];
        
        $this->logger->info('Starting Gmail email fetch command', ['accountId' => $accountId]);
        
        if ($accountId) {
            $account = $this->gmailAccountRepository->find($accountId);
            if (!$account) {
                $errorMsg = "No GmailAccount found with ID $accountId";
                $this->logger->error($errorMsg);
                $output->writeln("<error>$errorMsg</error>");
                return Command::FAILURE;
            }
            $accounts = [$account];
            $this->logger->info('Processing specific Gmail account', ['accountId' => $accountId, 'email' => $account->getEmail()]);
        } else {
            $accounts = $this->gmailAccountRepository->findAll();
            $this->logger->info('Processing all Gmail accounts', ['count' => count($accounts)]);
        }

        if (empty($accounts)) {
            $this->logger->info('No Gmail accounts to process');
            $output->writeln('<info>No Gmail accounts to process.</info>');
            return Command::SUCCESS;
        }

        $totalProcessed = 0;
        $totalArchived = 0;
        $totalErrors = 0;

        foreach ($accounts as $account) {
            $output->writeln("Processing Gmail account: <info>{$account->getEmail()}</info> (ID: {$account->getId()})");
            $this->logger->info('Processing Gmail account', ['accountId' => $account->getId(), 'email' => $account->getEmail()]);
            
            try {
                [$processed, $archived, $errors] = $this->emailImportService->importForAccount($account, 10);
                $totalProcessed += $processed;
                $totalArchived += $archived;
                $totalErrors += count($errors);
                
                $output->writeln("  Processed: <comment>$processed</comment> | Archived: <comment>$archived</comment>");
                $this->logger->info('Account processing completed', [
                    'accountId' => $account->getId(),
                    'email' => $account->getEmail(),
                    'processed' => $processed,
                    'archived' => $archived,
                    'errors' => count($errors)
                ]);
                
                if (!empty($errors)) {
                    foreach ($errors as $err) {
                        $output->writeln("  <error>Error:</error> $err");
                        $this->logger->error('Email processing error', [
                            'accountId' => $account->getId(),
                            'email' => $account->getEmail(),
                            'error' => $err
                        ]);
                    }
                }
            } catch (\Exception $e) {
                $errorMsg = "Failed to process account {$account->getEmail()}: " . $e->getMessage();
                $this->logger->error($errorMsg, [
                    'accountId' => $account->getId(),
                    'email' => $account->getEmail(),
                    'exception' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                $output->writeln("<error>$errorMsg</error>");
                $totalErrors++;
            }
        }
        
        $this->logger->info('Gmail email fetch command completed', [
            'totalProcessed' => $totalProcessed,
            'totalArchived' => $totalArchived,
            'totalErrors' => $totalErrors
        ]);
        
        $output->writeln("<info>Done. Total processed: $totalProcessed, Total archived: $totalArchived, Total errors: $totalErrors</info>");
        return Command::SUCCESS;
    }
} 
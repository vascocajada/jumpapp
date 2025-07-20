<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

#[AsCommand(
    name: 'messenger:health-check',
    description: 'Health check for messenger workers',
)]
class MessengerHealthCheckCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private LoggerInterface $logger
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            // Check database connection
            $this->em->getConnection()->executeQuery('SELECT 1');
            
            // Check for failed messages
            $failedCount = $this->getFailedMessagesCount();
            
            if ($failedCount > 0) {
                $this->logger->warning('Health check: Found failed messages', ['count' => $failedCount]);
                $io->warning("Found {$failedCount} failed messages");
            } else {
                $io->success('Messenger health check passed');
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->logger->error('Health check failed', ['error' => $e->getMessage()]);
            $io->error('Health check failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function getFailedMessagesCount(): int
    {
        try {
            $result = $this->em->getConnection()->executeQuery(
                "SELECT COUNT(*) FROM messenger_failed_messages"
            );
            return (int) $result->fetchOne();
        } catch (\Exception $e) {
            // Table might not exist yet
            return 0;
        }
    }
} 
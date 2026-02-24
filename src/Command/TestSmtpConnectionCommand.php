<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;

#[AsCommand(
    name: 'app:test-smtp-connection',
    description: 'Test direct SMTP connection'
)]
class TestSmtpConnectionCommand extends Command
{
    public function __construct(
        private string $mailerDsn
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp('This command tests direct SMTP connection');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Test Direct SMTP Connection');

        try {
            // Parse DSN
            if (!preg_match('/smtp:\/\/([^:]+):([^@]+)@([^:]+):(\d+)/', $this->mailerDsn, $matches)) {
                $io->error('Invalid MAILER_DSN format');
                return Command::FAILURE;
            }

            $username = urldecode($matches[1]); // Decode %40 to @
            $password = $matches[2];
            $host = $matches[3];
            $port = $matches[4];

            $io->section('Connection Details');
            $io->table(
                ['Parameter', 'Value'],
                [
                    ['Host', $host],
                    ['Port', $port],
                    ['Username', $username],
                    ['Password', str_repeat('*', strlen($password))],
                ]
            );

            $io->section('Testing SMTP Connection...');
            
            // Create transport
            $transport = new EsmtpTransport($host, $port);
            $transport->setUsername($username);
            $transport->setPassword($password);
            
            // Try to start connection
            $transport->start();
            
            $io->success('SMTP connection established successfully!');
            
            // Try to send EHLO/HELO
            $io->text('Testing EHLO command...');
            $transport->executeCommand("EHLO [127.0.0.1]\r\n");
            $io->success('EHLO command successful!');
            
            $transport->stop();
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $io->error('SMTP connection failed: ' . $e->getMessage());
            $io->text('Error details: ' . $e->getTraceAsString());
            return Command::FAILURE;
        }
    }
}

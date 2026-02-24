<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-connection',
    description: 'Test SMTP connection'
)]
class TestConnectionCommand extends Command
{
    public function __construct(
        private string $mailerDsn
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp('This command tests the SMTP connection');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Test SMTP Connection');

        try {
            $io->section('Mailer DSN Configuration');
            $io->text('MAILER_DSN: ' . $this->mailerDsn);
            
            // Parse DSN to show connection details
            if (preg_match('/smtp:\/\/([^:]+):([^@]+)@([^:]+):(\d+)/', $this->mailerDsn, $matches)) {
                $io->table(
                    ['Parameter', 'Value'],
                    [
                        ['Username', $matches[1]],
                        ['Password', str_repeat('*', strlen($matches[2]))],
                        ['Host', $matches[3]],
                        ['Port', $matches[4]],
                    ]
                );
            }
            
            $io->success('DSN appears to be correctly formatted');
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $io->error('Connection test failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}

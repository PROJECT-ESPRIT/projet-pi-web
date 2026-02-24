<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use App\Service\EmailService;
use App\Entity\User;

#[AsCommand(
    name: 'app:test-email',
    description: 'Test email sending functionality'
)]
class TestEmailCommand extends Command
{
    public function __construct(
        private EmailService $emailService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Email address to send test to')
            ->setHelp('This command allows you to test email sending functionality');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = $input->getArgument('email');

        $io->title('Test Email Sending');

        try {
            // Create a test user
            $user = new User();
            $user->setEmail($email);
            $user->setPrenom('Test');
            $user->setNom('User');
            $user->setEmailVerificationToken('test-token-123');

            $io->section('Testing EmailService configuration...');
            
            // Test basic email sending
            $io->text('Attempting to send verification email...');
            
            // Test email verification
            $this->emailService->sendEmailVerification($user);
            
            $io->success('Test email sent successfully!');
            $io->info('Check your inbox at: ' . $email);
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $io->error('Email test failed: ' . $e->getMessage());
            $io->text('Error details: ' . $e->getTraceAsString());
            return Command::FAILURE;
        }
    }
}

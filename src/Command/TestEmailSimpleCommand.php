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
    name: 'app:test-email-simple',
    description: 'Test email sending with simple mailer'
)]
class TestEmailSimpleCommand extends Command
{
    public function __construct(
        private \Symfony\Component\Mailer\MailerInterface $mailer,
        private string $adminEmail
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Email address to send test to')
            ->setHelp('This command allows you to test email sending with simple mailer');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = $input->getArgument('email');

        $io->title('Test Simple Email Sending');

        try {
            $io->section('Testing direct mailer...');
            
            $email = (new \Symfony\Bridge\Twig\Mime\TemplatedEmail())
                ->from($this->adminEmail)
                ->to($email)
                ->subject('Test Email — Art Connect')
                ->htmlTemplate('emails/email_verification.html.twig')
                ->context([
                    'user' => (object) [
                        'prenom' => 'Test',
                        'nom' => 'User',
                        'email' => $email
                    ],
                    'verificationUrl' => 'https://example.com/verify',
                    'ttlHours' => 48,
                ]);

            $this->mailer->send($email);
            
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

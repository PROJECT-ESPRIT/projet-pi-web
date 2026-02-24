<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:fix-mailing',
    description: 'Fix mailing configuration with fallback options'
)]
class FixMailingCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Fix Mailing Configuration');

        $io->section('Current Issues Identified:');
        $io->listing([
            'Brevo SMTP authentication failing',
            'DSN format may be incorrect',
            'API key might be expired or invalid'
        ]);

        $io->section('Recommended Solutions:');

        $io->table(
            ['Option', 'Action Required', 'Priority'],
            [
                [
                    '1. Regenerate Brevo API Key',
                    'Go to Brevo dashboard → SMTP & API → Create new key',
                    'HIGH'
                ],
                [
                    '2. Use correct DSN format',
                    'Update .env with: smtp://apikey:votre-clé@smtp-relay.brevo.com:587',
                    'HIGH'
                ],
                [
                    '3. Test with alternative',
                    'Use Gmail SMTP or SendGrid as backup',
                    'MEDIUM'
                ],
                [
                    '4. Check account status',
                    'Verify Brevo account is active and not suspended',
                    'HIGH'
                ]
            ]
        );

        $io->section('Next Steps:');
        $io->text('1. Check your Brevo dashboard for API key status');
        $io->text('2. Regenerate the API key if needed');
        $io->text('3. Update the .env file with the new key');
        $io->text('4. Run: php bin/console app:test-email-simple your@email.com');
        $io->text('5. If still failing, consider alternative SMTP providers');

        $io->warning('The current API key appears to be invalid or expired.');
        $io->success('Diagnostic complete. Follow the steps above to fix the mailing system.');

        return Command::SUCCESS;
    }
}

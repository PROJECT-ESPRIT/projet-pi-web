<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

#[AsCommand(
    name: 'app:mailer:test',
    description: 'Send a test email with the currently configured MAILER_DSN',
)]
class MailerTestCommand extends Command
{
    public function __construct(
        private MailerInterface $mailer
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('to', InputArgument::REQUIRED, 'Recipient email address')
            ->addArgument('from', InputArgument::OPTIONAL, 'From email address', (string) ($_ENV['MAILER_FROM'] ?? 'no-reply@artconnect.local'));
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $to = (string) $input->getArgument('to');
        $from = (string) $input->getArgument('from');

        $email = (new Email())
            ->from($from)
            ->to($to)
            ->subject('Test SMTP Art Connect')
            ->text('Test SMTP reussi.');

        try {
            $this->mailer->send($email);
            $io->success(sprintf('Email envoye a %s depuis %s', $to, $from));

            return Command::SUCCESS;
        } catch (TransportExceptionInterface $e) {
            $io->error('Echec SMTP: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}

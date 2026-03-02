<?php

namespace App\Command;

use App\Service\MailService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:test-mail-service',
    description: 'Teste l\'envoi d\'email avec le MailService'
)]
class TestEmailCommand extends Command
{
    public function __construct(private MailService $mailService)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Test d\'envoi d\'email avec MailService...</info>');
        
        $postTitle = 'Test Post - ' . date('Y-m-d H:i:s');
        $commentContent = 'Ceci est un test de commentaire pour vérifier l\'envoi d\'email.';
        $authorName = 'Test User';
        $commentDate = new \DateTimeImmutable();

        $output->writeln('<comment>Données de test:</comment>');
        $output->writeln('  - Titre: ' . $postTitle);
        $output->writeln('  - Auteur: ' . $authorName);
        $output->writeln('  - Date: ' . $commentDate->format('Y-m-d H:i:s'));

        $result = $this->mailService->sendCommentNotification(
            $postTitle,
            $commentContent,
            $authorName,
            $commentDate
        );

        if ($result) {
            $output->writeln('<info>✅ Email envoyé avec succès !</info>');
            $output->writeln('<info>Vérifiez votre boîte de réception: rayen.dorbez@esprit.tn</info>');
            return Command::SUCCESS;
        } else {
            $output->writeln('<error>❌ Échec de l\'envoi d\'email</error>');
            $output->writeln('<error>Vérifiez les logs pour plus de détails</error>');
            return Command::FAILURE;
        }
    }
}

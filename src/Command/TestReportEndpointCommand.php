<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\BrowserKit\HttpBrowser;
use Symfony\Component\HttpClient\HttpClient;

#[AsCommand(
    name: 'app:test-report-endpoint',
    description: 'Test the report endpoint directly'
)]
class TestReportEndpointCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('reponse-id', InputArgument::REQUIRED, 'ID de la réponse à signaler')
            ->addArgument('user-id', InputArgument::REQUIRED, 'ID de l utilisateur qui signale');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $reponseId = $input->getArgument('reponse-id');
        $userId = $input->getArgument('user-id');

        $io->title('Test du endpoint de signalement');

        $client = HttpClient::create();
        
        try {
            $response = $client->request('POST', "http://localhost:8000/forum-reponse/{$reponseId}/report", [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-Requested-With' => 'XMLHttpRequest',
                ],
                'body' => json_encode([]),
            ]);

            $io->section('Réponse du serveur:');
            $io->text('Status: ' . $response->getStatusCode());
            $io->text('Headers: ' . json_encode($response->getHeaders(), JSON_PRETTY_PRINT));
            
            $content = $response->getContent();
            $io->text('Content: ' . $content);
            
            if ($response->getStatusCode() === 200) {
                $data = json_decode($content, true);
                $io->success('Requête réussie!');
                $io->listing([
                    'Success: ' . ($data['success'] ? 'Oui' : 'Non'),
                    'Reports Count: ' . ($data['reportsCount'] ?? 'N/A'),
                    'Deleted: ' . ($data['deleted'] ? 'Oui' : 'Non')
                ]);
            } else {
                $io->error('Erreur de requête: ' . $response->getStatusCode());
            }

        } catch (\Exception $e) {
            $io->error('Exception: ' . $e->getMessage());
        }

        return Command::SUCCESS;
    }
}

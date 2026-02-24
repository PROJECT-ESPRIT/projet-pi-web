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
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:test-forum-report-endpoint',
    description: 'Test forum report endpoint with authentication'
)]
class TestForumReportEndpointCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('forum-id', InputArgument::REQUIRED, 'ID du forum à signaler')
            ->addArgument('email', InputArgument::REQUIRED, 'Email de l utilisateur')
            ->addArgument('password', InputArgument::REQUIRED, 'Mot de passe de l utilisateur');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $forumId = $input->getArgument('forum-id');
        $email = $input->getArgument('email');
        $password = $input->getArgument('password');

        $io->title('Test du endpoint de signalement avec authentification');

        $client = HttpClient::create();
        
        try {
            // 1. Se connecter
            $io->section('1. Connexion utilisateur');
            $loginResponse = $client->request('POST', 'http://localhost:8000/login', [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'body' => http_build_query([
                    'email' => $email,
                    'password' => $password,
                ]),
            ]);

            $cookies = $loginResponse->getHeaders()['set-cookie'] ?? [];
            $cookieHeader = '';
            foreach ($cookies as $cookie) {
                if (strpos($cookie, 'PHPSESSID') !== false || strpos($cookie, 'REMEMBERME') !== false) {
                    $cookieHeader .= $cookie . '; ';
                }
            }

            $io->text('Status connexion: ' . $loginResponse->getStatusCode());
            
            if ($loginResponse->getStatusCode() !== 200) {
                $io->error('Échec de connexion');
                return Command::FAILURE;
            }

            // 2. Signaler le forum
            $io->section('2. Signalement du forum');
            
            $response = $client->request('POST', "http://localhost:8000/forum/{$forumId}/report", [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-Requested-With' => 'XMLHttpRequest',
                    'Cookie' => $cookieHeader,
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
            $io->text('Stack trace: ' . $e->getTraceAsString());
        }

        return Command::SUCCESS;
    }
}

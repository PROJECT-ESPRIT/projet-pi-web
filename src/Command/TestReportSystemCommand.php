<?php

namespace App\Command;

use App\Entity\ForumReponse;
use App\Entity\ForumReponseSignalement;
use App\Entity\User;
use App\Repository\ForumReponseRepository;
use App\Repository\ForumReponseSignalementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-report-system',
    description: 'Test the forum response reporting system'
)]
class TestReportSystemCommand extends Command
{
    public function __construct(
        private ForumReponseRepository $reponseRepository,
        private ForumReponseSignalementRepository $signalementRepository,
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Test Forum Response Reporting System');

        // 1. Vérifier s'il y a des réponses
        $reponses = $this->reponseRepository->findAll();
        if (empty($reponses)) {
            $io->warning('Aucune réponse trouvée dans la base de données.');
            return Command::SUCCESS;
        }

        $io->section('1. Réponses trouvées:');
        foreach ($reponses as $reponse) {
            $io->text(sprintf('ID: %d - Auteur: %s - Signalements: %d', 
                $reponse->getId(), 
                $reponse->getAuteur()?->getEmail() ?? 'N/A',
                $reponse->getSignalementsCount()
            ));
        }

        // 2. Vérifier les signalements existants
        $io->section('2. Signalements existants:');
        $signalements = $this->signalementRepository->findAll();
        if (empty($signalements)) {
            $io->text('Aucun signalement trouvé.');
        } else {
            foreach ($signalements as $signalement) {
                $io->text(sprintf('ID: %d - Réponse: %d - User: %s - Date: %s',
                    $signalement->getId(),
                    $signalement->getReponse()->getId(),
                    $signalement->getUser()->getEmail(),
                    $signalement->getCreatedAt()->format('Y-m-d H:i:s')
                ));
            }
        }

        // 3. Tester les méthodes du repository
        $io->section('3. Test des méthodes du repository:');
        $testReponse = $reponses[0];
        
        $countByReponse = $this->signalementRepository->countByReponse($testReponse->getId());
        $io->text(sprintf('countByReponse(%d): %d', $testReponse->getId(), $countByReponse));

        // 4. Vérifier les routes
        $io->section('4. Routes de signalement:');
        $io->listing([
            'app_forum_reponse_report → POST /forum-reponse/{id}/report',
            'app_forum_report → POST /forum/{id}/report'
        ]);

        // 5. Vérifier la configuration JavaScript
        $io->section('5. Configuration JavaScript requise:');
        $io->listing([
            'Bouton avec classe: reponse-report-btn',
            'Attributs: data-reponse-id, data-reported',
            'Fetch vers: /forum-reponse/{id}/report',
            'Méthode: POST',
            'Réponse attendue: JSON avec success, reportsCount, deleted'
        ]);

        $io->success('Test du système de signalement terminé!');
        
        return Command::SUCCESS;
    }
}

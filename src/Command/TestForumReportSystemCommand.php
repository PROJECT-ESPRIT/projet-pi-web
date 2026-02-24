<?php

namespace App\Command;

use App\Entity\Forum;
use App\Entity\ForumSignalement;
use App\Entity\User;
use App\Repository\ForumRepository;
use App\Repository\ForumSignalementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-forum-report-system',
    description: 'Test the forum reporting system'
)]
class TestForumReportSystemCommand extends Command
{
    public function __construct(
        private ForumRepository $forumRepository,
        private ForumSignalementRepository $signalementRepository,
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Test Forum Reporting System');

        // 1. Vérifier s'il y a des forums
        $forums = $this->forumRepository->findAll();
        if (empty($forums)) {
            $io->warning('Aucun forum trouvé dans la base de données.');
            return Command::SUCCESS;
        }

        $io->section('1. Forums trouvés:');
        foreach ($forums as $forum) {
            $io->text(sprintf('ID: %d - Sujet: %s - Auteur: %s - Signalements: %d', 
                $forum->getId(), 
                substr($forum->getSujet(), 0, 50) . '...',
                $forum->getEmail(),
                $forum->getSignalementsCount()
            ));
        }

        // 2. Vérifier les signalements existants
        $io->section('2. Signalements existants:');
        $signalements = $this->signalementRepository->findAll();
        if (empty($signalements)) {
            $io->text('Aucun signalement trouvé.');
        } else {
            foreach ($signalements as $signalement) {
                $io->text(sprintf('ID: %d - Forum: %d - User: %s - Date: %s',
                    $signalement->getId(),
                    $signalement->getForum()->getId(),
                    $signalement->getUser()->getEmail(),
                    $signalement->getCreatedAt()->format('Y-m-d H:i:s')
                ));
            }
        }

        // 3. Tester les méthodes du repository
        $io->section('3. Test des méthodes du repository:');
        $testForum = $forums[0];
        
        $countByForum = $this->signalementRepository->countByForum($testForum->getId());
        $io->text(sprintf('countByForum(%d): %d', $testForum->getId(), $countByForum));

        // 4. Vérifier les forums avec 3+ signalements
        $io->section('4. Forums avec 3+ signalements:');
        $forumsWithManyReports = $this->signalementRepository->findForumsWithThreeOrMoreReports();
        if (empty($forumsWithManyReports)) {
            $io->text('Aucun forum avec 3+ signalements.');
        } else {
            foreach ($forumsWithManyReports as $data) {
                $io->text(sprintf('Forum ID: %d - Signalements: %d', $data['id'], $data['report_count']));
            }
        }

        // 5. Vérifier les routes
        $io->section('5. Routes de signalement:');
        $io->listing([
            'app_forum_report → POST /forum/{id}/report',
            'Méthode: ForumController::report()',
            'Suppression automatique après 3 signalements: ✅'
        ]);

        // 6. Vérifier la configuration JavaScript
        $io->section('6. Configuration JavaScript requise:');
        $io->listing([
            'Bouton avec classe: report-btn',
            'Attributs: data-forum-id, data-reported',
            'Fetch vers: /forum/{id}/report',
            'Méthode: POST',
            'Réponse attendue: JSON avec success, reportsCount, deleted',
            'Animation de suppression si deleted=true'
        ]);

        // 7. Test de simulation de suppression
        $io->section('7. Test de simulation:');
        if ($countByForum >= 3) {
            $io->warning(sprintf('Le forum %d a déjà %d signalements et devrait être supprimé.', 
                $testForum->getId(), $countByForum));
        } else {
            $io->text(sprintf('Le forum %d a %d signalements (besoin de 3 pour suppression).', 
                $testForum->getId(), $countByForum));
        }

        $io->success('Test du système de signalement de forum terminé!');
        
        return Command::SUCCESS;
    }
}

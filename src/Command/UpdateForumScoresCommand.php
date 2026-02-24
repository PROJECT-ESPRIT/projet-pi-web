<?php

namespace App\Command;

use App\Service\ForumScoringService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:forum:update-scores',
    description: 'Update all forum post scores'
)]
class UpdateForumScoresCommand extends Command
{
    private ForumScoringService $scoringService;

    public function __construct(ForumScoringService $scoringService)
    {
        $this->scoringService = $scoringService;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Updating forum post scores...');
        
        $startTime = microtime(true);
        $this->scoringService->updateAllScores();
        $endTime = microtime(true);
        
        $duration = round(($endTime - $startTime) * 1000, 2);
        $output->writeln("Scores updated successfully in {$duration}ms");
        
        return Command::SUCCESS;
    }
}

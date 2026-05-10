<?php

namespace App\Command;

use App\Service\UserSegmentationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:user:segment',
    description: 'Analyse et met à jour le segment de tous les utilisateurs (VIP, Actif, Dormant, etc.)',
)]
class SegmentUsersCommand extends Command
{
    private UserSegmentationService $segmentationService;

    public function __construct(UserSegmentationService $segmentationService)
    {
        parent::__construct();
        $this->segmentationService = $segmentationService;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Début de la segmentation des utilisateurs');

        try {
            $count = $this->segmentationService->segmentAllUsers();
            $io->success(sprintf('%d utilisateurs ont été segmentés avec succès !', $count));
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Une erreur est survenue lors de la segmentation : ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}

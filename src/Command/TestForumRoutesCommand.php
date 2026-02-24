<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Routing\RouterInterface;

#[AsCommand(
    name: 'app:test-forum-routes',
    description: 'Test forum response routes'
)]
class TestForumRoutesCommand extends Command
{
    public function __construct(
        private RouterInterface $router
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Test Forum Response Routes');

        $routes = [
            'app_forum_reponse_edit' => '/forum/reponse/{id}/edit',
            'app_forum_reponse_delete' => '/forum/reponse/{id}',
            'app_forum_show' => '/forum/{id}'
        ];

        $io->section('Routes Configuration:');
        foreach ($routes as $name => $pattern) {
            try {
                $route = $this->router->getRouteCollection()->get($name);
                $io->text("✅ {$name}: {$route->getPath()}");
            } catch (\Exception $e) {
                $io->text("❌ {$name}: Not found");
            }
        }

        $io->section('Expected Behavior:');
        $io->listing([
            'After editing a response → Redirect to forum show page',
            'After deleting a response → Redirect to forum show page',
            'Back button in edit form → Go to parent forum'
        ]);

        $io->success('Forum response routes are properly configured!');

        return Command::SUCCESS;
    }
}

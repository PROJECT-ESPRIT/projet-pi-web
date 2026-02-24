<?php

namespace App\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class ForumScoringExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../../config/packages'));
        $loader->load('forum_scoring.yaml');

        $configuration = $this->getConfiguration($configs, $container);
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('forum_scoring.weights', $config['weights']);
        $container->setParameter('forum_scoring.anti_spam', $config['anti_spam']);
        $container->setParameter('forum_scoring.caching', $config['caching']);
    }

    public function getAlias(): string
    {
        return 'forum_scoring';
    }
}

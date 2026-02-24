<?php

namespace App\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class ForumScoringConfiguration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('forum_scoring');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->arrayNode('weights')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->floatNode('likes')->defaultValue(2.0)->end()
                        ->floatNode('dislikes')->defaultValue(-1.0)->end()
                        ->floatNode('comments')->defaultValue(3.0)->end()
                        ->floatNode('views')->defaultValue(0.1)->end()
                        ->floatNode('time_decay_rate')->defaultValue(0.01)->end()
                        ->floatNode('base_score')->defaultValue(1.0)->end()
                    ->end()
                ->end()
                ->arrayNode('anti_spam')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('view_cooldown')->defaultValue(300)->end()
                        ->integerNode('max_likes_per_hour')->defaultValue(10)->end()
                        ->integerNode('max_comments_per_hour')->defaultValue(20)->end()
                    ->end()
                ->end()
                ->arrayNode('caching')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('score_update_interval')->defaultValue(300)->end()
                        ->integerNode('trending_cache_ttl')->defaultValue(600)->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}

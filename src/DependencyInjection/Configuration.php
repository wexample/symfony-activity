<?php

namespace Wexample\SymfonyActivity\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Wexample\SymfonyActivity\Enum\ActivityExpiry;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('wexample_symfony_activity');

        $treeBuilder->getRootNode()
            ->children()
                ->arrayNode('categories')
                    ->info('What is recorded, by category: `security`, `page`, `mail`, or one of the application\'s own — `support`, `billing`. A category absent from this list records nothing: installing the package keeps nothing until the application says what to keep.')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->children()
                            ->booleanNode('enabled')
                                ->defaultTrue()
                            ->end()
                            ->integerNode('retention_days')
                                ->info('Days an entry is kept, then purged by `activity:purge`. Unset, kept for good — the default: what to forget, and when, is a rule of the market the application serves, not of the package.')
                                ->defaultNull()
                                ->min(1)
                            ->end()
                            ->enumNode('on_expiry')
                                ->info('delete: the entry is removed; anonymize: it stays, counted, with neither subject, actor nor context.')
                                ->values(array_map(static fn (ActivityExpiry $expiry) => $expiry->value, ActivityExpiry::cases()))
                                ->defaultValue(ActivityExpiry::DELETE->value)
                            ->end()
                        ->end()
                    ->end()
                    ->defaultValue([])
                ->end()
            ->end();

        return $treeBuilder;
    }
}

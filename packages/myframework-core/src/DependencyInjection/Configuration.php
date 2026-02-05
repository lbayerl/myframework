<?php

declare(strict_types=1);

namespace MyFramework\Core\DependencyInjection;

use MyFramework\Core\Util\Env;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        // Konsistenter Alias: Composer/Bundle-Name "MyFrameworkCore" -> "my_framework_core"
        $treeBuilder = new TreeBuilder('my_framework_core');

        $rootNode = $treeBuilder->getRootNode();
        $rootNode
            ->children()
                ->arrayNode('push')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('vapid_public_key')
                            ->defaultValue('%env(resolve:' . Env::VAPID_PUBLIC_KEY . ')%')
                        ->end()
                        ->scalarNode('vapid_private_key')
                            ->defaultValue('%env(resolve:' . Env::VAPID_PRIVATE_KEY . ')%')
                        ->end()
                        ->scalarNode('vapid_subject')
                            ->defaultValue('%env(resolve:' . Env::VAPID_SUBJECT . ')%')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('ui')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('app_name')
                            ->defaultValue('%env(resolve:' . Env::APP_NAME . ')%')
                        ->end()
                        ->scalarNode('primary_color')
                            ->defaultValue('%env(resolve:' . Env::PRIMARY_COLOR . ')%')
                        ->end()
                        ->scalarNode('logo_path')
                            ->defaultNull()
                        ->end()
                        ->scalarNode('home_route')
                            ->defaultValue('app_home')
                            ->info('The route name to use for the home/brand link in navigation')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('mailer')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('from_email')
                            ->defaultValue('%env(resolve:' . Env::FROM_EMAIL . ')%')
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}

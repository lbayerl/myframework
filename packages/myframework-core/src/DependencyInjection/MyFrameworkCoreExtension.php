<?php

declare(strict_types=1);

namespace MyFramework\Core\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

final class MyFrameworkCoreExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('myframework_core.push.vapid_public_key', $config['push']['vapid_public_key']);
        $container->setParameter('myframework_core.push.vapid_private_key', $config['push']['vapid_private_key']);
        $container->setParameter('myframework_core.push.vapid_subject', $config['push']['vapid_subject']);

        $container->setParameter('myframework_core.ui.app_name', $config['ui']['app_name']);
        $container->setParameter('myframework_core.ui.primary_color', $config['ui']['primary_color']);
        $container->setParameter('myframework_core.ui.logo_path', $config['ui']['logo_path'] ?? null);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../../resources/config'));
        $loader->load('services.yaml');
    }
}

<?php

declare(strict_types=1);

namespace Factotum\StructuredDataImportBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Extension\Extension as SymfonyExtension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class Extension extends SymfonyExtension
{
    private const BUNDLE_ALIAS  = 'factotum_structured_data_import_bundle';
    private const CONFIG_DIR    = '/../../config';
    private const SERVICES_FILE = 'services.yaml';

    /**
     * @param array $configs
     * @param ContainerBuilder $container
     * @return void
     * @throws \Exception
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__ . self::CONFIG_DIR)
        );

        $loader->load(self::SERVICES_FILE);
    }

    /**
     * @return string
     */
    public function getAlias(): string
    {
        return self::BUNDLE_ALIAS;
    }
}

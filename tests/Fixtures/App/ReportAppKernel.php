<?php

namespace Wexample\SymfonyActivity\Tests\Fixtures\App;

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * The fixture application reporting: the browser's categories opened, the
 * server's errors kept, repeats merged, and the report endpoint routed.
 */
class ReportAppKernel extends AppKernel
{
    protected function getConfigFiles(): array
    {
        return [
            ...parent::getConfigFiles(),
            __DIR__ . '/config/report.yaml',
        ];
    }

    /**
     * A cache of its own: the routes compiled for another fixture kernel,
     * in the same directory, would be the ones read.
     */
    public function getCacheDir(): string
    {
        return parent::getCacheDir().'/report';
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(__DIR__ . '/../../../src/Controller', 'attribute');
        $routes->import(__DIR__ . '/ReportController', 'attribute');
    }
}

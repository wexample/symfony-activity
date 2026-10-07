<?php

namespace Wexample\SymfonyActivity\Tests\Fixtures\App;

/**
 * The fixture application with a firewall, its members signing in, and the
 * `page` category enabled.
 */
class PageAppKernel extends AppKernel
{
    protected function getConfigFiles(): array
    {
        return [
            ...parent::getConfigFiles(),
            __DIR__ . '/config/page.yaml',
        ];
    }

    protected function getRoutesControllersDir(): ?string
    {
        return __DIR__ . '/Controller';
    }
}

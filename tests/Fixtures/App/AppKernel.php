<?php

namespace Wexample\SymfonyActivity\Tests\Fixtures\App;

use Wexample\SymfonyActivity\WexampleSymfonyActivityBundle;
use Wexample\SymfonyTesting\Tests\Fixtures\AbstractFixtureKernel;

class AppKernel extends AbstractFixtureKernel
{
    protected function getFixtureDir(): string
    {
        return __DIR__;
    }

    protected function getExtraBundles(): iterable
    {
        return [new WexampleSymfonyActivityBundle()];
    }

    protected function getConfigFiles(): array
    {
        return [__DIR__ . '/config/config.yaml'];
    }
}

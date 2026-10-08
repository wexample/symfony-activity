<?php

namespace Wexample\SymfonyActivity\Tests\Fixtures\App;

use DH\AuditorBundle\DHAuditorBundle;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Wexample\SymfonyActivity\WexampleSymfonyActivityBundle;
use Wexample\SymfonySecurity\WexampleSymfonySecurityBundle;
use Wexample\SymfonyTesting\Tests\Fixtures\AbstractFixtureKernel;

class AppKernel extends AbstractFixtureKernel
{
    protected function getFixtureDir(): string
    {
        return __DIR__;
    }

    protected function getExtraBundles(): iterable
    {
        return [
            new SecurityBundle(),
            new DHAuditorBundle(),
            new WexampleSymfonySecurityBundle(),
            new WexampleSymfonyActivityBundle(),
        ];
    }

    protected function getConfigFiles(): array
    {
        return [__DIR__ . '/config/config.yaml'];
    }
}

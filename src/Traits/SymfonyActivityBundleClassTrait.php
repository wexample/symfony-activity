<?php

namespace Wexample\SymfonyActivity\Traits;

use Wexample\SymfonyActivity\WexampleSymfonyActivityBundle;
use Wexample\SymfonyHelpers\Traits\BundleClassTrait;

trait SymfonyActivityBundleClassTrait
{
    use BundleClassTrait;

    public static function getBundleClassName(): string
    {
        return WexampleSymfonyActivityBundle::class;
    }
}

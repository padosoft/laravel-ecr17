<?php

declare(strict_types=1);

namespace Padosoft\Ecr17\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Padosoft\Ecr17\Ecr17ServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            Ecr17ServiceProvider::class,
        ];
    }
}

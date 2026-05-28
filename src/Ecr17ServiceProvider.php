<?php

declare(strict_types=1);

namespace Padosoft\Ecr17;

use Illuminate\Support\ServiceProvider;

final class Ecr17ServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/ecr17.php', 'ecr17');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/ecr17.php' => $this->app->configPath('ecr17.php'),
            ], 'ecr17-config');
        }
    }
}

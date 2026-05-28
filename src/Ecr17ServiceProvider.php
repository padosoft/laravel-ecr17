<?php

declare(strict_types=1);

namespace Padosoft\Ecr17;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Padosoft\Ecr17\Transport\SocketTransport;

final class Ecr17ServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/ecr17.php', 'ecr17');

        $this->app->singleton('ecr17', function (Application $app): Ecr17Client {
            /** @var array<string,mixed> $cfg */
            $cfg = $app['config']->get('ecr17', []);
            $config = Ecr17Config::fromArray($cfg);
            $transport = new SocketTransport($config->host, $config->port, $config->connectionTimeoutMs);

            return new Ecr17Client($transport, $config);
        });

        $this->app->alias('ecr17', Ecr17Client::class);
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

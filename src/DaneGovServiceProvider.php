<?php

declare(strict_types=1);

namespace Beziworld\DaneGov;

use Beziworld\DaneGov\Console\ResourceCommand;
use Beziworld\DaneGov\Console\SearchCommand;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the dane.gov.pl client, its configuration and Artisan commands.
 */
final class DaneGovServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/dane-gov.php', 'dane-gov');

        $this->app->singleton(DaneGovConfig::class, static function (Application $app): DaneGovConfig {
            $config = $app->make(Config::class)->get('dane-gov', []);

            return DaneGovConfig::fromArray(is_array($config) ? $config : []);
        });

        $this->app->singleton(DaneGovClient::class, static function (Application $app): DaneGovClient {
            $config = $app->make(DaneGovConfig::class);

            return new DaneGovClient(
                http: $app->make(HttpFactory::class),
                config: $config,
                cache: $config->cachesResponses() ? $app->make(CacheFactory::class)->store($config->cacheStore) : null,
            );
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__ . '/../config/dane-gov.php' => $this->app->configPath('dane-gov.php')], 'dane-gov-config');

            $this->commands([SearchCommand::class, ResourceCommand::class]);
        }
    }
}

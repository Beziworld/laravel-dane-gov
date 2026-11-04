<?php

declare(strict_types=1);

namespace Beziworld\DaneGov\Tests;

use Beziworld\DaneGov\DaneGovClient;
use Beziworld\DaneGov\DaneGovConfig;
use Beziworld\DaneGov\DaneGovServiceProvider;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * @var list<string>
     */
    private static array $directories = [];

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        foreach (self::$directories as $directory) {
            \Illuminate\Support\Facades\File::deleteDirectory($directory);
        }

        self::$directories = [];

        parent::tearDown();
    }

    protected function getPackageProviders($app): array
    {
        return [DaneGovServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('cache.default', 'array');
        $app['config']->set('dane-gov.retries', 0);
        $app['config']->set('dane-gov.cache.enabled', false);
    }

    /**
     * Response body captured from the live API.
     */
    public static function fixture(string $name): string
    {
        $contents = file_get_contents(__DIR__ . '/Fixtures/' . $name);

        if ($contents === false) {
            throw new \RuntimeException('Missing fixture ' . $name);
        }

        return $contents;
    }

    /**
     * Decoded fixture.
     *
     * @return array<string, mixed>
     */
    public static function document(string $name): array
    {
        return json_decode(self::fixture($name), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Client built from configuration overrides.
     *
     * @param array<string, mixed> $config
     */
    public function client(array $config = []): DaneGovClient
    {
        $config = DaneGovConfig::fromArray(array_replace_recursive(['retries' => 0, 'cache' => ['enabled' => false]], $config));

        return new DaneGovClient(
            $this->app->make(HttpFactory::class),
            $config,
            $config->cachesResponses() ? $this->app->make(CacheFactory::class)->store('array') : null,
        );
    }

    /**
     * Fresh temporary directory for downloads.
     */
    public static function temporaryDirectory(): string
    {
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dane-gov-tests-' . bin2hex(random_bytes(4));
        mkdir($directory, 0775, true);
        self::$directories[] = $directory;

        return $directory;
    }
}

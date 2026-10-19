<?php

declare(strict_types=1);

namespace Gcego\Registry\Tests;

use Gcego\Core\GcegoServiceProvider;
use Gcego\Registry\RegistryServiceProvider;
use Gcego\Registry\Tests\Fixtures\LoadCounter;
use Gcego\Registry\Tests\Fixtures\Workspace;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected Workspace $workspace;

    /**
     * @var array<string, mixed>
     */
    private array $registryConfig = [];

    protected function setUp(): void
    {
        $this->workspace = new Workspace;
        LoadCounter::reset();

        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->workspace->remove();
    }

    /**
     * Rebuild the application with gcego-registry overrides (keys relative to "gcego-registry").
     *
     * @param  array<string, mixed>  $config
     */
    protected function rebuildApplication(array $config = []): Application
    {
        $this->registryConfig = $config;
        $this->refreshApplication();

        /** @var Application */
        return $this->app;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function rebuildApplicationPublic(array $config = []): Application
    {
        return $this->rebuildApplication($config);
    }

    protected function getPackageProviders($app): array
    {
        return [GcegoServiceProvider::class, RegistryServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('gcego-registry.discovery.roots', [
            ['path' => $this->workspace->path('Modules'), 'kind' => 'blueprint', 'entrypoint' => 'directory'],
            ['path' => $this->workspace->path('Pages'), 'kind' => 'page', 'entrypoint' => 'file'],
        ]);
        $app['config']->set('gcego-registry.cache.enabled', false);
        $app['config']->set('gcego-registry.cache.path', $this->workspace->path('cache/gcego-registry.php'));

        foreach ($this->registryConfig as $key => $value) {
            $app['config']->set('gcego-registry.'.$key, $value);
        }
    }
}

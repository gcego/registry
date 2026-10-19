<?php

declare(strict_types=1);

namespace Gcego\Registry;

use Gcego\Blueprints\Contracts\BlueprintResolver;
use Gcego\Core\Contracts\PackageInterface;
use Gcego\Core\Packages\PackageDescriptor;
use Gcego\Registry\Cache\FileRegistryCache;
use Gcego\Registry\Console\CacheRegistryCommand;
use Gcego\Registry\Console\ClearRegistryCommand;
use Gcego\Registry\Contracts\DefinitionLoader;
use Gcego\Registry\Contracts\RegistryCache;
use Gcego\Registry\Contracts\RegistryInterface;
use Gcego\Registry\Discovery\DefinitionDiscovery;
use Gcego\Registry\Discovery\DirectoryScanner;
use Gcego\Registry\Discovery\DiscoveryPath;
use Gcego\Registry\Kinds\BlueprintKind;
use Gcego\Registry\Kinds\DefinitionKinds;
use Gcego\Registry\Loading\PhpDefinitionLoader;
use Gcego\Registry\Resolution\DefinitionResolver;
use Gcego\Registry\Resolution\RegistryBlueprintResolver;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

/**
 * Laravel integration for gcego/registry. It is also the "gcego/registry"
 * GCEGO package (gcego/core discovers it).
 *
 * register() only declares bindings; nothing is scanned or loaded until the
 * registry is first used.
 */
final class RegistryServiceProvider extends ServiceProvider implements PackageInterface
{
    public const string ConfigTag = 'gcego-registry-config';

    public function register(): void
    {
        $this->mergeConfigFrom(self::configPath(), 'gcego-registry');

        $this->app->singleton(RegistryConfig::class, static function (Application $app): RegistryConfig {
            $config = $app->make(Repository::class)->get('gcego-registry', []);

            return RegistryConfig::fromArray(is_array($config) ? $config : []);
        });

        $this->app->singleton(DefinitionKinds::class, static function (): DefinitionKinds {
            $kinds = new DefinitionKinds;
            $kinds->register(new BlueprintKind);

            return $kinds;
        });

        $this->app->singleton(DefinitionDiscovery::class, static function (Application $app): DefinitionDiscovery {
            $config = $app->make(RegistryConfig::class);

            return new DefinitionDiscovery($config->activeRoots(), new DirectoryScanner($config->ignoredDirectories));
        });

        $this->app->singleton(PhpDefinitionLoader::class, static fn (Application $app): PhpDefinitionLoader => new PhpDefinitionLoader(
            $app,
            array_map(static fn (DiscoveryPath $root): string => $root->path, $app->make(DefinitionDiscovery::class)->roots()),
        ));
        $this->app->alias(PhpDefinitionLoader::class, DefinitionLoader::class);

        $this->app->singleton(FileRegistryCache::class, static fn (Application $app): FileRegistryCache => new FileRegistryCache(
            $app->make(RegistryConfig::class)->cachePath ?: $app->bootstrapPath('cache/gcego-registry.php'),
        ));
        $this->app->alias(FileRegistryCache::class, RegistryCache::class);

        $this->app->singleton(DefinitionResolver::class, static fn (Application $app): DefinitionResolver => new DefinitionResolver(
            $app->make(DefinitionLoader::class),
            $app->make(DefinitionKinds::class),
            $app->make(RegistryConfig::class)->validateOnResolve,
        ));

        $this->app->singleton(RegistryManager::class);
        $this->app->singleton(Registry::class);
        $this->app->alias(Registry::class, RegistryInterface::class);
        $this->app->singleton(BlueprintResolver::class, RegistryBlueprintResolver::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([self::configPath() => $this->app->configPath('gcego-registry.php')], self::ConfigTag);
            $this->commands([CacheRegistryCommand::class, ClearRegistryCommand::class]);
            $this->optimizes(optimize: 'gcego:registry-cache', clear: 'gcego:registry-clear', key: 'gcego-registry');
        }
    }

    public function descriptor(): PackageDescriptor
    {
        return new PackageDescriptor(
            'gcego/registry',
            requires: ['gcego/core'],
            capabilities: ['registry'],
            description: 'Definition discovery, registration and resolution.',
        );
    }

    public static function configPath(): string
    {
        return dirname(__DIR__).'/config/gcego-registry.php';
    }
}

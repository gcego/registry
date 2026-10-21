<?php

declare(strict_types=1);

use Gcego\Core\Gcego;
use Gcego\Registry\Cache\FileRegistryCache;
use Gcego\Registry\Contracts\DefinitionLoader;
use Gcego\Registry\Contracts\RegistryCache;
use Gcego\Registry\Contracts\RegistryInterface;
use Gcego\Registry\Diagnostics\RegistryInspector;
use Gcego\Registry\Discovery\DefinitionDiscovery;
use Gcego\Registry\Kinds\DefinitionKinds;
use Gcego\Registry\Loading\PhpDefinitionLoader;
use Gcego\Registry\Registration\DefinitionRegistrar;
use Gcego\Registry\Registry;
use Gcego\Registry\RegistryConfig;
use Gcego\Registry\RegistryManager;
use Gcego\Registry\RegistryServiceProvider;
use Gcego\Registry\Resolution\DefinitionResolver;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

it('resolves every service', function (string $abstract, string $concrete): void {
    expect(app($abstract))->toBeInstanceOf($concrete);
})->with([
    [Registry::class, Registry::class],
    [RegistryInterface::class, Registry::class],
    [RegistryManager::class, RegistryManager::class],
    [RegistryConfig::class, RegistryConfig::class],
    [DefinitionKinds::class, DefinitionKinds::class],
    [DefinitionDiscovery::class, DefinitionDiscovery::class],
    [DefinitionLoader::class, PhpDefinitionLoader::class],
    [RegistryCache::class, FileRegistryCache::class],
    [DefinitionResolver::class, DefinitionResolver::class],
    [DefinitionRegistrar::class, DefinitionRegistrar::class],
    [RegistryInspector::class, RegistryInspector::class],
]);

it('shares framework-wide singletons', function (string $abstract): void {
    expect(app($abstract))->toBe(app($abstract));
})->with([Registry::class, RegistryManager::class, DefinitionKinds::class, RegistryConfig::class, PhpDefinitionLoader::class, FileRegistryCache::class]);

it('aliases the contract to the registry', function (): void {
    expect(app(RegistryInterface::class))->toBe(app(Registry::class));
});

it('starts without package-specific kinds', function (): void {
    $app = new Application(dirname(__DIR__, 2));
    $app->instance('config', new Repository);
    $app->register(RegistryServiceProvider::class);

    expect($app->make(DefinitionKinds::class)->ids())->toBe([]);
});

it('merges and publishes configuration', function (): void {
    expect(config('gcego-registry.strict'))->toBeTrue()
        ->and(config('gcego-registry.discovery.ignore'))->toBe(['Tests', 'tests', 'Fixtures', 'fixtures'])
        ->and(ServiceProvider::pathsToPublish(RegistryServiceProvider::class, RegistryServiceProvider::ConfigTag))
        ->toBe([RegistryServiceProvider::configPath() => config_path('gcego-registry.php')]);
});

it('declares Laravel package discovery', function (): void {
    $manifest = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/composer.json'), true, flags: JSON_THROW_ON_ERROR);

    expect(is_array($manifest) ? $manifest['extra']['laravel']['providers'] : null)->toBe([RegistryServiceProvider::class]);
});

it('takes part in the gcego/core package lifecycle', function (): void {
    $gcego = app(Gcego::class);

    expect($gcego->isBooted())->toBeTrue()
        ->and($gcego->isPackageActive('gcego/registry'))->toBeTrue()
        ->and($gcego->hasCapability('registry'))->toBeTrue()
        ->and($gcego->bootResult()?->packages)->toBe(['gcego/core', 'gcego/registry']);
});

it('does not read env() at runtime', function (): void {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/src', FilesystemIterator::SKIP_DOTS));

    foreach ($iterator as $file) {
        if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
            continue;
        }

        $calls = array_filter(
            token_get_all((string) file_get_contents($file->getPathname())),
            fn (mixed $token): bool => is_array($token) && $token[0] === T_STRING && $token[1] === 'env',
        );

        expect($calls)->toBe([], $file->getPathname().' calls env()');
    }
});

it('applies configuration overrides', function (): void {
    $this->rebuildApplication(['strict' => false, 'cache.verify' => false]);

    expect(app(RegistryConfig::class)->strict)->toBeFalse()
        ->and(app(RegistryConfig::class)->verifyCache)->toBeFalse();
});

/**
 * @return list<object>
 */
function reachable(object $root): array
{
    $seen = [];
    $stack = [$root];

    while ($stack !== []) {
        $object = array_pop($stack);

        if (isset($seen[spl_object_id($object)]) || $object instanceof Container) {
            continue;
        }

        $seen[spl_object_id($object)] = $object;

        foreach ((array) $object as $value) {
            foreach (is_array($value) ? $value : [$value] as $item) {
                if (is_object($item)) {
                    $stack[] = $item;
                }
                if (is_array($item)) {
                    foreach ($item as $nested) {
                        if (is_object($nested)) {
                            $stack[] = $nested;
                        }
                    }
                }
            }
        }
    }

    return array_values($seen);
}

it('keeps no request state between requests', function (): void {
    $this->workspace->module('Modules/Subjects/Subjects.php', 'subjects');
    Route::get('/probe', fn () => ['title' => app(Registry::class)->get('module:'.'subjects')->title()]);

    $registry = app(Registry::class);
    $this->get('/probe?user=alice')->assertOk()->assertJson(['title' => 'Subjects']);
    $this->get('/probe?user=bob')->assertOk()->assertJson(['title' => 'Subjects']);

    expect(app(Registry::class))->toBe($registry);

    foreach (reachable($registry) as $object) {
        expect($object)->not->toBeInstanceOf(Request::class);
    }
});

it('keeps no static mutable state in its own classes', function (): void {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/src', FilesystemIterator::SKIP_DOTS));

    foreach ($iterator as $file) {
        if ($file instanceof SplFileInfo && $file->getExtension() === 'php') {
            expect((string) file_get_contents($file->getPathname()))->not->toMatch('/(private|protected|public)\s+static\s+(?!function)/');
        }
    }
});

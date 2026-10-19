<?php

declare(strict_types=1);

use Gcego\Registry\Exceptions\DiscoveryException;
use Gcego\Registry\RegistryConfig;

it('reads the shipped defaults', function (): void {
    $config = RegistryConfig::fromArray(require dirname(__DIR__, 2).'/config/gcego-registry.php');

    expect($config->discoveryEnabled)->toBeTrue()
        ->and(array_map(fn ($root): string => $root->kind, $config->roots))->toBe(['blueprint', 'page', 'widget', 'layout', 'action', 'navigation', 'panel'])
        ->and($config->roots[0]->path)->toEndWith('GCEGo/Modules')
        ->and($config->roots[0]->entrypoint->strategy)->toBe('directory')
        ->and($config->ignoredDirectories)->toBe(['Tests', 'tests', 'Fixtures', 'fixtures'])
        ->and($config->strict)->toBeTrue()
        ->and($config->cacheEnabled)->toBeTrue()
        ->and($config->whenCacheMissing)->toBe('build');
});

it('disables roots when discovery is off', function (): void {
    $config = RegistryConfig::fromArray(['discovery' => ['enabled' => 'false', 'roots' => [['path' => '/x', 'kind' => 'blueprint']]]]);

    expect($config->discoveryEnabled)->toBeFalse()
        ->and($config->roots)->toHaveCount(1)
        ->and($config->activeRoots())->toBe([]);
});

it('rejects invalid configuration', function (array $config, string $message): void {
    expect(fn () => RegistryConfig::fromArray($config))->toThrow(DiscoveryException::class, $message);
})->with([
    [['discovery' => 'yes'], 'gcego-registry.discovery: must be an array'],
    [['discovery' => ['roots' => ['app/GCEGo']]], 'each root must be an array'],
    [['providers' => [5]], 'gcego-registry.providers: must contain non-empty strings'],
    [['strict' => 'sometimes'], 'gcego-registry.strict: must be a boolean'],
    [['cache' => ['missing' => 'ignore']], 'gcego-registry.cache.missing: must be "build" or "fail"'],
    [['cache' => ['enabled' => true, 'path' => '']], 'gcego-registry.cache.path'],
]);

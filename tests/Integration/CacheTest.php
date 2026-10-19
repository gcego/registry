<?php

declare(strict_types=1);

use Gcego\Registry\Cache\FileRegistryCache;
use Gcego\Registry\Exceptions\DefinitionLoadException;
use Gcego\Registry\Exceptions\RegistryCacheException;
use Gcego\Registry\Registry;
use Gcego\Registry\RegistryManager;
use Gcego\Registry\Tests\Fixtures\LoadCounter;
use Gcego\Registry\Tests\TestCase;
use Illuminate\Support\ServiceProvider;

/**
 * @param  array<string, mixed>  $cache
 */
function withCache(TestCase $test, array $cache = []): void
{
    $test->rebuildApplicationPublic(array_merge(['cache.enabled' => true, 'cache.verify' => false], array_combine(
        array_map(fn (string $key): string => 'cache.'.$key, array_keys($cache)),
        array_values($cache),
    )));
}

it('builds the cache on first use and then serves metadata without loading files', function (): void {
    $this->workspace->blueprint('Modules/Subjects/Subjects.php', 'subjects');
    withCache($this);

    expect(app(Registry::class)->names())->toBe(['subjects'])
        ->and(app(Registry::class)->origin())->toBe('discovery')
        ->and(is_file($this->workspace->path('cache/gcego-registry.php')))->toBeTrue();

    LoadCounter::reset();
    withCache($this);
    $registry = app(Registry::class);

    expect($registry->names())->toBe(['subjects'])
        ->and($registry->descriptor('subjects')->metadata['title'])->toBe('Subjects')
        ->and($registry->origin())->toBe('cache')
        ->and(LoadCounter::total())->toBe(0);

    $registry->get('subjects');
    $registry->get('subjects');

    expect(LoadCounter::total())->toBe(1);
});

it('does not see new files in production until the cache is rebuilt', function (): void {
    $this->workspace->blueprint('Modules/Subjects/Subjects.php', 'subjects');
    withCache($this);
    app(Registry::class)->names();

    $this->workspace->blueprint('Modules/Topics/Topics.php', 'topics');
    withCache($this);

    expect(app(Registry::class)->names())->toBe(['subjects']);

    $this->artisan('gcego:registry-cache')->assertSuccessful();
    withCache($this);

    expect(app(Registry::class)->names())->toBe(['subjects', 'topics']);
});

it('rebuilds automatically when verification detects changes', function (): void {
    $this->workspace->blueprint('Modules/Subjects/Subjects.php', 'subjects');
    withCache($this, ['verify' => true]);
    app(Registry::class)->names();

    withCache($this, ['verify' => true]);
    LoadCounter::reset();

    expect(app(Registry::class)->names())->toBe(['subjects'])
        ->and(app(Registry::class)->origin())->toBe('cache')
        ->and(LoadCounter::total())->toBe(0);

    $this->workspace->blueprint('Modules/Topics/Topics.php', 'topics');
    withCache($this, ['verify' => true]);

    expect(app(Registry::class)->names())->toBe(['subjects', 'topics'])
        ->and(app(Registry::class)->origin())->toBe('discovery');
});

it('reports stale entries when a cached file disappears or changes identity', function (): void {
    $path = $this->workspace->blueprint('Modules/Subjects/Subjects.php', 'subjects');
    withCache($this);
    app(Registry::class)->names();

    $this->workspace->blueprint('Modules/Subjects/Subjects.php', 'courses');
    withCache($this);

    expect(fn () => app(Registry::class)->get('subjects'))->toThrow(DefinitionLoadException::class, 'no longer does. Rebuild the registry cache');

    unlink($path);
    withCache($this);

    expect(fn () => app(Registry::class)->get('subjects'))->toThrow(DefinitionLoadException::class, 'does not exist');
});

it('fails clearly when the cache is required but missing', function (): void {
    withCache($this, ['missing' => 'fail']);

    expect(fn () => app(Registry::class)->names())->toThrow(RegistryCacheException::class, 'does not exist and gcego-registry.cache.missing is "fail"');
});

it('rebuilds a corrupt cache, or fails under the fail policy', function (): void {
    $this->workspace->blueprint('Modules/Subjects/Subjects.php', 'subjects');
    $this->workspace->file('cache/gcego-registry.php', '<?php return [');

    withCache($this);

    expect(app(Registry::class)->names())->toBe(['subjects']);

    $this->workspace->file('cache/gcego-registry.php', '<?php return "corrupt";');
    withCache($this, ['missing' => 'fail']);

    expect(fn () => app(Registry::class)->names())->toThrow(RegistryCacheException::class, 'is corrupt');
});

it('reuses a manifest written by a concurrent rebuild', function (): void {
    $this->workspace->blueprint('Modules/Subjects/Subjects.php', 'subjects');
    withCache($this);
    $manager = app(RegistryManager::class);
    $manager->rebuild();
    LoadCounter::reset();

    // A second process that waited for the lock finds an up-to-date manifest and does not rebuild.
    $loaded = $manager->rebuild();

    expect($loaded->origin)->toBe('cache')
        ->and(LoadCounter::total())->toBe(0);
});

it('never writes a cache from an invalid registry', function (): void {
    $this->workspace->file('Modules/Broken/Broken.php', '<?php return 42;');
    withCache($this);

    expect(fn () => $this->artisan('gcego:registry-cache'))->toThrow(DefinitionLoadException::class)
        ->and(is_file($this->workspace->path('cache/gcego-registry.php')))->toBeFalse();
});

it('clears the cache through artisan and reports status', function (): void {
    $this->workspace->blueprint('Modules/Subjects/Subjects.php', 'subjects');
    withCache($this);
    $this->artisan('gcego:registry-cache')->expectsOutputToContain('GCEGO registry cached: 1 definition(s).')->assertSuccessful();

    $status = app(RegistryManager::class)->status();

    expect($status->exists)->toBeTrue()
        ->and($status->valid)->toBeTrue()
        ->and($status->fresh)->toBeTrue()
        ->and($status->definitions)->toBe(1);

    $this->artisan('gcego:registry-clear')->assertSuccessful();

    expect(app(FileRegistryCache::class)->exists())->toBeFalse()
        ->and(app(RegistryManager::class)->status()->exists)->toBeFalse();
});

it('hooks into php artisan optimize', function (): void {
    $reflection = new ReflectionClass(ServiceProvider::class);

    expect($reflection->getStaticPropertyValue('optimizeCommands'))->toHaveKey('gcego-registry', 'gcego:registry-cache')
        ->and($reflection->getStaticPropertyValue('optimizeClearCommands'))->toHaveKey('gcego-registry', 'gcego:registry-clear');
});

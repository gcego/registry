<?php

declare(strict_types=1);

use Gcego\Registry\Cache\FileRegistryCache;
use Gcego\Registry\Cache\Manifest;
use Gcego\Registry\Exceptions\RegistryCacheException;
use Gcego\Registry\Identity\DefinitionIdentity;
use Gcego\Registry\Index\DefinitionDescriptor;
use Gcego\Registry\Index\DefinitionIndex;
use Gcego\Registry\Index\DefinitionSource;
use Gcego\Registry\Tests\Fixtures\ExampleDefinition;

function manifest(): Manifest
{
    return new Manifest(
        new DefinitionIndex(new DefinitionDescriptor(DefinitionIdentity::fromString('module:subjects'), DefinitionSource::file('/app/Subjects.php'), ExampleDefinition::class, ['title' => 'Subjects'])),
        'fingerprint-1',
        '2026-10-17T00:00:00Z',
    );
}

it('writes and reads a manifest', function (): void {
    $cache = new FileRegistryCache($this->workspace->path('cache/nested/registry.php'));

    expect($cache->exists())->toBeFalse()
        ->and($cache->read())->toBeNull();

    $cache->write(manifest());
    $read = $cache->read();

    expect($cache->exists())->toBeTrue()
        ->and($read?->fingerprint)->toBe('fingerprint-1')
        ->and($read?->generatedAt)->toBe('2026-10-17T00:00:00Z')
        ->and($read?->index->toArray())->toBe(manifest()->index->toArray());
});

it('writes plain PHP arrays only, atomically', function (): void {
    $cache = new FileRegistryCache($this->workspace->path('cache/registry.php'));
    $cache->write(manifest());
    $cache->write(manifest());
    $contents = (string) file_get_contents($cache->path());

    expect($contents)->toStartWith("<?php\n")
        ->and($contents)->not->toContain('unserialize')
        ->and($contents)->not->toContain('O:')
        ->and(glob($this->workspace->path('cache').'/*'))->toBe([$cache->path()]);
});

it('detects corruption', function (string $contents, string $reason): void {
    $path = $this->workspace->file('cache/registry.php', $contents);

    expect(fn () => (new FileRegistryCache($path))->read())->toThrow(RegistryCacheException::class, $reason);
})->with([
    'syntax error' => ['<?php return [', 'unreadable PHP'],
    'not an array' => ['<?php return "x";', 'not an array'],
    'wrong format' => ['<?php return ["format" => 99];', 'unsupported format'],
    'missing fields' => ['<?php return ["format" => 1];', 'missing fields'],
    'checksum' => ['<?php return ["format" => 1, "generated_at" => "x", "fingerprint" => "f", "definitions" => [], "checksum" => "bad"];', 'checksum mismatch'],
]);

it('detects tampered definitions', function (): void {
    $cache = new FileRegistryCache($this->workspace->path('cache/registry.php'));
    $cache->write(manifest());
    file_put_contents($cache->path(), str_replace("'subjects'", "'hijacked'", (string) file_get_contents($cache->path())));

    expect(fn () => $cache->read())->toThrow(RegistryCacheException::class, 'checksum mismatch');
});

it('clears the cache', function (): void {
    $cache = new FileRegistryCache($this->workspace->path('cache/registry.php'));
    $cache->write(manifest());

    expect($cache->clear())->toBeTrue()
        ->and($cache->exists())->toBeFalse()
        ->and($cache->clear())->toBeFalse();
});

it('serializes work under an exclusive lock', function (): void {
    $cache = new FileRegistryCache($this->workspace->path('cache/registry.php'));
    $result = $cache->locked(function () use ($cache): string {
        $handle = fopen($cache->path().'.lock', 'c');
        $acquired = $handle !== false && flock($handle, LOCK_EX | LOCK_NB);

        return $acquired ? 'not locked' : 'locked';
    });

    expect($result)->toBe('locked');
});

it('reports unwritable locations', function (): void {
    $this->workspace->file('blocker', 'a file, not a directory');

    expect(fn () => (new FileRegistryCache($this->workspace->path('blocker/registry.php')))->write(manifest()))
        ->toThrow(RegistryCacheException::class, 'could not be written');
});

<?php

declare(strict_types=1);

use Gcego\Registry\Cache\FileRegistryCache;
use Gcego\Registry\Cache\Manifest;
use Gcego\Registry\Identity\DefinitionIdentity;
use Gcego\Registry\Index\DefinitionDescriptor;
use Gcego\Registry\Index\DefinitionIndex;
use Gcego\Registry\Index\DefinitionSource;
use Gcego\Registry\Registry;
use Gcego\Registry\Tests\Fixtures\ExampleDefinition;

it('discovers and registers hundreds of definitions', function (): void {
    for ($index = 0; $index < 300; $index++) {
        $this->workspace->module(sprintf('Modules/Group%d/Feature%d/Feature%d.php', $index % 10, $index, $index), 'feature-'.$index);
    }

    $start = hrtime(true);
    $count = app(Registry::class)->count();
    $elapsedMs = (hrtime(true) - $start) / 1e6;

    // Generous regression guard, not a benchmark (see benchmarks/run.php).
    expect($count)->toBe(300)
        ->and($elapsedMs)->toBeLessThan(5000.0);
});

it('looks up and filters thousands of cached descriptors quickly', function (): void {
    $descriptors = [];

    for ($index = 0; $index < 5000; $index++) {
        $descriptors[] = new DefinitionDescriptor(new DefinitionIdentity($index % 2 === 0 ? 'module' : 'page', 'd'.$index), DefinitionSource::file('/app/d'.$index.'.php'), ExampleDefinition::class, ['title' => 'D'.$index]);
    }

    $cache = new FileRegistryCache($this->workspace->path('cache/perf.php'));
    $cache->write(new Manifest(new DefinitionIndex(...$descriptors), 'f', 'now'));

    $start = hrtime(true);
    $index = $cache->read()?->index;

    for ($lookup = 0; $lookup < 10000; $lookup++) {
        $index?->get('module:d'.(($lookup * 2) % 5000));
    }

    $pages = $index?->ofKind('page') ?? [];
    $elapsedMs = (hrtime(true) - $start) / 1e6;

    expect(count($pages))->toBe(2500)
        ->and($elapsedMs)->toBeLessThan(2000.0);
});

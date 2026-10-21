<?php

declare(strict_types=1);

use Gcego\Registry\Diagnostics\RegistryInspector;
use Gcego\Registry\Discovery\DefinitionDiscovery;
use Gcego\Registry\Discovery\DiscoveryRoots;
use Gcego\Registry\Exceptions\DiscoveryException;
use Gcego\Registry\Registry;

it('discovers definitions in roots that packages add', function (): void {
    $this->workspace->module('Reports/Fees/Fees.php', 'fees');
    $this->rebuildApplicationPublic(['discovery.roots' => []]);
    app(DiscoveryRoots::class)->add($this->workspace->path('Reports'), 'module', 'directory', 'finance');

    expect(app(Registry::class)->names())->toBe(['fees'])
        ->and(app(Registry::class)->has('module:finance.fees'))->toBeTrue()
        ->and(app(Registry::class)->get('module:finance.fees')->title())->toBe('Fees');
});

it('scans configured roots before package roots and adds each root once', function (): void {
    $roots = app(DiscoveryRoots::class);
    $roots->add($this->workspace->path('Extra'), 'page');
    $roots->add($this->workspace->path('Extra'), 'page');

    expect(array_map(static fn ($root): string => $root->path, app(DefinitionDiscovery::class)->roots()))
        ->toBe([$this->workspace->path('Modules'), $this->workspace->path('Pages'), $this->workspace->path('Extra')]);
});

it('ignores package roots while discovery is disabled', function (): void {
    $this->rebuildApplicationPublic(['discovery.enabled' => false]);
    app(DiscoveryRoots::class)->add($this->workspace->path('Extra'), 'page');

    expect(app(DefinitionDiscovery::class)->roots())->toBe([]);
});

it('reports package roots in the inspector', function (): void {
    app(DiscoveryRoots::class)->add($this->workspace->path('Extra'), 'report');

    expect(array_column(app(RegistryInspector::class)->inspect()->roots, 'kind'))->toBe(['module', 'page', 'report']);
});

it('rejects invalid roots with the root it was given', function (): void {
    expect(fn () => app(DiscoveryRoots::class)->add($this->workspace->path('Extra'), 'Not A Kind'))
        ->toThrow(DiscoveryException::class, 'kind "Not A Kind", added with DiscoveryRoots::add()');
});

it('refuses roots added after discovery started instead of ignoring them', function (): void {
    app(DefinitionDiscovery::class);

    expect(fn () => app(DiscoveryRoots::class)->add($this->workspace->path('Late'), 'page'))
        ->toThrow(DiscoveryException::class, 'was added after discovery started');
});

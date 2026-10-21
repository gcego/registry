<?php

declare(strict_types=1);

use Gcego\Registry\Discovery\DefinitionDiscovery;
use Gcego\Registry\Exceptions\InvalidDefinitionException;
use Gcego\Registry\Kinds\DefinitionKinds;
use Gcego\Registry\Registry;
use Gcego\Registry\Tests\Fixtures\PageDefinition;
use Gcego\Registry\Tests\Fixtures\PageKind;

/*
 * Two packages can own kinds whose roots share one folder. A file matched by
 * both roots belongs to the registered kind that accepts the loaded definition.
 */
function sharedRoots(string $path): array
{
    return [
        'discovery.roots' => [
            ['path' => $path, 'kind' => 'module', 'entrypoint' => 'directory'],
            ['path' => $path, 'kind' => 'page', 'entrypoint' => 'directory'],
        ],
    ];
}

it('records later roots of other kinds as alternatives of a file', function (): void {
    $this->workspace->file('Shared/Subjects/Subjects.php', '<?php return new '.PageDefinition::class."('subjects', 'Subjects');");
    $this->rebuildApplication(sharedRoots($this->workspace->path('Shared')));

    $file = app(DefinitionDiscovery::class)->discover()->files[0];

    expect($file->root->kind)->toBe('module')
        ->and(array_map(static fn ($root): string => $root->kind, $file->alternatives))->toBe(['page']);
});

it('uses an alternative root when the first root kind is not registered', function (): void {
    $this->workspace->file('Shared/Subjects/Subjects.php', '<?php return new '.PageDefinition::class."('subjects', 'Subjects');");
    $this->rebuildApplication([...sharedRoots($this->workspace->path('Shared')), 'discovery.roots' => [
        ['path' => $this->workspace->path('Shared'), 'kind' => 'report', 'entrypoint' => 'directory'],
        ['path' => $this->workspace->path('Shared'), 'kind' => 'page', 'entrypoint' => 'directory'],
    ]]);
    app(DefinitionKinds::class)->register(new PageKind);

    expect(app(Registry::class)->names())->toBe(['subjects'])
        ->and(app(Registry::class)->has('page:subjects'))->toBeTrue();
});

it('gives a file to the registered kind whose contract the definition implements', function (): void {
    $this->workspace->file('Shared/Subjects/Subjects.php', '<?php return new '.PageDefinition::class."('subjects', 'Subjects');");
    $this->workspace->module('Shared/Topics/Topics.php', 'topics');
    $this->rebuildApplication(sharedRoots($this->workspace->path('Shared')));
    app(DefinitionKinds::class)->register(new PageKind);

    expect(app(Registry::class)->names('page'))->toBe(['subjects'])
        ->and(app(Registry::class)->names('module'))->toBe(['topics'])
        ->and(app(Registry::class)->get('page:subjects'))->toBeInstanceOf(PageDefinition::class);
});

it('reports a contract mismatch against the first kind when no candidate accepts the definition', function (): void {
    $this->workspace->file('Shared/Subjects/Subjects.php', '<?php return new stdClass;');
    $this->rebuildApplication(sharedRoots($this->workspace->path('Shared')));
    app(DefinitionKinds::class)->register(new PageKind);

    expect(fn () => app(Registry::class)->names())->toThrow(InvalidDefinitionException::class, 'module');
});

it('keeps attributing a file reachable from nested roots of one kind to the first root', function (): void {
    $this->workspace->module('Modules/Admin/Subjects/Subjects.php', 'subjects');
    $this->rebuildApplication(['discovery.roots' => [
        ['path' => $this->workspace->path('Modules'), 'kind' => 'module', 'entrypoint' => 'directory'],
        ['path' => $this->workspace->path('Modules/Admin'), 'kind' => 'module', 'entrypoint' => 'directory', 'scope' => 'admin'],
    ]]);

    expect(app(DefinitionDiscovery::class)->discover()->files[0]->alternatives)->toBe([])
        ->and(app(Registry::class)->has('module:subjects'))->toBeTrue();
});

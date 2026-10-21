<?php

declare(strict_types=1);

use Gcego\Registry\Exceptions\DefinitionLoadException;
use Gcego\Registry\Exceptions\DefinitionNotFoundException;
use Gcego\Registry\Exceptions\DuplicateDefinitionException;
use Gcego\Registry\Exceptions\InvalidDefinitionException;
use Gcego\Registry\Index\SourceType;
use Gcego\Registry\Kinds\DefinitionKinds;
use Gcego\Registry\Registry;
use Gcego\Registry\Tests\Fixtures\AcademicsProvider;
use Gcego\Registry\Tests\Fixtures\ExampleDefinition;
use Gcego\Registry\Tests\Fixtures\LoadCounter;
use Gcego\Registry\Tests\Fixtures\PageDefinition;
use Gcego\Registry\Tests\Fixtures\PageKind;
use Gcego\Registry\Tests\Fixtures\PagesServiceProvider;
use Gcego\Registry\Tests\Fixtures\Workspace;

function pageFile(Workspace $workspace, string $relative, string $name, string $title = 'A page'): string
{
    return $workspace->file($relative, sprintf("<?php\n\nreturn new %s('%s', '%s');\n", PageDefinition::class, $name, $title));
}

it('does nothing until first use', function (): void {
    $this->workspace->module('Modules/Subjects/Subjects.php', 'subjects');
    $this->rebuildApplication();

    expect(LoadCounter::total())->toBe(0);

    app(Registry::class)->names();

    expect(LoadCounter::total())->toBe(1);
});

it('resolves qualified and bare identifiers', function (): void {
    $this->workspace->module('Modules/Subjects/Subjects.php', 'subjects');
    $registry = app(Registry::class);

    expect($registry->has('subjects'))->toBeTrue()
        ->and($registry->has('module:subjects'))->toBeTrue()
        ->and($registry->has('page:subjects'))->toBeFalse()
        ->and($registry->has('Not An Identity:x'))->toBeFalse()
        ->and($registry->get('subjects'))->toBe($registry->get('module:subjects'))
        ->and($registry->descriptor('subjects')->key())->toBe('module:subjects');
});

it('reuses definitions loaded while building and loads each definition once', function (): void {
    $this->workspace->module('Modules/Subjects/Subjects.php', 'subjects');
    $registry = app(Registry::class);
    $first = $registry->get('subjects');
    $registry->get('subjects');

    expect(LoadCounter::total())->toBe(1)
        ->and($registry->get('subjects'))->toBe($first)
        ->and($registry->origin())->toBe('discovery');
});

it('checks the expected type', function (): void {
    $this->workspace->module('Modules/Subjects/Subjects.php', 'subjects');

    expect(app(Registry::class)->get('subjects', ExampleDefinition::class))->toBeInstanceOf(ExampleDefinition::class)
        ->and(fn () => app(Registry::class)->get('subjects', PageDefinition::class))
        ->toThrow(InvalidDefinitionException::class, 'Definition "module:subjects" is a '.ExampleDefinition::class.', not a '.PageDefinition::class);
});

it('reports missing definitions with suggestions', function (): void {
    $this->workspace->module('Modules/Subjects/Subjects.php', 'subjects');

    expect(fn () => app(Registry::class)->get('subject'))
        ->toThrow(DefinitionNotFoundException::class, 'Definition "subject" is not registered. Did you mean: module:subjects?')
        ->and(fn () => app(Registry::class)->descriptor('page:dashboard'))
        ->toThrow(DefinitionNotFoundException::class, 'Definition "page:dashboard" is not registered.');
});

it('rejects duplicate identities naming both sources', function (): void {
    $first = $this->workspace->module('Modules/Subjects/Subjects.php', 'subjects');
    $second = $this->workspace->module('Modules/Courses/Courses.php', 'subjects');

    try {
        app(Registry::class)->names();
        $this->fail('Expected a duplicate definition error.');
    } catch (DuplicateDefinitionException $exception) {
        expect($exception->getMessage())->toContain('Definition "module:subjects" is defined twice.')
            ->and($exception->getMessage())->toContain($first)
            ->and($exception->getMessage())->toContain($second);
    }
});

it('rejects invalid definitions using module validation', function (): void {
    $path = $this->workspace->module('Modules/Subjects/Subjects.php', 'subjects', "''");

    try {
        app(Registry::class)->names();
        $this->fail('Expected an invalid definition error.');
    } catch (InvalidDefinitionException $exception) {
        expect($exception->getMessage())->toContain('Definition "module:subjects" (file '.$path.') is invalid')
            ->and($exception->errors())->toBe(['title: a module needs a title']);
    }
});

it('rejects definitions of the wrong kind and unnamed definitions', function (): void {
    $this->workspace->file('Modules/Odd/Odd.php', '<?php return new stdClass;');

    expect(fn () => app(Registry::class)->names())->toThrow(InvalidDefinitionException::class, 'returned stdClass, but definitions of kind "module" must implement');

    $this->workspace->remove();
    $this->workspace = new Workspace;
    $this->rebuildApplication();
    $this->workspace->file('Modules/Unnamed/Unnamed.php', '<?php return new Gcego\\Registry\\Tests\\Fixtures\\ExampleDefinition;');

    expect(fn () => app(Registry::class)->names())->toThrow(InvalidDefinitionException::class, 'has no valid name');
});

it('skips and reports problems when not strict', function (): void {
    $this->rebuildApplication(['strict' => false]);
    $this->workspace->module('Modules/Subjects/Subjects.php', 'subjects');
    $this->workspace->file('Modules/Broken/Broken.php', '<?php return new ExampleDefinition(;');

    $registry = app(Registry::class);

    expect($registry->names())->toBe(['subjects'])
        ->and($registry->failures())->toHaveCount(1)
        ->and($registry->failures()[0]->exception)->toBeInstanceOf(DefinitionLoadException::class);
});

it('ignores roots whose kind is not registered and supports custom kinds', function (): void {
    $this->workspace->module('Modules/Subjects/Subjects.php', 'subjects');
    pageFile($this->workspace, 'Pages/Dashboard.php', 'dashboard', 'Dashboard');

    expect(app(Registry::class)->names())->toBe(['subjects']);

    app(DefinitionKinds::class)->register(new PageKind);
    app(Registry::class)->refresh();

    expect(app(Registry::class)->names())->toBe(['subjects', 'dashboard'])
        ->and(app(Registry::class)->get('page:dashboard'))->toBeInstanceOf(PageDefinition::class)
        ->and(app(Registry::class)->descriptor('dashboard')->metadata)->toBe(['title' => 'Dashboard']);
});

it('requires qualification when a bare name is ambiguous', function (): void {
    app(DefinitionKinds::class)->register(new PageKind);
    $this->workspace->module('Modules/Dashboard/Dashboard.php', 'dashboard');
    pageFile($this->workspace, 'Pages/Dashboard.php', 'dashboard');

    expect(fn () => app(Registry::class)->get('dashboard'))
        ->toThrow(DefinitionNotFoundException::class, 'Definition name "dashboard" is ambiguous; use one of: module:dashboard, page:dashboard.')
        ->and(app(Registry::class)->get('page:dashboard'))->toBeInstanceOf(PageDefinition::class);
});

it('scopes identities per root', function (): void {
    $this->rebuildApplication(['discovery.roots' => [
        ['path' => $this->workspace->path('Modules'), 'kind' => 'module', 'entrypoint' => 'directory'],
        ['path' => $this->workspace->path('Admin'), 'kind' => 'module', 'entrypoint' => 'directory', 'scope' => 'admin'],
    ]]);
    $this->workspace->module('Modules/Subjects/Subjects.php', 'subjects');
    $this->workspace->module('Admin/Subjects/Subjects.php', 'subjects');
    $registry = app(Registry::class);

    expect($registry->has('module:subjects'))->toBeTrue()
        ->and($registry->has('module:admin.subjects'))->toBeTrue()
        ->and($registry->get('subjects'))->toBe($registry->get('module:subjects'))
        ->and(array_map(fn ($d): string => $d->key(), $registry->descriptors(scope: 'admin')))->toBe(['module:admin.subjects']);
});

it('registers class-based providers from configuration', function (): void {
    $this->rebuildApplication(['providers' => [AcademicsProvider::class]]);
    $registry = app(Registry::class);

    expect($registry->names())->toBe(['topics', 'lessons'])
        ->and($registry->descriptor('lessons')->source->type)->toBe(SourceType::Provider)
        ->and($registry->descriptors(sourceType: SourceType::Provider))->toHaveCount(2);
});

it('registers objects at runtime without triggering discovery', function (): void {
    $this->workspace->module('Modules/Subjects/Subjects.php', 'subjects');
    $registry = app(Registry::class);
    $reports = new ExampleDefinition('reports');

    $descriptor = $registry->register($reports);

    expect(LoadCounter::total())->toBe(0)
        ->and($descriptor->source->type)->toBe(SourceType::Runtime)
        ->and($registry->register($reports))->toBe($descriptor)
        ->and($registry->get('reports'))->toBe($reports)
        ->and($registry->names())->toBe(['subjects', 'reports'])
        ->and(fn () => $registry->register(new ExampleDefinition('reports')))->toThrow(DuplicateDefinitionException::class)
        ->and(fn () => $registry->register(new ExampleDefinition('subjects')))->toThrow(DuplicateDefinitionException::class)
        ->and(fn () => $registry->register(new ExampleDefinition('Bad Name')))->toThrow(InvalidDefinitionException::class)
        ->and(fn () => $registry->register(new stdClass))->toThrow(InvalidDefinitionException::class, 'matches no registered definition kind');
});

it('detects runtime registrations that collide with discovered definitions', function (): void {
    $this->workspace->module('Modules/Subjects/Subjects.php', 'subjects');
    $registry = app(Registry::class);
    $registry->register(new ExampleDefinition('subjects'));

    expect(fn () => $registry->names())->toThrow(DuplicateDefinitionException::class);
});

it('picks up new files after refresh when the cache is disabled', function (): void {
    $registry = app(Registry::class);

    expect($registry->count())->toBe(0);

    $this->workspace->module('Modules/Subjects/Subjects.php', 'subjects');

    expect($registry->count())->toBe(0);

    $registry->refresh();

    expect($registry->count())->toBe(1)
        ->and($registry->count('module'))->toBe(1)
        ->and($registry->kinds())->toBe(['module']);
});

it('can disable discovery entirely', function (): void {
    $this->rebuildApplication(['discovery.enabled' => false]);
    $this->workspace->module('Modules/Subjects/Subjects.php', 'subjects');

    expect(app(Registry::class)->count())->toBe(0);
});

it('lets a package provider add a kind that discovery then uses', function (): void {
    pageFile($this->workspace, 'Pages/Dashboard.php', 'dashboard', 'Dashboard');
    app(DefinitionKinds::class);
    app()->register(PagesServiceProvider::class);

    expect(app(Registry::class)->names('page'))->toBe(['dashboard']);
});

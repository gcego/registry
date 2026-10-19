<?php

declare(strict_types=1);

use Gcego\Registry\Exceptions\DuplicateDefinitionException;
use Gcego\Registry\Exceptions\InvalidDefinitionException;
use Gcego\Registry\Index\SourceType;
use Gcego\Registry\Kinds\DefinitionKinds;
use Gcego\Registry\Registry;
use Gcego\Registry\RegistryManager;
use Gcego\Registry\Tests\Fixtures\PageBook;
use Gcego\Registry\Tests\Fixtures\PageBookKind;
use Gcego\Registry\Tests\Fixtures\PageDefinition;
use Gcego\Registry\Tests\Fixtures\PageKind;
use Gcego\Registry\Tests\Fixtures\Workspace;

beforeEach(function (): void {
    $kinds = app(DefinitionKinds::class);
    $kinds->register(new PageKind);
    $kinds->register(new PageBookKind);
});

function bookFile(Workspace $workspace, string $path, string $name, array $pages, string $title = 'A page'): string
{
    return $workspace->file($path, sprintf("<?php\n\nreturn new %s('%s', %s, '%s');\n", PageBook::class, $name, var_export($pages, true), $title));
}

it('indexes the definitions a discovered definition derives, with its source', function (): void {
    bookFile($this->workspace, 'Books/Guide.php', 'guide', ['intro', 'faq'], 'Guide page');
    $this->rebuildApplication(['discovery' => ['roots' => [['path' => $this->workspace->path('Books'), 'kind' => 'book', 'entrypoint' => 'file']]]]);
    app(DefinitionKinds::class)->register(new PageKind);
    app(DefinitionKinds::class)->register(new PageBookKind);
    $registry = app(Registry::class);

    expect($registry->names('book'))->toBe(['guide'])
        ->and($registry->names('page'))->toBe(['guide-intro', 'guide-faq'])
        ->and($registry->descriptor('page:guide-intro')->source->type)->toBe(SourceType::File)
        ->and($registry->descriptor('page:guide-intro')->source->location)->toBe($registry->descriptor('book:guide')->source->location)
        ->and($registry->get('page:guide-faq'))->toBeInstanceOf(PageDefinition::class)
        ->and($registry->get('page:guide-faq')->title)->toBe('Guide page');
});

it('loads derived definitions lazily from the cached index', function (): void {
    bookFile($this->workspace, 'Books/Guide.php', 'guide', ['intro']);
    $config = ['discovery' => ['roots' => [['path' => $this->workspace->path('Books'), 'kind' => 'book', 'entrypoint' => 'file']]], 'cache' => ['enabled' => true, 'path' => $this->workspace->path('cache/gcego-registry.php'), 'verify' => false, 'missing' => 'build']];
    $this->rebuildApplication($config);
    app(DefinitionKinds::class)->register(new PageKind);
    app(DefinitionKinds::class)->register(new PageBookKind);
    app(RegistryManager::class)->rebuild();

    $this->rebuildApplication($config);
    app(DefinitionKinds::class)->register(new PageKind);
    app(DefinitionKinds::class)->register(new PageBookKind);
    $registry = app(Registry::class);

    expect($registry->origin())->toBe('cache')
        ->and($registry->get('page:guide-intro'))->toBeInstanceOf(PageDefinition::class);
});

it('registers derived definitions with a runtime registration, once', function (): void {
    $registry = app(Registry::class);
    $book = new PageBook('manual', ['setup', 'usage']);

    $registry->register($book);
    $registry->register($book);

    expect($registry->names('page'))->toBe(['manual-setup', 'manual-usage'])
        ->and($registry->get('page:manual-setup'))->toBeInstanceOf(PageDefinition::class);
});

it('rejects derived definitions that collide or fail validation', function (): void {
    $registry = app(Registry::class);
    $registry->register(new PageDefinition('manual-setup', 'Existing'));

    expect(fn () => $registry->register(new PageBook('manual', ['setup'])))->toThrow(DuplicateDefinitionException::class)
        ->and(fn () => $registry->register(new PageBook('other', ['setup'], '')))->toThrow(InvalidDefinitionException::class);
});

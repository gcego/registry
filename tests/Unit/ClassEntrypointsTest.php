<?php

declare(strict_types=1);

use Gcego\Registry\Exceptions\DefinitionLoadException;
use Gcego\Registry\Loading\ClassEntrypoints;
use Gcego\Registry\Loading\PhpDefinitionLoader;
use Gcego\Registry\Registry;
use Gcego\Registry\Tests\Fixtures\ExampleDefinition;
use Gcego\Registry\Tests\Fixtures\LoadCounter;

/**
 * A class-based provider entrypoint in a namespace unique to the test (classes are declared once per process).
 */
function classEntrypoint(string $return = 'return Subjects::class;', string $name = 'subjects', string $extra = ''): string
{
    $namespace = 'Entry'.bin2hex(random_bytes(5));

    return <<<PHP
        <?php

        declare(strict_types=1);

        namespace {$namespace};

        use Gcego\\Registry\\Contracts\\DefinitionProvider;
        use Gcego\\Registry\\Tests\\Fixtures\\ExampleDefinition;

        \\Gcego\\Registry\\Tests\\Fixtures\\LoadCounter::hit(__FILE__);

        final class Subjects implements DefinitionProvider
        {
            public function definitions(): iterable
            {
                yield new ExampleDefinition('{$name}');
            }
        }
        {$extra}
        {$return}
        PHP;
}

function entrypointLoader(string $trusted): PhpDefinitionLoader
{
    return new PhpDefinitionLoader(app(), [$trusted]);
}

it('finds the classes a file declares, ignoring class constants and anonymous classes', function (): void {
    $path = $this->workspace->file('Modules/Parsed/Parsed.php', <<<'PHP'
        <?php
        namespace App\Parsed;
        interface Shape {}
        enum Size: string { case Small = 's'; }
        final readonly class Parsed { public function make(): object { return new class { }; } }
        return Parsed::class;
        PHP);

    expect(ClassEntrypoints::declaredIn($path))->toBe(['App\\Parsed\\Shape', 'App\\Parsed\\Size', 'App\\Parsed\\Parsed']);
});

it('reads braced and global namespaces', function (): void {
    $path = $this->workspace->file('Modules/Braced/Braced.php', "<?php\nnamespace App\\One { class First {} }\nnamespace { class Second {} }\n");

    expect(ClassEntrypoints::declaredIn($path))->toBe(['App\\One\\First', 'Second']);
});

it('includes a class entrypoint once and reuses its result on later loads', function (): void {
    $path = $this->workspace->file('Modules/Subjects/Subjects.php', classEntrypoint());
    $loader = entrypointLoader($this->workspace->path('Modules'));

    $first = $loader->loadFile($path);
    $second = $loader->loadFile($path);

    expect(LoadCounter::total())->toBe(1)
        ->and(array_map(static fn (object $definition): string => $definition instanceof ExampleDefinition ? $definition->name() : '', [...$first, ...$second]))->toBe(['subjects', 'subjects']);
});

it('accepts a class entrypoint that declares one provider and returns nothing', function (): void {
    $path = $this->workspace->file('Modules/Subjects/Subjects.php', classEntrypoint(return: ''));

    $definitions = entrypointLoader($this->workspace->path('Modules'))->loadFile($path);

    expect($definitions)->toHaveCount(1)
        ->and($definitions[0])->toBeInstanceOf(ExampleDefinition::class);
});

it('does not include a class entrypoint that Composer already autoloaded', function (): void {
    $path = $this->workspace->file('Modules/Subjects/Subjects.php', classEntrypoint());
    (static fn () => require $path)();
    LoadCounter::reset();

    $definitions = entrypointLoader($this->workspace->path('Modules'))->loadFile($path);

    expect($definitions)->toHaveCount(1)
        ->and(LoadCounter::total())->toBe(0);
});

it('refuses to reload a class entrypoint that changed after its classes were loaded', function (): void {
    $path = $this->workspace->file('Modules/Subjects/Subjects.php', classEntrypoint());
    $loader = entrypointLoader($this->workspace->path('Modules'));
    $loader->loadFile($path);
    file_put_contents($path, (string) file_get_contents($path)."\n// edited\n");
    touch($path, time() + 5);

    expect(fn () => $loader->loadFile($path))->toThrow(DefinitionLoadException::class, 'changed after its classes were loaded in this PHP process');
});

it('reports a class that another file already declared instead of failing fatally', function (): void {
    $contents = classEntrypoint();
    $original = $this->workspace->file('Modules/Subjects/Subjects.php', $contents);
    $copy = $this->workspace->file('Modules/Copy/Copy.php', $contents);
    $loader = entrypointLoader($this->workspace->path('Modules'));
    $loader->loadFile($original);

    expect(fn () => $loader->loadFile($copy))->toThrow(DefinitionLoadException::class, 'which is already declared by '.$original);
});

it('reuses the object a class-declaring file returned', function (): void {
    $path = $this->workspace->file('Modules/Subjects/Subjects.php', classEntrypoint(return: "return new ExampleDefinition('subjects');"));
    $loader = entrypointLoader($this->workspace->path('Modules'));

    expect($loader->loadFile($path)[0])->toBe($loader->loadFile($path)[0]);
});

it('rejects a class entrypoint without a return value that declares several providers', function (): void {
    $path = $this->workspace->file('Modules/Subjects/Subjects.php', classEntrypoint(return: '', extra: 'final class Topics implements DefinitionProvider { public function definitions(): iterable { return []; } }'));

    expect(fn () => entrypointLoader($this->workspace->path('Modules'))->loadFile($path))
        ->toThrow(DefinitionLoadException::class, 'declares 2 Gcego\\Registry\\Contracts\\DefinitionProvider classes');
});

it('keeps working across refresh, cache rebuilds and resolution from the cache in one process', function (): void {
    $this->workspace->file('Modules/Academics/Subjects/Subjects.php', classEntrypoint(name: 'subjects'));
    $config = ['cache.enabled' => true, 'cache.verify' => true];
    $this->rebuildApplication($config);

    expect(app(Registry::class)->get('module:subjects'))->toBeInstanceOf(ExampleDefinition::class);

    app(Registry::class)->refresh();
    expect(app(Registry::class)->names())->toBe(['subjects']);

    $this->artisan('gcego:registry-cache')->assertSuccessful();
    $this->rebuildApplication($config);

    expect(app(Registry::class)->origin())->toBe('cache')
        ->and(app(Registry::class)->get('module:subjects'))->toBeInstanceOf(ExampleDefinition::class)
        ->and(LoadCounter::total())->toBe(1);
});

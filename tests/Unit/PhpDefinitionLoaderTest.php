<?php

declare(strict_types=1);

use Gcego\Blueprints\Blueprint;
use Gcego\Registry\Exceptions\DefinitionLoadException;
use Gcego\Registry\Index\DefinitionSource;
use Gcego\Registry\Loading\PhpDefinitionLoader;
use Gcego\Registry\Tests\Fixtures\AcademicsProvider;
use Gcego\Registry\Tests\Fixtures\BrokenProvider;
use Gcego\Registry\Tests\Fixtures\LoadCounter;

function loader(string $trusted): PhpDefinitionLoader
{
    return new PhpDefinitionLoader(app(), [$trusted]);
}

it('loads a file that returns a definition object', function (): void {
    $path = $this->workspace->blueprint('Modules/Subjects/Subjects.php', 'subjects');
    $definitions = loader($this->workspace->path('Modules'))->loadFile($path);

    expect($definitions)->toHaveCount(1)
        ->and($definitions[0])->toBeInstanceOf(Blueprint::class)
        ->and($definitions[0] instanceof Blueprint ? $definitions[0]->name() : null)->toBe('subjects');
});

it('loads the same file again (require, not require_once)', function (): void {
    $path = $this->workspace->blueprint('Modules/Subjects/Subjects.php', 'subjects');
    $loader = loader($this->workspace->path('Modules'));
    $loader->loadFile($path);
    $loader->loadFile($path);

    expect(LoadCounter::total())->toBe(2);
});

it('loads class-based providers through the container', function (): void {
    $path = $this->workspace->file('Modules/Academics/Academics.php', '<?php return '.AcademicsProvider::class.'::class;');
    config()->set('app.name', 'GCEGO');

    $definitions = loader($this->workspace->path('Modules'))->loadFile($path);

    expect($definitions)->toHaveCount(2)
        ->and($definitions[0] instanceof Blueprint ? $definitions[0]->title() : null)->toBe('Topics of GCEGO')
        ->and(loader($this->workspace->path('Modules'))->load(DefinitionSource::provider(AcademicsProvider::class)))->toHaveCount(2);
});

it('rejects invalid files with the source path', function (string $contents, string $message): void {
    $path = $this->workspace->file('Modules/Bad/Bad.php', $contents);

    expect(fn () => loader($this->workspace->path('Modules'))->loadFile($path))->toThrow(DefinitionLoadException::class, $message);
})->with([
    'no return' => ['<?php $x = 1;', 'returned int. A definition file must return a definition object'],
    'array' => ['<?php return ["name" => "subjects"];', 'returned array'],
    'syntax error' => ['<?php return new Blueprint(;', 'failed with ParseError'],
    'exception' => ['<?php throw new RuntimeException("boom");', 'failed with RuntimeException: boom'],
    'output' => ['<?php echo "hi"; return new Gcego\\Blueprints\\Blueprint("subjects");', 'produced output'],
    'unknown class name' => ['<?php return "App\\\\Missing";', 'does not exist or does not implement'],
]);

it('restores output buffering after failures', function (): void {
    $level = ob_get_level();
    $path = $this->workspace->file('Modules/Bad/Bad.php', '<?php ob_start(); echo "x"; throw new RuntimeException("boom");');

    try {
        loader($this->workspace->path('Modules'))->loadFile($path);
    } catch (DefinitionLoadException) {
    }

    expect(ob_get_level())->toBe($level);
});

it('refuses missing and untrusted files', function (): void {
    $outside = $this->workspace->blueprint('Elsewhere/Evil.php', 'evil');

    expect(fn () => loader($this->workspace->path('Modules'))->loadFile($this->workspace->path('Modules/None.php')))
        ->toThrow(DefinitionLoadException::class, 'does not exist')
        ->and(fn () => loader($this->workspace->directory('Modules'))->loadFile($outside))
        ->toThrow(DefinitionLoadException::class, 'outside every configured discovery root')
        ->and(fn () => loader($this->workspace->directory('Modules'))->loadFile($this->workspace->path('Modules/../Elsewhere/Evil.php')))
        ->toThrow(DefinitionLoadException::class, 'outside every configured discovery root')
        ->and(LoadCounter::total())->toBe(0);
});

it('rejects providers that yield non-objects or are not providers', function (): void {
    expect(fn () => loader($this->workspace->directory('Modules'))->loadProvider(BrokenProvider::class))
        ->toThrow(DefinitionLoadException::class, 'returned string')
        ->and(fn () => loader($this->workspace->directory('Modules'))->loadProvider(stdClass::class))
        ->toThrow(DefinitionLoadException::class, 'does not implement');
});

it('trusts roots created after the loader', function (): void {
    $loader = loader($this->workspace->path('Later'));
    $path = $this->workspace->blueprint('Later/Subjects/Subjects.php', 'subjects');

    expect($loader->loadFile($path))->toHaveCount(1);
});

it('refuses to reload runtime registrations', function (): void {
    loader($this->workspace->directory('Modules'))->load(DefinitionSource::runtime(Blueprint::class));
})->throws(DefinitionLoadException::class);

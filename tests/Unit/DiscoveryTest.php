<?php

declare(strict_types=1);

use Gcego\Registry\Discovery\DefinitionDiscovery;
use Gcego\Registry\Discovery\DirectoryScanner;
use Gcego\Registry\Discovery\DiscoveredFile;
use Gcego\Registry\Discovery\DiscoveryPath;
use Gcego\Registry\Discovery\Entrypoint;
use Gcego\Registry\Exceptions\DiscoveryException;

function root(string $path, string $entrypoint = 'directory', string $kind = 'blueprint', bool $required = false): DiscoveryPath
{
    return DiscoveryPath::fromConfig(0, ['path' => $path, 'kind' => $kind, 'entrypoint' => $entrypoint, 'required' => $required]);
}

/**
 * @param  list<DiscoveredFile>  $files
 * @return list<string>
 */
function relativePaths(array $files): array
{
    return array_map(fn (DiscoveredFile $file): string => $file->relativePath, $files);
}

it('finds nested module entrypoints and ignores their helper files', function (): void {
    $this->workspace->file('Modules/Academics/Subjects/Subjects.php', '<?php');
    $this->workspace->file('Modules/Academics/Subjects/Table.php', '<?php');
    $this->workspace->file('Modules/Academics/Subjects/Form.php', '<?php');
    $this->workspace->file('Modules/Academics/Topics/Topics.php', '<?php');
    $this->workspace->file('Modules/People/Students/Students.php', '<?php');
    $this->workspace->file('Modules/People/Students/Details/Details.php', '<?php');

    $result = (new DefinitionDiscovery([root($this->workspace->path('Modules'))]))->discover();

    expect(relativePaths($result->files))->toBe([
        'Academics/Subjects/Subjects.php',
        'Academics/Topics/Topics.php',
        'People/Students/Details/Details.php',
        'People/Students/Students.php',
    ])
        ->and(array_column($result->skipped, 'reason'))->each->toContain('not an entrypoint');
});

it('supports the file and regex conventions', function (): void {
    $this->workspace->file('Pages/Dashboard.php', '<?php');
    $this->workspace->file('Pages/Reports/Analytics.php', '<?php');
    $this->workspace->file('Custom/Reports/annual.definition.php', '<?php');
    $this->workspace->file('Custom/Reports/helper.php', '<?php');

    expect(relativePaths((new DefinitionDiscovery([root($this->workspace->path('Pages'), 'file')]))->discover()->files))
        ->toBe(['Dashboard.php', 'Reports/Analytics.php'])
        ->and(relativePaths((new DefinitionDiscovery([root($this->workspace->path('Custom'), '#\.definition\.php$#')]))->discover()->files))
        ->toBe(['Reports/annual.definition.php'])
        ->and(Entrypoint::fromConfig('directory', 0)->matches('Single.php'))->toBeTrue()
        ->and(Entrypoint::fromConfig('directory', 0)->matches('A/B.php'))->toBeFalse();
});

it('ignores non-PHP files, hidden and underscored names, and ignored directories', function (): void {
    $this->workspace->file('Pages/Dashboard.php', '<?php');
    $this->workspace->file('Pages/notes.md', '#');
    $this->workspace->file('Pages/.Hidden.php', '<?php');
    $this->workspace->file('Pages/_Partial.php', '<?php');
    $this->workspace->file('Pages/.git/Config.php', '<?php');
    $this->workspace->file('Pages/Tests/DashboardTest.php', '<?php');

    $result = (new DefinitionDiscovery([root($this->workspace->path('Pages'), 'file')], new DirectoryScanner(['Tests'])))->discover();

    expect(relativePaths($result->files))->toBe(['Dashboard.php'])
        ->and(array_column($result->skipped, 'reason'))->toContain('ignored directory name');
});

it('does not follow symbolic links', function (): void {
    $this->workspace->file('Outside/Secret.php', '<?php');
    $this->workspace->file('Pages/Dashboard.php', '<?php');
    symlink($this->workspace->path('Outside/Secret.php'), $this->workspace->path('Pages/Linked.php'));
    symlink($this->workspace->path('Outside'), $this->workspace->path('Pages/LinkedDir'));

    $result = (new DefinitionDiscovery([root($this->workspace->path('Pages'), 'file')]))->discover();

    expect(relativePaths($result->files))->toBe(['Dashboard.php'])
        ->and(array_values(array_filter(array_column($result->skipped, 'reason'), fn (string $reason): bool => str_contains($reason, 'symbolic'))))->toHaveCount(2);
});

it('scans multiple roots in order and deduplicates roots and files', function (): void {
    $this->workspace->file('Modules/Subjects/Subjects.php', '<?php');
    $this->workspace->file('Pages/Dashboard.php', '<?php');

    $result = (new DefinitionDiscovery([
        root($this->workspace->path('Pages'), 'file', 'page'),
        root($this->workspace->path('Modules')),
        root($this->workspace->path('Pages').'/', 'file', 'page'),
        root($this->workspace->path(), 'file', 'page'),
    ]))->discover();

    expect(array_map(fn (DiscoveredFile $file): string => $file->root->kind.':'.$file->relativePath, $result->files))->toBe([
        'page:Dashboard.php',
        'blueprint:Subjects/Subjects.php',
    ])
        ->and(array_column($result->skipped, 'reason'))->toContain('root already configured')
        ->and(array_column($result->skipped, 'reason'))->toContain('already discovered under another root');
});

it('scans one folder for several kinds, each with its own entrypoint rule', function (): void {
    $this->workspace->file('Modules/Subjects/Subjects.php', '<?php');
    $this->workspace->file('Modules/Academics/Module.php', '<?php');

    $result = (new DefinitionDiscovery([
        root($this->workspace->path('Modules')),
        root($this->workspace->path('Modules'), '#(^|/)Module\.php$#', 'module'),
    ]))->discover();

    expect(array_map(fn (DiscoveredFile $file): string => $file->root->kind.':'.$file->relativePath, $result->files))->toBe([
        'blueprint:Subjects/Subjects.php',
        'module:Academics/Module.php',
    ])->and(array_column($result->skipped, 'reason'))->not->toContain('root already configured');
});

it('skips missing optional roots and fails on required or invalid ones', function (): void {
    $this->workspace->file('NotADirectory', 'x');

    $result = (new DefinitionDiscovery([root($this->workspace->path('Missing'))]))->discover();

    expect($result->files)->toBe([])
        ->and($result->missingRoots)->toHaveCount(1)
        ->and(fn () => (new DefinitionDiscovery([root($this->workspace->path('Missing'), required: true)]))->discover())
        ->toThrow(DiscoveryException::class, 'Required discovery root')
        ->and(fn () => (new DefinitionDiscovery([root($this->workspace->path('NotADirectory'))]))->discover())
        ->toThrow(DiscoveryException::class, 'is not a readable directory');
});

it('validates root configuration', function (array $config, string $message): void {
    expect(fn () => DiscoveryPath::fromConfig('modules', $config))->toThrow(DiscoveryException::class, $message);
})->with([
    [['kind' => 'blueprint'], '"path" must be a non-empty string'],
    [['path' => '/x', 'kind' => 'Blue Print'], '"kind" must be a definition kind identifier'],
    [['path' => '/x', 'kind' => 'blueprint', 'entrypoint' => '(unclosed'], 'entrypoint must be "directory", "file" or a valid regular expression'],
    [['path' => '/x', 'kind' => 'blueprint', 'scope' => 'Admin'], '"scope" must be lowercase'],
    [['path' => '/x', 'kind' => 'blueprint', 'required' => 'yes'], '"required" must be a boolean'],
]);

it('fingerprints supporting files an entrypoint includes', function (): void {
    $this->workspace->file('Modules/Academics/Module.php', '<?php // module');
    $resource = $this->workspace->file('Modules/Academics/Resources/Subjects.php', '<?php // one');
    $discovery = new DefinitionDiscovery([root($this->workspace->path('Modules'), '#(^|/)Module\\.php$#', 'module')]);
    $first = $discovery->discover()->fingerprint();

    expect($discovery->discover()->files)->toHaveCount(1)->and($discovery->discover()->fingerprint())->toBe($first);

    file_put_contents($resource, '<?php // edited resource');
    clearstatcache();
    $edited = $discovery->discover()->fingerprint();
    expect($edited)->not->toBe($first);

    $added = $this->workspace->file('Modules/Academics/Resources/Topics.php', '<?php');
    expect($discovery->discover()->fingerprint())->not->toBe($edited);

    unlink($added);
    expect($discovery->discover()->fingerprint())->toBe($edited);
});

it('fingerprints content changes deterministically', function (): void {
    $path = $this->workspace->file('Pages/Dashboard.php', '<?php // one');
    $discovery = new DefinitionDiscovery([root($this->workspace->path('Pages'), 'file', 'page')]);
    $first = $discovery->discover()->fingerprint();

    expect($discovery->discover()->fingerprint())->toBe($first);

    file_put_contents($path, '<?php // changed size');
    clearstatcache();

    expect($discovery->discover()->fingerprint())->not->toBe($first);

    $this->workspace->file('Pages/Analytics.php', '<?php');

    expect($discovery->discover()->fingerprint())->not->toBe($first);
});

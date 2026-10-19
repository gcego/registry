<?php

declare(strict_types=1);

use Gcego\Blueprints\Blueprint;
use Gcego\Registry\Exceptions\DuplicateDefinitionException;
use Gcego\Registry\Exceptions\InvalidDefinitionException;
use Gcego\Registry\Identity\DefinitionIdentity;
use Gcego\Registry\Index\DefinitionDescriptor;
use Gcego\Registry\Index\DefinitionIndex;
use Gcego\Registry\Index\DefinitionSource;
use Gcego\Registry\Index\SourceType;

function descriptor(string $identity, string $path = '/app/a.php', array $metadata = []): DefinitionDescriptor
{
    return new DefinitionDescriptor(DefinitionIdentity::fromString($identity), DefinitionSource::file($path), Blueprint::class, $metadata);
}

it('indexes descriptors in order with constant-time lookup', function (): void {
    $index = new DefinitionIndex(descriptor('blueprint:subjects'), descriptor('page:dashboard', '/app/d.php'), descriptor('blueprint:admin.topics', '/app/t.php'));

    expect($index->count())->toBe(3)
        ->and($index->has('blueprint:subjects'))->toBeTrue()
        ->and($index->get('page:dashboard')?->name())->toBe('dashboard')
        ->and($index->get('page:missing'))->toBeNull()
        ->and($index->identities())->toBe(['blueprint:subjects', 'page:dashboard', 'blueprint:admin.topics'])
        ->and($index->kinds())->toBe(['blueprint', 'page'])
        ->and($index->names('blueprint'))->toBe(['subjects', 'topics'])
        ->and(count($index->ofKind('blueprint')))->toBe(2)
        ->and(count($index->withName('topics')))->toBe(1)
        ->and(array_keys(iterator_to_array($index)))->toBe($index->identities());
});

it('collapses identical registrations and rejects conflicting sources', function (): void {
    expect((new DefinitionIndex(descriptor('blueprint:subjects'), descriptor('blueprint:subjects')))->count())->toBe(1)
        ->and(fn () => new DefinitionIndex(descriptor('blueprint:subjects', '/app/a.php'), descriptor('blueprint:subjects', '/app/b.php')))
        ->toThrow(DuplicateDefinitionException::class, "First:  file /app/a.php\nSecond: file /app/b.php");
});

it('keeps kinds and scopes distinct', function (): void {
    $index = new DefinitionIndex(descriptor('blueprint:subjects', '/a.php'), descriptor('page:subjects', '/b.php'), descriptor('blueprint:admin.subjects', '/c.php'));

    expect($index->count())->toBe(3);
});

it('filters by kind, scope and source', function (): void {
    $index = new DefinitionIndex(
        descriptor('blueprint:subjects', '/a.php'),
        descriptor('blueprint:admin.topics', '/b.php'),
        new DefinitionDescriptor(DefinitionIdentity::fromString('blueprint:lessons'), DefinitionSource::provider(stdClass::class), Blueprint::class),
    );

    expect(array_map(fn ($d): string => $d->key(), $index->filter(scope: '')))->toBe(['blueprint:subjects', 'blueprint:lessons'])
        ->and(array_map(fn ($d): string => $d->key(), $index->filter(scope: 'admin')))->toBe(['blueprint:admin.topics'])
        ->and(array_map(fn ($d): string => $d->key(), $index->filter(sourceType: SourceType::Provider)))->toBe(['blueprint:lessons'])
        ->and(array_map(fn ($d): string => $d->key(), $index->filter(location: '/a.php')))->toBe(['blueprint:subjects'])
        ->and($index->filter(kind: 'page'))->toBe([]);
});

it('merges indexes and detects conflicts', function (): void {
    $merged = (new DefinitionIndex(descriptor('blueprint:a', '/a.php')))->merge(new DefinitionIndex(descriptor('blueprint:b', '/b.php')));

    expect($merged->identities())->toBe(['blueprint:a', 'blueprint:b'])
        ->and(fn () => $merged->merge(new DefinitionIndex(descriptor('blueprint:a', '/other.php'))))->toThrow(DuplicateDefinitionException::class);
});

it('round-trips through arrays', function (): void {
    $index = new DefinitionIndex(descriptor('blueprint:subjects', '/a.php', ['title' => 'Subjects', 'slug' => 'subjects', 'icon' => null]));

    expect(DefinitionIndex::fromArray($index->toArray())->toArray())->toBe($index->toArray());
});

it('rejects malformed rows', function (array $rows): void {
    DefinitionIndex::fromArray($rows);
})->with([
    'not an array' => [['nope']],
    'missing kind' => [[['name' => 'a']]],
    'runtime source' => [[['kind' => 'blueprint', 'name' => 'a', 'scope' => null, 'source' => ['type' => 'runtime', 'location' => 'X'], 'class' => 'X', 'metadata' => []]]],
    'non-scalar metadata' => [[['kind' => 'blueprint', 'name' => 'a', 'scope' => null, 'source' => ['type' => 'file', 'location' => '/a.php'], 'class' => 'X', 'metadata' => ['x' => []]]]],
])->throws(InvalidDefinitionException::class);

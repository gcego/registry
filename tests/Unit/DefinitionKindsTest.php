<?php

declare(strict_types=1);

use Gcego\Registry\Contracts\DefinitionKind;
use Gcego\Registry\Exceptions\DuplicateDefinitionException;
use Gcego\Registry\Kinds\DefinitionKinds;
use Gcego\Registry\Tests\Fixtures\ExampleDefinition;
use Gcego\Registry\Tests\Fixtures\ExampleKind;
use Gcego\Registry\Tests\Fixtures\PageDefinition;
use Gcego\Registry\Tests\Fixtures\PageKind;
use Gcego\Registry\Tests\Fixtures\SpecialDefinition;

it('registers kinds and matches objects by contract', function (): void {
    $kinds = new DefinitionKinds;
    $kinds->register(new ExampleKind);
    $kinds->register(new PageKind);
    $kinds->register(new PageKind);

    expect($kinds->ids())->toBe(['module', 'page'])
        ->and($kinds->has('page'))->toBeTrue()
        ->and($kinds->get('widget'))->toBeNull()
        ->and(array_map(fn (DefinitionKind $kind): string => $kind->id(), $kinds->matching(new PageDefinition('dashboard'))))->toBe(['page'])
        ->and($kinds->matching(new stdClass))->toBe([]);
});

it('matches only the most specific kind when contracts extend each other', function (): void {
    $kinds = new DefinitionKinds;
    $kinds->register(new ExampleKind);
    $kinds->register(new class implements DefinitionKind
    {
        public function id(): string
        {
            return 'special';
        }

        public function contract(): string
        {
            return SpecialDefinition::class;
        }

        public function name(object $definition): string
        {
            return 'special';
        }

        public function validate(object $definition): array
        {
            return [];
        }

        public function metadata(object $definition): array
        {
            return [];
        }
    });

    expect(array_map(fn (DefinitionKind $kind): string => $kind->id(), $kinds->matching(new SpecialDefinition('special'))))->toBe(['special'])
        ->and(array_map(fn (DefinitionKind $kind): string => $kind->id(), $kinds->matching(new ExampleDefinition('subjects'))))->toBe(['module']);
});

it('rejects conflicting and invalid kinds', function (): void {
    $kinds = new DefinitionKinds;
    $kinds->register(new ExampleKind);
    $impostor = new class implements DefinitionKind
    {
        public function id(): string
        {
            return 'module';
        }

        public function contract(): string
        {
            return stdClass::class;
        }

        public function name(object $definition): string
        {
            return 'x';
        }

        public function validate(object $definition): array
        {
            return [];
        }

        public function metadata(object $definition): array
        {
            return [];
        }
    };

    expect(fn () => $kinds->register($impostor))->toThrow(DuplicateDefinitionException::class, 'Definition kind "module" is already registered');
});

it('uses the registered kind for naming validation and metadata', function (): void {
    $kind = new ExampleKind;
    $valid = new ExampleDefinition('subjects');
    $invalid = new ExampleDefinition('subjects', '');

    expect($kind->name($valid))->toBe('subjects')
        ->and($kind->validate($valid))->toBe([])
        ->and($kind->validate($invalid))->toBe(['title: a module needs a title'])
        ->and($kind->metadata($valid))->toBe(['title' => 'Subjects'])
        ->and(fn () => $kind->name(new ExampleDefinition))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $kind->name(new stdClass))->toThrow(InvalidArgumentException::class);
});

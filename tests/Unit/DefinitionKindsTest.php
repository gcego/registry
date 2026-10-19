<?php

declare(strict_types=1);

use Gcego\Blueprints\Blueprint;
use Gcego\Blueprints\Exceptions\InvalidBlueprintException;
use Gcego\Registry\Contracts\DefinitionKind;
use Gcego\Registry\Exceptions\DuplicateDefinitionException;
use Gcego\Registry\Kinds\BlueprintKind;
use Gcego\Registry\Kinds\DefinitionKinds;
use Gcego\Registry\Tests\Fixtures\ExampleTable;
use Gcego\Registry\Tests\Fixtures\PageDefinition;
use Gcego\Registry\Tests\Fixtures\PageKind;
use Gcego\Registry\Tests\Fixtures\SpecialBlueprint;

it('registers kinds and matches objects by contract', function (): void {
    $kinds = new DefinitionKinds;
    $kinds->register(new BlueprintKind);
    $kinds->register(new PageKind);
    $kinds->register(new PageKind);

    expect($kinds->ids())->toBe(['blueprint', 'page'])
        ->and($kinds->has('page'))->toBeTrue()
        ->and($kinds->get('widget'))->toBeNull()
        ->and(array_map(fn (DefinitionKind $kind): string => $kind->id(), $kinds->matching(new PageDefinition('dashboard'))))->toBe(['page'])
        ->and($kinds->matching(new stdClass))->toBe([]);
});

it('matches only the most specific kind when contracts extend each other', function (): void {
    $kinds = new DefinitionKinds;
    $kinds->register(new BlueprintKind);
    $kinds->register(new class implements DefinitionKind
    {
        public function id(): string
        {
            return 'special';
        }

        public function contract(): string
        {
            return SpecialBlueprint::class;
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

    expect(array_map(fn (DefinitionKind $kind): string => $kind->id(), $kinds->matching(new SpecialBlueprint('special'))))->toBe(['special'])
        ->and(array_map(fn (DefinitionKind $kind): string => $kind->id(), $kinds->matching(new Blueprint('subjects'))))->toBe(['blueprint']);
});

it('rejects conflicting and invalid kinds', function (): void {
    $kinds = new DefinitionKinds;
    $kinds->register(new BlueprintKind);
    $impostor = new class implements DefinitionKind
    {
        public function id(): string
        {
            return 'blueprint';
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

    expect(fn () => $kinds->register($impostor))->toThrow(DuplicateDefinitionException::class, 'Definition kind "blueprint" is already registered');
});

it('delegates blueprint naming, validation and metadata to gcego/blueprints', function (): void {
    $kind = new BlueprintKind;
    $valid = new Blueprint('subjects', new ExampleTable('table'));
    $invalid = new Blueprint('subjects', new ExampleTable('a'), new ExampleTable('b'));

    expect($kind->name($valid))->toBe('subjects')
        ->and($kind->validate($valid))->toBe([])
        ->and($kind->validate($invalid))->toBe(['subjects.b: Blueprint "subjects" already has a "table" part at subjects.a; the role allows only one. [blueprint.duplicate_singleton]'])
        ->and($kind->metadata($valid))->toBe(['title' => 'Subjects', 'slug' => 'subjects', 'description' => null, 'icon' => null, 'roles' => 'table'])
        ->and(fn () => $kind->name(new Blueprint))->toThrow(InvalidBlueprintException::class)
        ->and(fn () => $kind->name(new stdClass))->toThrow(InvalidArgumentException::class);
});

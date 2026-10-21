<?php

declare(strict_types=1);

use Gcego\Registry\Exceptions\InvalidDefinitionException;
use Gcego\Registry\Identity\DefinitionIdentity;

it('formats and parses identities', function (string $string, string $kind, string $name, ?string $scope): void {
    $identity = DefinitionIdentity::fromString($string);

    expect($identity->kind)->toBe($kind)
        ->and($identity->name)->toBe($name)
        ->and($identity->scope)->toBe($scope)
        ->and($identity->toString())->toBe($string)
        ->and((string) $identity)->toBe($string);
})->with([
    ['module:subjects', 'module', 'subjects', null],
    ['page:dashboard', 'page', 'dashboard', null],
    ['module:admin.subjects', 'module', 'subjects', 'admin'],
    ['module:academics.subjects', 'module', 'subjects', 'academics'],
    ['widget:school.staff.statistics', 'widget', 'statistics', 'school.staff'],
    ['module:past_papers', 'module', 'past_papers', null],
]);

it('rejects invalid identities', function (string $identity): void {
    DefinitionIdentity::fromString($identity);
})->with(['subjects', 'ExampleDefinition:subjects', 'module:Subjects', 'module:', ':subjects', 'module:a..b', 'module:sub jects', 'blue print:x'])
    ->throws(InvalidDefinitionException::class, 'Invalid definition identity');

it('compares identities exactly', function (): void {
    expect((new DefinitionIdentity('module', 'subjects'))->equals(DefinitionIdentity::fromString('module:subjects')))->toBeTrue()
        ->and((new DefinitionIdentity('module', 'subjects'))->equals(new DefinitionIdentity('page', 'subjects')))->toBeFalse()
        ->and((new DefinitionIdentity('module', 'subjects', 'admin'))->equals(new DefinitionIdentity('module', 'subjects')))->toBeFalse()
        ->and(DefinitionIdentity::isQualified('subjects'))->toBeFalse()
        ->and(DefinitionIdentity::isQualified('module:subjects'))->toBeTrue();
});

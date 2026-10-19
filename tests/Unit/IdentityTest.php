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
    ['blueprint:subjects', 'blueprint', 'subjects', null],
    ['page:dashboard', 'page', 'dashboard', null],
    ['blueprint:admin.subjects', 'blueprint', 'subjects', 'admin'],
    ['module:academics.subjects', 'module', 'subjects', 'academics'],
    ['widget:school.staff.statistics', 'widget', 'statistics', 'school.staff'],
    ['blueprint:past_papers', 'blueprint', 'past_papers', null],
]);

it('rejects invalid identities', function (string $identity): void {
    DefinitionIdentity::fromString($identity);
})->with(['subjects', 'Blueprint:subjects', 'blueprint:Subjects', 'blueprint:', ':subjects', 'blueprint:a..b', 'blueprint:sub jects', 'blue print:x'])
    ->throws(InvalidDefinitionException::class, 'Invalid definition identity');

it('compares identities exactly', function (): void {
    expect((new DefinitionIdentity('blueprint', 'subjects'))->equals(DefinitionIdentity::fromString('blueprint:subjects')))->toBeTrue()
        ->and((new DefinitionIdentity('blueprint', 'subjects'))->equals(new DefinitionIdentity('page', 'subjects')))->toBeFalse()
        ->and((new DefinitionIdentity('blueprint', 'subjects', 'admin'))->equals(new DefinitionIdentity('blueprint', 'subjects')))->toBeFalse()
        ->and(DefinitionIdentity::isQualified('subjects'))->toBeFalse()
        ->and(DefinitionIdentity::isQualified('blueprint:subjects'))->toBeTrue();
});

<?php

declare(strict_types=1);

use Gcego\Registry\Contracts\RegistryInterface;
use Gcego\Registry\Facades\Registry;
use Gcego\Registry\Registry as RegistryService;
use Gcego\Registry\Tests\Fixtures\ExampleDefinition;

it('uses the same registry singleton through the facade and container', function (): void {
    $definition = new ExampleDefinition('reports');
    $descriptor = Registry::register($definition);

    expect(Registry::getFacadeRoot())->toBe(app(RegistryService::class))
        ->and(Registry::getFacadeRoot())->toBe(app(RegistryInterface::class))
        ->and(Registry::has('module:reports'))->toBeTrue()
        ->and(Registry::get('module:reports', ExampleDefinition::class))->toBe($definition)
        ->and(Registry::descriptor('module:reports'))->toBe($descriptor)
        ->and(Registry::names('module'))->toBe(['reports']);
});

it('discovers and refreshes definitions through the facade', function (): void {
    expect(Registry::count())->toBe(0);

    $this->workspace->module('Modules/Subjects/Subjects.php', 'subjects');
    Registry::refresh();

    expect(Registry::get('module:subjects'))->toBeInstanceOf(ExampleDefinition::class)
        ->and(Registry::count('module'))->toBe(1);
});

<?php

declare(strict_types=1);

namespace Gcego\Registry\Tests\Fixtures;

use Gcego\Registry\Contracts\DefinitionKind;
use InvalidArgumentException;

/** An application-owned kind with no external definition engine. */
final readonly class ExampleKind implements DefinitionKind
{
    public function id(): string
    {
        return 'module';
    }

    public function contract(): string
    {
        return ExampleDefinition::class;
    }

    public function name(object $definition): string
    {
        return $this->definition($definition)->name();
    }

    public function validate(object $definition): array
    {
        return $this->definition($definition)->title() === '' ? ['title: a module needs a title'] : [];
    }

    public function metadata(object $definition): array
    {
        return ['title' => $this->definition($definition)->title()];
    }

    private function definition(object $definition): ExampleDefinition
    {
        return $definition instanceof ExampleDefinition ? $definition : throw new InvalidArgumentException('Not an application definition.');
    }
}

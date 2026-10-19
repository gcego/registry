<?php

declare(strict_types=1);

namespace Gcego\Registry\Tests\Fixtures;

use Gcego\Registry\Contracts\DefinitionKind;
use InvalidArgumentException;

final readonly class PageBookKind implements DefinitionKind
{
    public function id(): string
    {
        return 'book';
    }

    public function contract(): string
    {
        return PageBook::class;
    }

    public function name(object $definition): string
    {
        return $definition instanceof PageBook ? $definition->name : throw new InvalidArgumentException('Not a book.');
    }

    public function validate(object $definition): array
    {
        return [];
    }

    public function metadata(object $definition): array
    {
        return ['pages' => $definition instanceof PageBook ? count($definition->pages) : 0];
    }
}

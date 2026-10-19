<?php

declare(strict_types=1);

namespace Gcego\Registry\Tests\Fixtures;

use Gcego\Registry\Contracts\DefinitionKind;
use InvalidArgumentException;

/**
 * A custom definition kind registered the way a future package would.
 */
final readonly class PageKind implements DefinitionKind
{
    public function id(): string
    {
        return 'page';
    }

    public function contract(): string
    {
        return PageDefinition::class;
    }

    public function name(object $definition): string
    {
        return $this->page($definition)->name;
    }

    public function validate(object $definition): array
    {
        return $this->page($definition)->title === '' ? ['title: a page needs a title'] : [];
    }

    public function metadata(object $definition): array
    {
        return ['title' => $this->page($definition)->title];
    }

    private function page(object $definition): PageDefinition
    {
        return $definition instanceof PageDefinition ? $definition : throw new InvalidArgumentException('Not a page.');
    }
}

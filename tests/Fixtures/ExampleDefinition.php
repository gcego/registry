<?php

declare(strict_types=1);

namespace Gcego\Registry\Tests\Fixtures;

/** A plain application definition used only by registry tests and benchmarks. */
class ExampleDefinition
{
    private readonly string $label;

    public function __construct(private readonly string $identifier = '', ?string $title = null)
    {
        $this->label = $title ?? ucwords(str_replace('-', ' ', $identifier));
    }

    public function name(): string
    {
        if ($this->identifier === '') {
            throw new \InvalidArgumentException('A definition needs a name.');
        }

        return $this->identifier;
    }

    public function title(): string
    {
        return $this->label;
    }
}

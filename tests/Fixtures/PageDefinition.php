<?php

declare(strict_types=1);

namespace Gcego\Registry\Tests\Fixtures;

/**
 * A non-component definition type, standing in for a future gcego/pages page.
 */
final readonly class PageDefinition
{
    public function __construct(
        public string $name,
        public string $title = '',
    ) {}
}

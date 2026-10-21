<?php

declare(strict_types=1);

namespace Gcego\Registry\Tests\Fixtures;

/**
 * A plain local page definition used to test a second kind.
 */
final readonly class PageDefinition
{
    public function __construct(
        public string $name,
        public string $title = '',
    ) {}
}

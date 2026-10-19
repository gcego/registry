<?php

declare(strict_types=1);

namespace Gcego\Registry\Tests\Fixtures;

use Gcego\Registry\Contracts\DefinitionProvider;

final readonly class BrokenProvider implements DefinitionProvider
{
    public function definitions(): iterable
    {
        // Deliberately violates the iterable<object> contract to prove the loader rejects it.
        yield 'not an object'; // @phpstan-ignore generator.valueType
    }
}
